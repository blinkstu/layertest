<?php
namespace api\admin\controller;

use think\Db;
use api\admin\model\LiftModel;
use api\admin\model\NotyModel;
use api\admin\model\WorkModel;
use api\admin\model\UserModel;
use cmf\controller\RestBaseController;
use JPush\Client as JPush;
use think\Request;
use think\Cache;

class RuntimeController extends RestBaseController
{
    /**
     * JS文件获取runtime服务器地址
     */
    public function getRuntimeServer(){
        if($this->request->isPost()){
            $lift_settings = cmf_get_option('lift_settings');
            $runtimeServerUrl = $lift_settings['runtimeServerUrl'];
            return $runtimeServerUrl;
        }
    }

    /**
     * 此方法10分钟会被Runtime服务器执行一次
     * 1.检查所有电梯
     */
    public function liftNoty()
    {
        $LiftModel = new LiftModel();
        $lift = $LiftModel->with('worker,contract')->select()->toArray();
        $currentDate = strtotime(date('Y-m-d'));

        //获取用户设置项目
        $lift_settings = cmf_get_option('lift_settings');
        $lift_early = $lift_settings['lift_early'];
        $lift_late = $lift_settings['lift_late'];
        $noty_time = $lift_settings['noty_time'];
        $WorkModel = new WorkModel();
        $liftModel = new LiftModel();
        $notyModel = new NotyModel();

        //遍历所有电梯
        foreach ($lift as $key => $vo) {
            $worker_id = $vo['worker']['id'];
            $lift_id = $vo['id'];
            $gap = $vo['gap'];
            $next_date = strtotime($vo['next_date']);
            $next_data_text = $vo['next_date'];
            $day_lefts = floor(($next_date - $currentDate) / 86400);
            $last_noty = $notyModel->where('lift_id', $lift_id)->where('user_id', $worker_id)->order('create_time desc')->find();
            
            if ($last_noty == null) {
                $last_noty_time = null;
            } else {
                $last_noty_time = strtotime($last_noty['create_time']);
            }

            switch ($day_lefts) {case 0:$noty_gap = '-1 hours';break;case $day_lefts < 0:$noty_gap = '-1 hours';break;case 1:$noty_gap = '-1 day'; break; case 2: $noty_gap = '-2 days'; break; case 3: $noty_gap = '-3 days'; break; default: $noty_gap = null; break; }

            if($noty_gap == null){
                continue;
            }
            if($day_lefts == 0 || $day_lefts < 0){
                $callBackend = true;
            }else{
                $callBackend == false;
            }
            $address = $vo['map_address'];

            $currentHour = (int) date('H');
            $startHour = (int) $noty_time;
            $endHour = 22;

            if($currentHour < $endHour && $currentHour > $startHour)
            {
                if ($last_noty_time === null || $last_noty_time < strtotime($noty_gap) && $noty_gap != null) {
                    $this->createNoty(1, $lift_id, $worker_id, $day_lefts, $next_data_text, '', $callBackend, $address);
                }
            }

        }
        $this->success('good');
    }

    /**
     * 实时监控电梯系统，控制电梯状态
     */
    public function checkLift(){
        $lift_settings  = cmf_get_option('lift_settings');
        $maintain_time = $lift_settings['maintain_time'];
        $currentTimestamp = time();
        $LiftModel = new LiftModel();
        $UserModel = new UserModel();
        $users = $UserModel::hasWhere('role',['role_id'=>'4'])->where('user_type=1')->select();
        //遍历所有工作人员
        foreach($users as $user){
            $userId = $user['id'];
            //遍历该员工下所有电梯
            $lifts = $LiftModel->where('worker_id',$userId)->where('status',1)->select();
            $tasks = $LiftModel->where('worker_id',$userId)->where('status',2)->order('update_time desc')->select();

            //查看队列中的项目是否完成
            if(count($tasks)){
                foreach($tasks as $task){
                    if($task['maintain_end']<=$currentTimestamp){
                        $LiftModel->update(['id'=>$task['id'],'status'=>3]);
                    }
                }
            }
            // 启动队列中任意一个电梯
            if (!count($tasks) && count($lifts)){
                $LiftModel->where('worker_id',$userId)->where('status',1)->update(['status'=>2,'maintain_end'=>strtotime('+ '.$maintain_time.' minutes')]);
            }
        }
        return $this->success('检查成功');
    }

    /**
     * 发送post请求
     * @param string $url
     * @param string $param
     * @return bool|mixed
     */
    private function requestPost($url = '', $param = '')
    {
        if (empty($url) || empty($param)) {
            return false;
        }
        $postUrl = $url;
        $curlPost = $param;
        $ch = curl_init(); //初始化curl
        curl_setopt($ch, CURLOPT_URL, $postUrl); //抓取指定网页
        curl_setopt($ch, CURLOPT_HEADER, 0); //设置header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_POST, 1); //post提交方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
        $data = curl_exec($ch); //运行curl
        curl_close($ch);
        return $data;
    }


    /**
     * 后台主页测试推送系统
     */
    public function testNoty(){
        $params = $this->request->param();
        $user_id = $params['id'];
        $user = Db::name('user')->find($user_id);
        if($user == null){
            $this->error('错误的用户ID');
        }
        $result = $this->createNoty(3, 0, $user_id, 0, '', '测试内容', false, '');
        
        if($result == 'good'){
            $this->success('通知成功');
        }else if($result['App'] != 1){
            $this->error('当前用户不在线,但已经通知',$result);
        }else{
            $this->error('错误',$result);
        }
    }

    /**
     * 创建通知内容
     * @param int $type 1:电梯 2:请求 3:普通提醒
     * @param int $id
     * @param int $userId
     * @param int $days
     * @param string $time
     * @param string $content
     * @param bool $callBackend
     */
    private function createNoty($type,$id,$userId,$days,$time,$content,$callBackend = false,$address=''){

        //通知后台
        if ($callBackend) {
            $this->callBackend($type, $id, $userId, $days, $time, $content);
        }

        //制作content
        switch($type){
            case 1:
                if($days > 0){
                    $content = '您有一个电梯维保还有' . $days . '天到期';
                    $left = '剩余时间';
                } else if($days == 0){
                    $left = '剩余时间';
                    $content = '您有一个电梯维保今天到期';
                } else if($days < 0){
                    $left = '过期时间';
                    $days = -$days;
                    $content = '您有一个电梯维保已经过期'. $days .'天';
                }
                break;
            case 2:
                break;
            case 3:
                $content = $content;
            default:
        }

        if($type == 1){
            $result = $this->doNoty1($id, $userId, $days, $time, $content, $address);
        }else if($type == 3){
            $result = $this->doNoty3($id, $userId, $days, $time, $content, $address);
        }

        return $result;
    }


    /**
     * 通知后台
     */
    private function callBackend($type, $id, $userId, $days, $content){
        $user = Db::name('user')->where('id', $userId)->find();
        $user_nickname = $user['user_nickname'];
        switch ($type) {
            case 1:
                if ($days < 0) {
                    $content = '工作人员' . $user_nickname . '有一个电梯维保过期' . -$days . '天';
                } else if ($days == 0) {
                    $content = '工作人员' . $user_nickname . '有一个电梯维保今天到期';
                }
                break;
            case 2:
                break;
            case 3:
                $content = $content;
            default:
        }
        if ($days <= 0) {
            $notyModel = new NotyModel();
            $notyModel->insert([
                'lift_id' => $id,
                'user_id' => 1,
                'type' => 3,
                'status' => 1,
                'text' => $content,
                'create_time' => date('Y-m-d H:i:s')
            ]);
            $this->send_noty($content, 1);
        }
    }

    /**
     * 类型一的通知
     */
    private function doNoty1($id, $userId, $days, $time, $content, $address){
        $notyModel = new NotyModel();
        $notyModel->insert([
            'lift_id' => $id,
            'user_id' => $userId,
            'type' => 1,
            'status' => 1,
            'text' => $content,
            'create_time' => date('Y-m-d H:i:s')
        ]);
        $data = [
            'end_date' => [
                "value" => $time,
                "color" => "#f74c31"
            ],
            'left' => [
                "value" => $left,
                "color" => "#000"
            ],
            'days' => [
                "value" => $days . '天',
                "color" => "#f74c31"
            ],
            'address' => [
                "value" => $address,
                "color" => "#f74c31"
            ]
        ];
        $resultWechat = $this->sendWechatNoty($userId, 'tNeEiX_yEuJSzG_4tLlqrW2l_PmgEmrdvel5E_suKY0', 'https://vue.c6pu.com/Lift/Details/' . $id, $data);
        $resultApp = $this->sendAppNoty(1,$userId,$content);
        $resultWeb = $this->sendWebNoty($content, $userId);
        $data = [
            'Wechat' => $resultWechat,
            'App' => $resultApp,
            'Web' => $resultWeb,
        ];
        return $data;
    }

    /**
     * 类型三的通知
     */
    private function doNoty3($id, $userId, $days, $time, $content,$address){
        $notyModel = new NotyModel();
        $notyModel->insert([
            'lift_id' => $id,
            'user_id' => $userId,
            'type' => 3,
            'status' => 1,
            'text' => $content,
            'create_time' => date('Y-m-d H:i:s')
        ]);
        //发送微信
        $data = [
            'end_date' => [
                "value" => '2018-00-00',
                "color" => "#f74c31"
            ],
            'left' => [
                "value" => '剩余时间',
                "color" => "#000"
            ],
            'days' => [
                "value" => '0天',
                "color" => "#f74c31"
            ],
            'address' => [
                "value" => '测试通知测试通知',
                "color" => "#f74c31"
            ]
        ];
        $resultWechat = $this->sendWechatNoty($userId, 'tNeEiX_yEuJSzG_4tLlqrW2l_PmgEmrdvel5E_suKY0', 'https://vue.c6pu.com/Lift/Details/' . $id, $data);
        $resultApp = $this->sendAppNoty(3, $userId, $content);
        $resultWeb = $this->sendWebNoty($content, $userId);
        $data = [
            'Wechat' => $resultWechat,
            'App' => $resultApp,
            'Web' => $resultWeb,
        ];
        return $data;
    }
    
    /**
     * APP通知
     */
    private function sendAppNoty($type, $user_id, $content){
        try {
            $client = new JPush("b28f67d60b0df4ac05a997cc", "a687eba8d0359a780c113465", null);
            $client->push()
                ->setPlatform('all')
                ->addAlias([(string)$user_id])
                ->androidNotification($content, [
                    'extras' => [
                        'type' => $type
                    ]
                ])
                ->setNotificationAlert($content)
                ->send();
        } catch (\JPush\Exceptions\APIConnectionException $e) {
            return 0;
        } catch (\JPush\Exceptions\APIRequestException $e) {
            return 0;
        }
        return 1;
    }

    /**
     * 微信消息模板API
     * @param string $openid
     * @param string $template_id
     * @param string $url
     * @param $data
     * @param string $topcolor
     * return bool
     */
    private function sendWechatNoty($user_id, $template_id, $url, $data, $topcolor = '#7B68EE')
    {
        $user = Db::name('third_party_user')->where('user_id', $user_id)->find();
        if ($user == null) {
            return false;
        } else {
            $touser = $user['openid'];
        }
        $lift_settings = cmf_get_option('lift_settings');
        $appId = $lift_settings['appId'];
        $secret = $lift_settings['secret'];

        if (!Cache::get('access_token')) {
            $url1 = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appId&secret=$secret";
            $response = cmf_curl_get($url1);
            $response = json_decode($response, true);
            if (empty($response['access_token'])) {
            //$this->error('操作失败');
                return false;
            }
            $accessToken = $response['access_token'];
            Cache::set('access_token', $accessToken, 7000);
        } else {
            $accessToken = Cache::get('access_token');
        }

        $template = array(
            'touser' => $touser,
            'template_id' => $template_id,
            'url' => $url,
            'topcolor' => $topcolor,
            'data' => $data
        );
        $json_template = json_encode($template);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $accessToken;
        $dataRes = $this->requestPost($url, urldecode($json_template));
        $dataRes = json_decode($dataRes, true);
        if ($dataRes['errcode'] == 0) {
            return $dataRes;
        } else {
            return $dataRes;
        }
        return 1;
    }

    /**
     * 发送到WORKERMAN
     * @param string $content
     */
    private function sendWebNoty($content,$id){
        $server = 'c6pu.com';
        //$server = '192.168.100.10';
        $data = [
            "code" => 1, 
            "msg" => $content
        ];
        $data = json_encode($data);
        $response = cmf_curl_get('http://'.$server.':2121/?type=publish&to='.$id.'&content='.$data);
        return $response;
    }
}
