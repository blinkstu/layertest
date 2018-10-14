<?php
namespace api\lift\controller;

use think\Db;
use think\Request;
use cmf\controller\RestUserBaseController;
use api\lift\model\WorkModel;

class ClientWorkController extends RestUserBaseController
{
    public function WorkRequest(){
        if($this->request->isPost()){
            $userId = $this->getUserId();
            $workModel = new WorkModel;
            $works = $workModel->where('customer_id',$userId)->with('lift')->order('create_time desc')->select();
            foreach ($works as $key => $item) {
                $works[$key]['work_time'] = date('Y-m-d H:m:s', $works[$key]['work_time']);
                $works[$key]['lift']['map_address'] = mb_substr($works[$key]['lift']['map_address'], -15);
            }
            $this->success('good',$works);
        }
    }

    public function WorkDetails(){
        if($this->request->isPost()){
            $params = $this->request->param();
            $id = $params['id'];
            $workModel = new WorkModel;
            $work = $workModel->with('lift')->find($id);
            $work['domain'] = $this->request->domain();
            $this->success('good',$work);
        }
    }

    public function confirmWork(){
        if ($this->request->isPost()) {
            $params = $this->request->param();
            $id = $params['id'];
            $workModel = new WorkModel;
            $work = $workModel->find($id);
            $lift_id = $work['lift_id'];
            $userId = $this->getUserId();
            if($work['customer_id'] != $userId ){
                $this->error('出现错误');
            }
            $work = $workModel->update([
                'id' => $id,
                'status' => 1
            ]);
            Db::name('lift')->where('id',$lift_id)->update(['status'=>0]);
            $this->success('good', $work);
        }
    }
}