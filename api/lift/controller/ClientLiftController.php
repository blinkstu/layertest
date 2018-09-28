<?php
namespace api\lift\controller;

use cmf\controller\RestUserBaseController;

use think\Db;
use think\Request;
use api\admin\model\LiftModel;

class ClientLiftController extends RestUserBaseController
{
    public function index(){
        $userId = $this->getUserId();
        $lift = new LiftModel;
        $lift = $lift->where('worker_id',$userId)->select();
        $lift = $lift->toArray();
        foreach( $lift as $key => $item){
            $lift[$key]['map_address'] = mb_substr($lift[$key]['map_address'], -15);
        }
        $this->success('good',$lift);
    }

    public function liftDetails(){
        $params = $this->request->param();
        $id = $params['id'];
        $lift = new LiftModel;
        $lift = $lift->find($id);
        $this->success('good',$lift);
    }
}
