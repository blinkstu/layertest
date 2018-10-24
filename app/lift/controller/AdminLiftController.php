<?php
namespace app\lift\controller;

use app\lift\model\ContractModel;
use app\lift\model\LiftModel;
use app\lift\model\UserModel;
use app\lift\model\WorkModel;
use cmf\controller\AdminBaseController;
use think\Validate;

class AdminLiftController extends AdminBaseController
{
    public function index()
    {
        $params = $this->request->param();
        $id = $params['contract_id'];
        $contract = new ContractModel;
        $contract = $contract->find($id);
        $this->assign('contract', $contract);

        return $this->fetch('contractLift');
    }

    public function addLift()
    {
        $params = $this->request->param();
        $contract_id = $params['contract_id'];

        $userModel = new UserModel();
        $staffs = $userModel::hasWhere('role', ['role_id' => '4'])->with('role.roleinfo,desc,gotLifts')->select();
        $this->assign('staffs', $staffs);
        return $this->fetch('addLift');
    }

    public function contractLiftFetchData()
    {
        $params = $this->request->param();
        $request = input('request.');
        $page = $request['page'];
        $limit = $request['limit'];
        $contract_id = $params['contract_id'];

        $liftModel = new LiftModel();
        $count = $liftModel->with('worker')->where('contract_id=' . $contract_id)->order('create_time', 'desc')->select();
        $data = $liftModel->with('worker')->where('contract_id=' . $contract_id)->order('create_time', 'desc')->page($page.','.$limit)->select();
        $result = [
            'code' => 0,
            'msg' => '',
            'count' => count($count),
            'data' => $data,
        ];
        
        return json($result);
    }

    public function addLiftPost()
    {
        if ($this->request->isPost()) {
            $validate = new Validate([
                'contract_id' => 'require',
                'worker_id' => 'require',
                'lift_id' => 'require',
                'address_name' => 'require',
                'next_date' => 'require|date',
                'addLng' => 'require',
            ]);

            $validate->message([
                'contract_id.require' => '缺少参数!',
                'worker_id.require' => '请选择员工',
                'lift_id.require' => '请输入电梯编号!',
                'address_name.require' => '请选择地理位置',
                'next_date' => '下次保养日期错误',
                'addLng.require' => '请选择地理位置',
            ]);

            $params = $this->request->param();
            if (!$validate->check($params)) {
                $this->error($validate->getError());
            }

            if (strtotime($params['next_date']) < strtotime('now')) {
                $this->error('下次保养时间错误');
            }

            $contract_id = $params['contract_id'];
            $worker_id = $params['worker_id'];
            $liftModel = new LiftModel();
            $data = [
                'contract_id' => $_POST['contract_id'],
                'worker_id' => $_POST['worker_id'],
                'lift_id' => $_POST['lift_id'],
                'lift_model' => $_POST['lift_model'],
                'lift_belongs' => $_POST['lift_belongs'],
                'lift_desc' => $_POST['lift_desc'],
                'first_date' => $_POST['next_date'],
                'map_lng' => $_POST['addLng'],
                'map_lat' => $_POST['addLat'],
                'map_address' => $_POST['address_name'],
                'last_date' => '',
                //'gap' => $_POST['gap'],
                'next_date' => $_POST['next_date'],
                'create_time' => date("Y-m-d H:i:s"),
            ];
            $res = $liftModel->insert($data);

            $this->success('成功');
        }
    }

    public function editLift()
    {
        $params = $this->request->param();
        $id = $params['id'];

        //获取电梯
        $liftModel = new LiftModel;
        $lift = $liftModel->with('worker')->find($id);
        if ($lift['edit_log']) {
            $edit_log = json_decode($lift['edit_log'], true);
            $edit_log = array_reverse($edit_log);
            $this->assign('edit_log', $edit_log);
        }
        $this->assign('lift', $lift);

        //获取员工
        $userModel = new UserModel();
        $staffs = $userModel::hasWhere('role', ['role_id' => '4'])->with('role.roleinfo,desc,gotLifts')->select();
        $this->assign('staffs', $staffs);

        //dump($lift);
        return $this->fetch('editLift');
    }

    public function editLiftPost()
    {
        if ($this->request->isPost()) {
            $validate = new Validate([
                'id' => 'require',
                'contract_id' => 'require',
                'worker_id' => 'require',
                'lift_id' => 'require',
                'address_name' => 'require',
                'next_date' => 'date',
                'addLng' => 'require',
            ]);

            $validate->message([
                'id.require' => '缺少参数！',
                'contract_id.require' => '缺少参数!',
                'worker_id.require' => '请选择员工',
                'lift_id.require' => '请输入电梯编号!',
                'address_name.require' => '请选择地理位置',
                'next_date' => '下次保养日期错误',
                'addLng.require' => '请选择地理位置',
            ]);

            $params = $this->request->param();
            if (!$validate->check($params)) {
                $this->error($validate->getError());
            }

            $contract_id = $params['contract_id'];
            $worker_id = $params['worker_id'];
            $liftModel = new LiftModel();

            $lift = $liftModel->find($params['id']);
            if (!$lift['edit_log']) {
                $edit_log = array();
            } else {
                $edit_log = json_decode($lift['edit_log'], true);
            }
            //if ($_POST['gap'] != $lift['gap'] || $_POST['next_date'] != $lift['next_date']) {
            if ($_POST['next_date'] != $lift['next_date']) {
                if (strtotime($params['next_date']) < strtotime(date('Y-m-d'))) {
                    $this->error('下次保养时间应该大于今天');
                }
                /* if ($_POST['gap'] != $lift['gap']) {
                    $edit_log[] = ['date' => date("Y-m-d H:i:s"), 'log' => '间隔由' . $lift['gap'] . '个月修改为' . $_POST['gap'] . '个月'];
                } */
                if ($_POST['next_date'] != $lift['next_date']) {
                    $edit_log[] = ['date' => date("Y-m-d H:i:s"), 'log' => '下次保养日期由' . $lift['next_date'] . '修改为' . $_POST['next_date']];
                }
            }
            $edit_log_json = json_encode($edit_log);

            $data = [
                'worker_id' => $_POST['worker_id'],
                'lift_id' => $_POST['lift_id'],
                'lift_model' => $_POST['lift_model'],
                'lift_belongs' => $_POST['lift_belongs'],
                'lift_desc' => $_POST['lift_desc'],
                'map_lng' => $_POST['addLng'],
                'map_lat' => $_POST['addLat'],
                'map_address' => $_POST['address_name'],
                'last_date' => '',
                //'gap' => $_POST['gap'],
                'next_date' => $_POST['next_date'],
                'update_time' => date("Y-m-d H:i:s"),
                'edit_log' => $edit_log_json,
            ];
            $res = $liftModel->where('id', $params['id'])->update($data);

            $this->success('成功');
        }
    }

    public function delLiftPost()
    {
        $params = $this->request->param();
        $id = $params['id'];
        $lift = new LiftModel;
        $lift = $lift->find($id)->delete();
        $this->success('删除成功');
    } 

    public function liftWorkLogFetch()
    {
        $params = $this->request->param();
        $lift_id = $params['lift_id'];
        $page    = $params['page'];
        $limit   = $params['limit'];

        $liftModel = new LiftModel;
        $lift = $liftModel->with('worker')->where('id', $lift_id)->find();

        $WorkModel = new WorkModel;
        $count = $WorkModel->where('lift_id', $lift_id)->select();
        $data = $WorkModel->where('lift_id', $lift_id)->with('worker')->order('work_time desc')->page($page.','.$limit)->select();
        $data = $data->toArray();

        //array_push($data,[
        //    'id' => 1,
        //    'type'  => 1,
        //    'status'   =>2,
        //    'user_nickname' => $lift['user_nickname'],
        //    'work_time' => $lift['next_date']
        //]);

        $result = [
            'code' => 0,
            'msg' => '',
            'count' => count($count),
            'data' => $data,
        ];
        return json($result);
    }

    public function liftWorkAdd()
    {
        $userModel = new UserModel;
        $workers = $userModel::hasWhere('role', ['role_id' => '4'])->with('role.roleinfo,desc,gotLifts')->select()->toArray();
        $this->assign('workers',$workers);
        return $this->fetch('addWork');
    }

    public function liftWorkEdit()
    {
        $param = $this->request->param();
        $id = $param['id'];
        $work = new WorkModel();
        $work = $work->where('id',$id)->with('worker')->find();

        $this->assign('work',$work);
        return $this->fetch('editWork');
    }

    public function liftWorkAddPost()
    {
        if ($this->request->isPost()) {
            $validate = new Validate([
                'worker_id' => 'require',
                'lift_id' => 'require',
                'finish_date' => 'require',
                'desc'  => 'require'
            ]);

            $validate->message([    
                'worker_id.require' => '请选择员工',
                'lift_id.require' => '请输入电梯编号!',
                'finish_date.require'   => '请输入完成时间',
                'desc.require'  => '请输入任务描述'
            ]);

            $params = $this->request->param();
            if (!$validate->check($params)) {
                $this->error($validate->getError());
            }

            $worker_id = $params['worker_id'];
            $lift_id = $params['lift_id'];

            $WorkModel = new WorkModel;
            $WorkModel->insert([
                'lift_id' => $lift_id,
                'type'  => 2,
                'status'    => 4,
                'worker_id' => $params['worker_id'],
                'work_time' => $params['finish_date'],
                'create_time'  => date('Y-m-d H:i:s'),
                'note'  => $params['desc']
            ]);



            $this->success('成功');
        }
    }

    public function delWorkPost(){
        if($this->request->isPost()){
            $params = $this->request->param();
            $id = $params['id'];
            $work = new WorkModel;
            $work = $work->find($id)->delete();
            $this->success('删除成功');
        }
    }

    public function doneWorkPost(){
        if($this->request->isPost()){
            $params = $this->request->param();
            $id = $params['id'];
            $work = new WorkModel;
            $work = $work->where('id',$params['id'])->update(['status' => 1]);
            $this->success('设定成功');
        }
    }

    public function editWorkPost(){
        if($this->request->isPost()){
            $params = $this->request->param();
            $id = $params['id'];
            $work = new WorkModel();
            $work = $work->where('id',$id)->update([
                'work_time' => $params['finish_date'],
                'note'  => $params['desc']
            ]);
            $this->success('保存成功');
        }
    }

    public function notdoneWorkPost(){
        if($this->request->isPost()){
            $params = $this->request->param();
            $id = $params['id'];
            $work = new WorkModel;
            $work = $work->where('id',$params['id'])->update(['status' => 4]);
            $this->success('设定成功');
        }
    }


}
