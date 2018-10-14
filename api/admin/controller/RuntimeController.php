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


    private function send_noty($content){
        $response = cmf_curl_get("http://127.0.0.1:2121/?type=publish&to=1&content={%22code%22:1,%22msg%22:%22$content%22}");
    }
}
