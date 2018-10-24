<?php
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

class NotyController extends AdminBaseController
{
    public function index()
    {
        return $this->fetch();
    }

    public function fetchData()
    {
        $request = input('request.');
        $page = $request['page'];
        $limit = $request['limit'];
        $that = $this;
        $id = 1;
        //$data = Db::name('noty')->where('user_id', $id)->select();
        $count = Db::name('noty')->where('user_id',1)->select();
        $data = Db::name('noty')->where('user_id', 1)->page($page . ',' . $limit)->order('create_time desc')->select()->toArray();
        foreach($data as $key => $item ){
            if ($item['type'] == 1) {
                $lift = Db::name('lift')->where('id', $item['lift_id'])->find();
                $contract_id = $lift['contract_id'];
            } else {
                $contract_id = '';
            }
            $data[$key]['contract_id'] = $contract_id;
        }
        $result = [
            'code' => 0,
            'msg' => '',
            'count' => count($count),
            'data' => $data,
        ];

        return json($result);
    }

    public function delPost(){
        if($this->request->isPost()){
            $request = input('request.');
            $data = (array)$request['data'];
            if(count($data) == 0){
                $this->error('需要选择至少一项');
            }
            foreach($data as $item){
                $id = $item['id'];
                Db::name('noty')->where('id',$id)->delete();
            }
            $this->success('操作成功');
        }
    }

    public function readPost()
    {
        if ($this->request->isPost()) {
            $request = input('request.');
            $data = (array)$request['data'];
            if (count($data) == 0) {
                $this->error('需要选择至少一项');
            }
            foreach ($data as $item) {
                $id = $item['id'];
                Db::name('noty')->where('id', $id)->update(['status'=>2]);

            }
            $this->success('操作成功');
        }
    }
}
