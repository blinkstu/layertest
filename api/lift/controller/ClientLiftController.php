<?php
namespace api\lift\controller;

use api\admin\model\LiftModel;
use api\lift\model\WorkModel;
use api\lift\model\UserModel;
use api\lift\model\ContractModel;
use cmf\controller\RestUserBaseController;
use think\Request;
use think\Db;

class ClientLiftController extends RestUserBaseController
{
    private function checklift(){
        //进行一次查询
        $lift_settings = cmf_get_option('lift_settings');
        $maintain_time = $lift_settings['maintain_time'];
        $currentTimestamp = time();
        $LiftModel = new LiftModel();
        $UserModel = new UserModel();
        $users = $UserModel::hasWhere('role', ['role_id' => '4'])->where('user_type=1')->select();
        //遍历所有工作人员
        foreach ($users as $user) {
            $userId = $user['id'];
            //遍历该员工下所有电梯
            $lifts = $LiftModel->where('worker_id', $userId)->where('status', 1)->select();
            $tasks = $LiftModel->where('worker_id', $userId)->where('status', 2)->order('update_time desc')->select();

            //查看队列中的项目是否完成
            if (count($tasks)) {
                foreach ($tasks as $task) {
                    if ($task['maintain_end'] <= $currentTimestamp) {
                        $LiftModel->update(['id' => $task['id'], 'status' => 3]);
                    }
                }
            }

            // 启动队列中任意一个电梯
            if (!count($tasks) && count($lifts)) {
                $LiftModel->where('worker_id', $userId)->where('status', 1)->update(['status' => 2, 'maintain_end' => strtotime('+ ' . $maintain_time . ' minutes')]);
            }
        }
    }

    public function index()
    {
        $this->checklift();
        $userId = $this->getUserId();
        $lift = new LiftModel;
        $lift = $lift->where('worker_id', $userId)->order('status desc')->order('next_date asc')->select();
        $lift = $lift->toArray();
        foreach ($lift as $key => $item) {
            $future = strtotime($lift[$key]['next_date']);
            $time = time();
            $days = round(($future - time()) / 86400) + 1;
            if ($days > 0) {
                $days_str = '剩余' . $days . '天';
                if ($days >= 3) {
                    $days_class = "green";
                } else if ($days == 2) {
                    $days_class = "yellow";
                } else {
                    $days_class = "red";
                }
            } else if ($days == 0) {
                $days_str = '剩余0天';
                $days_class = "red";
            } else {
                $days = -$days;
                $days_str = '超时' . $days . '天';
                $days_class = "red";
            }
            $lift[$key]['days_str'] = $days_str;
            $lift[$key]['days_class'] = $days_class;
            $lift[$key]['days_left'] = $days;
            $lift[$key]['map_address'] = mb_substr($lift[$key]['map_address'], -15);
        }
        $this->success('good', $lift);
    }

    public function liftDetails()
    {
        $this->checklift();
        $params = $this->request->param();
        $id = $params['id'];
        $lift = new LiftModel;
        $lift = $lift->find($id);
        $userId = $this->getUserId();

        $lift_settings = cmf_get_option('lift_settings');
        $lift_distance = $lift_settings['lift_distence'];

        if ($lift['worker_id'] != $userId) {
            //工作人员ID不对应
            $this->error('操作失败');
        }

        $contractId = $lift['contract_id'];
        $contractModel = new ContractModel;
        $contract = $contractModel->where('id',$contractId)->with('user')->find();

        $lift['contract']=$contract;

        $future = strtotime($lift['next_date']);
        $time = time();
        $days = round(($future - time()) / 86400) + 1;
        if ($days > 0) {
            $days_str = '剩余' . $days . '天';
            if ($days >= 3) {
                $days_class = "green";
            } else if ($days == 2) {
                $days_class = "yellow";
            } else {
                $days_class = "red";
            }
        } else if ($days == 0) {
            $days_str = '剩余0天';
            $days_class = "red";
        } else {
            $days = -$days;
            $days_str = '超时' . $days . '天';
            $days_class = "red";
        }
        $lift['days_str'] = $days_str;
        $lift['days_class'] = $days_class;
        $lift['days_left'] = $days;
        $lift['lift_distance'] = $lift_distance;

        $this->success('good', $lift);
    }

    public function startMaintainPost()
    {
        if ($this->request->isPost()) {
            //获取用户设置项目
            $lift_settings = cmf_get_option('lift_settings');
            $lift_early = $lift_settings['lift_early'];
            $maintain_time = $lift_settings['maintain_time'];
            $params = $this->request->param();
            $id = $params['id'];
            $liftModel = new LiftModel;
            $lift = $liftModel->find($id);
            $userId = $this->getUserId();
            if ($lift['worker_id'] != $userId) {
                $this->error('id不对应');
            }

            $before_timestamp = strtotime($lift['next_date']) - $lift_early * 24 * 60 * 60;
            $currentTime = time();
            if ($before_timestamp > $currentTime) {
                $this->error('距离下次保养日期最多提前' . $lift_early . '天');
            }

            //设置状态为正在队列中
            if ($lift['status'] == 0) {
                $liftModel->where('id', $id)->update(['status' => 1]);
            } else {
                $this->error('无法启动维保');
            }
            //立即开始检查一次员工
            $LiftModel = new LiftModel;
            $lifts = $LiftModel->where('worker_id', $userId)->where('status', 1)->select();
            $tasks = $LiftModel->where('worker_id', $userId)->where('status', 2)->order('update_time desc')->select();
            //变量为空的时候
            if (!count($lifts)) {
                $this->error('无法启动维保');
            }

            if (!count($tasks)) {
                foreach ($lifts as $key => $item) {
                    $lift = $LiftModel->where('worker_id', $userId)->where('status', 1)->order('update_time desc')->find();
                    $liftModel->update(['id' => $item['id'], 'status' => 2, 'maintain_end' => strtotime('+ ' . $maintain_time . ' minutes')]);
                }
            }

            $this->success('good');
        }
    }

    public function cancelMaintainPost()
    {
        if ($this->request->isPost()) {
            $params = $this->request->param();
            $id = $params['id'];
            $LiftModel = new LiftModel;
            $lift = $LiftModel->find($id);
            $userId = $this->getUserId();
            if ($lift['worker_id'] != $userId) {
                $this->error('id不对应');
            }
            if ($lift['status'] == 1 || $lift['status'] == 2) {
                $LiftModel->where('id', $id)->update(['status' => 0]);
                $this->success('操作成功');
            } else {
                $this->error('无法取消');
            }
        }
    }

    public function maintainReport()
    {
        if ($this->request->isPost()) {
            $userId = $this->getUserId();
            $params = $this->request->param();
            $id = $params['id'];
            // 获取表单上传文件 例如上传了001.jpg
            $file = request()->file('image');
            $imageUrl = '';

            // 移动到框架应用根目录/public/uploads/ 目录下
            if ($file) {
                $dir = ROOT_PATH . 'public/' . DS . 'upload/' . $this->request->module() . '/';
                if (!file_exists($dir)) {
                    mkdir($dir);
                }
                $info = $file->move($dir);
                if ($info) {
                    $imageUrl = 'upload/' . $this->request->module() . '/' . $info->getSaveName();
                } else {
                    $this->error('图片上传失败');
                }
            }

            $liftModel = new LiftModel();
            $lift = $liftModel->where('id', $id)->find();
            if ($lift['status'] != 3) {
                $this->error('提交失败');
            }

            $contract_id = $lift['contract_id'];
            $contract = Db::name('contract')->find($contract_id);
            $customer_id = $contract['customer_id'];
            $workModel = new WorkModel;
            $data = [
                'lift_id' => $lift['id'],
                'worker_id'     => $userId,
                'type'          => 1, //1维保 2紧急维修
                'status'        => 0, //0客户未确认 1客户已经确认
                'work_time'     => $lift['maintain_end'],
                'maintain_time' => $lift['maintain_times'] + 1,
                'image'         => $imageUrl,
                'create_time'   => date('Y-m-d H:m:s'),
                'note'          => $params['note'],
                'customer_id'   => $customer_id
            ];
            $workModel->insert($data);


            $liftModel->where('id',$id)->update(['status'=>4]);
            $maintain_times = $lift['maintain_times'];
            $next_date = strtotime(date("Y-m-d"));
            switch($maintain_times){
                case 0://第一次保养
                    $next_date = strtotime(date('Y-m-d', strtotime('+1 month', $next_date)));
                break;
                case 1://第二次保养
                    $next_date = strtotime(date('Y-m-d', strtotime('+3 month', $next_date)));
                break;
                case 2://第三次保养
                    $next_date = strtotime(date('Y-m-d', strtotime('+1 year', $next_date)));
                break;
                default://第四次保养
                    $next_date = strtotime(date('Y-m-d', strtotime('+1 year', $next_date)));
            }
            $next_date = date('Y-m-d',$next_date);
            $liftModel->where('id', $id)->update(['status' => 4,'next_date'=>$next_date,'maintain_times'=>$maintain_times+1]);

            $this->success('good');

        }
    }

}
