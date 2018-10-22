<?php
namespace api\admin\controller;

use api\admin\model\LiftModel;
use api\admin\model\NotyModel;
use api\admin\model\WorkModel;
use api\admin\model\UserModel;
use cmf\controller\RestBaseController;

class RuntimeController extends RestBaseController
{
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

        $WorkModel = new WorkModel();
        $liftModel = new LiftModel();
        $notyModel = new NotyModel();

        //遍历所有电梯
        foreach ($lift as $key => $vo) {
            $worker_id = $vo['worker']['id'];
            $lift_id = $vo['id'];
            $gap = $vo['gap'];

            $next_date = strtotime($vo['next_date']);

            //检查是否过期
            while ($currentDate > $next_date) {
                $next_date_before = $next_date - $lift_early * 24 * 60 * 60;
                $next_date_after = $next_date + $lift_late * 24 * 60 * 60;
                //检查过期附近时间有没有维保过
                $findWroks = $WorkModel
                    ->where('create_time', 'between time', [$next_date_before, $next_date_after])
                    ->where('type', 1)
                    ->where('lift_id', $vo['lift_id'])
                    ->where('lift_id', $worker_id)
                    ->find();
                //if ($findWroks === null) {
                //    //没有的话直接做一条维保记录 说明过期没做
                //   $WorkModel->insert([
                //        'lift_id' => $lift_id,
                //        'worker_id' => $worker_id,
                //        'type' => 1,
                //        'status' => 3,
                //        'work_time' => date('Y-m-d', $next_date),
                //        'create_time' => date('Y-m-d H:i:s'),
                //    ]);
                //}
                //if ($gap == 0.5) {
                //    $next_date = strtotime(date('Y-m-d', strtotime('+15 days', $next_date)));
                //} else {
                //    $gap = floor($gap);
                //    $next_date = strtotime(date('Y-m-d', strtotime('+' . $gap . ' month', $next_date)));
                //}
            }

            //更新下电梯的下次维保时间
            //$next_date = date('Y-m-d', $next_date);
            //if ($next_date != $vo['next_date']) {
            //    $liftModel->where('id', $lift_id)->update([
            //        'next_date' => $next_date,
            //        'update_time' => date('Y-m-d H:i:s'),
            //    ]);
            //}
            //$next_date = strtotime($next_date);
            $day_lefts = floor(($next_date - $currentDate) / 86400);

            $last_noty = $notyModel->where('lift_id', $lift_id)->where('user_id', $worker_id)->order('create_time desc')->find();
            if ($last_noty == null) {
                $last_noty_time = null;
            } else {
                $last_noty_time = strtotime($last_noty['create_time']);
            }

            switch ($day_lefts) {
                case 0:
                    if ($last_noty_time === null || $last_noty_time < strtotime('-1 hours')) {
                        $notyModel->insert([
                            'lift_id' => $lift_id,
                            'user_id' => $worker_id,
                            'type' => 1,
                            'status' => 1,
                            'create_time' => date('Y-m-d H:i:s'),
                        ]);
                        $content = '今天必须完成';
                        $this->send_noty($content);
                    }
                    break;
                case 1:
                    if ($last_noty_time === null || $last_noty_time < strtotime('-1 day')) {
                        $notyModel->insert([
                            'lift_id' => $lift_id,
                            'user_id' => $worker_id,
                            'type' => 1,
                            'status' => 1,
                            'create_time' => date('Y-m-d H:i:s'),
                        ]);
                        $content = '还有1天';
                        $this->send_noty($content);
                    }
                    break;
                case 2:
                    if ($last_noty_time === null || $last_noty_time < strtotime('-2 days')) {
                        $notyModel->insert([
                            'lift_id' => $lift_id,
                            'user_id' => $worker_id,
                            'type' => 1,
                            'status' => 1,
                            'create_time' => date('Y-m-d H:i:s'),
                        ]);
                        $content = '还有2天';
                        $this->send_noty($content);
                    }
                    break;
                case 3:
                    if ($last_noty_time === null || $last_noty_time < strtotime('-3 days')) {
                        $notyModel->insert([
                            'lift_id' => $lift_id,
                            'user_id' => $worker_id,
                            'type' => 1,
                            'status' => 1,
                            'create_time' => date('Y-m-d H:i:s'),
                        ]);
                        $content = '还有3天';
                        $this->send_noty($content);
                    }
                    break;
                default:
                    $content = null;
                    break;
            }
        }
        $this->success($lift);
    }

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
        return $this->success('good');
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
     * 微信消息模板API
     * @param string $openid
     * @param string $template_id
     * @param string $url
     * @param $data
     * @param string $topcolor
     * return bool
    */
    private function doWechatNoty($touser, $template_id, $url, $data, $topcolor = '#7B68EE'){
        $lift_settings = cmf_get_option('lift_settings');
        $appId = $lift_settings['appId'];
        $secret = $lift_settings['secret'];
        $url1 = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$appId&secret=$secret";
        $response = cmf_curl_get($url1);
        $response = json_decode($response, true);
        if (empty($response['access_token'])) {
            $this->error('操作失败');
        }
        $accessToken = $response['access_token'];
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
            return true;
        } else {
            return false;
        }
    }

    /**
     * 发送通知（主控）
     * @param int $type
     * @param int $id
     * @param int $userId
     * @param int $days
     * @param bool $callBackend
     */
    private function doNoty($type,$id,$userId,$days,$time,$callBackend){
        //制作content
        switch($type){
            case 1:
                if($days > 0){
                    $content = '您有一个电梯维保还有' . $days . '到期';
                } else if($days == 0){
                    $content = '您有一个电梯维保今天到期';
                } else if($days < 0){
                    $days = -$days;
                    $content = '您有一个电梯维保已经过期'. $days .'天';
                }
                break;
            case 2:
                break;
            default:
        }

        //记录到数据库
        if($type == 1){
            $notyModel = new NotyModel();
            $notyModel->insert([
                'lift_id' => $id,
                'user_id' => $userId,
                'type' => 1,
                'status' => 1,
                'text' => $content,
                'create_time' => date('Y-m-d H:i:s')
            ]);
        }

        //发送socket给用户
        $this->send_noty($content, $userId);
        

        
        
    }

    /**
     * 发送到WORKERMAN
     * @param string $content
     */
    private function send_noty($content,$id){
        $response = cmf_curl_get("http://127.0.0.1:2121/?type=publish&to=$id&content={%22code%22:1,%22msg%22:%22$content%22}");
    }
}
