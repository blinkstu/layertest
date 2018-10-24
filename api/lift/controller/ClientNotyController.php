<?php
namespace api\lift\controller;

use think\Db;
use think\Request;
use api\lift\model\NotyModel;
use cmf\controller\RestUserBaseController;

class ClientNotyController extends RestUserBaseController
{
    /** 
     * noty types:
     * 1.电梯提醒
     * 2.维保确认
     * 
     * status:
     * 1: normal
     * 2: readed
    */
    public function index(){
        if($this->request->isPost()){
            $params = $this->request->param();
            $page = $params['page'];
            $user_id = $this->getUserId();
            $notyModel = new NotyModel;
            $noty = $notyModel->where('user_id',$user_id)->page($page, 12)->order('create_time desc')->select();

            $this->success('good',$noty);
        }
    }

    public function notyDetails(){
        if($this->request->isPost()){
            $params = $this->request->param();
            $id = $params['id'];
            $notyModel = new NotyModel;
            $noty = $notyModel->find($id);
            $this->success('good', $noty);
        }
    }

    public function clickNoty(){
        $params = $this->request->param();
        if(empty($params['type']) || empty($params['id']) ){
            $this->error('缺少参数');
        }
        $type = $params['type'];
        $id = $params['id'];
        $oid = $params['oid'];
        if($id != 0){
            $notyModel = new NotyModel;
            $noty = $notyModel->update([
                'id' => $id,
                'status' => 2
            ]);
        }else{
            $this->error('测试信息'); 
        }

        $this->success('good');
    }
}