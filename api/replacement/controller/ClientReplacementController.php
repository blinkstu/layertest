<?php
namespace api\replacement\controller;

use cmf\controller\RestUserBaseController;

use think\Request;
use think\Db;
use api\replacement\model\ReplacementModel;

class ClientReplacementController extends RestUserBaseController
{
    public function index(){
        $request = $this->request->param();
        $id = $request['id'];
        $replacement = new ReplacementModel;

        $replacement = $replacement->where('contract_id',$id)->select();
        $this->success('good',$replacement);

    }
}
