<?php 
namespace app\contract\controller;

use cmf\controller\AdminBaseController;
use think\Controller;
use think\Db;
use think\Validate;
use app\contract\model\ContractModel;
use app\contract\model\ContractPartyModel;

class AdminContractController extends AdminBaseController
{
    //首页HTML
    public function index(){
        
        return $this->fetch();
    }

    //录入合同页面HTML
    public function addContract(){ 
        return $this->fetch();
    }

    //合同表格DATA
    public function fetchContractData(){
        $request = input('request.');
        $page    = $request['page'];
        $limit   = $request['limit'];

        $contract = new ContractModel(); 
        $count = $contract->order("id DESC")->select();
        $list = $contract->order("id DESC")->with('party')->with('customer')->page($page.','.$limit)->select();

        $result = [
            'code' => 0,
            'msg'  => '',
            'count'=> count($count),
            'data' => $list
        ];
        return json($result);
    }

    //录入合同POST
    public function addContractPost(){
        if ($this->request->isPost()) {
            $validate = new Validate([
                'contract_name'     => 'require|max:10',
                'contract_id'       => 'require|unique:contract,contract_id',
                'total_amount'      => 'require',
                'start_date'        => 'require',
                'end_date'          => 'require',
                'a_name'            => 'require',
                'b_name'            => 'require',
                'customer_id'       => 'require'
            ],[
                'contract_name'         => '请填写合同名称',
                'contract_id'           => '请填写合同编号',
                'contract_id.unique'    => '合同编号已存在',
                'total_amount'          => '请填写合同总金额',
                'start_date'            => '请填写合同开始时间',
                'end_date'              => '请填写合同到期时间',
                'a_name'                => '请填写甲方名称',
                'b_name'                => '请填写乙方名称',
                'customer_id'           => '请选择客户账号'
            ]);

            $result = $validate->check($_POST);
            if ($result !== true) {
                $this->error($validate->getError());
            } else {
                if(empty($_POST['status'])){$_POST['status'] = 0;}

                if(empty($_POST['photos'])){
                    $photos = [];
                }else{
                    $photos      = $_POST['photos'];
                }

                $photos_json = json_encode($photos);
                $_POST['total_amount'] = str_replace(',', '', $_POST['total_amount']);
                //写入甲乙方信息
                $party = new ContractPartyModel();
                $data = [
                    'a_name'        => $_POST['a_name'],
                    'a_address'     => $_POST['a_address'],
                    'a_represent'   => $_POST['a_represent'],
                    'a_tel'         => $_POST['a_tel'],
                    'b_name'        => $_POST['b_name'],
                    'b_address'     => $_POST['b_address'],
                    'b_represent'   => $_POST['b_represent'],
                    'b_tel'         => $_POST['b_tel'],
                    'create_time'   => time()
                    ];
                $res = $party->insertGetId($data);

                //写入基本信息
                $contract = new ContractModel();
                $data = [
                    'contract_name' => $_POST['contract_name'],
                    'contract_id'   => $_POST['contract_id'],
                    'total_amount'  => $_POST['total_amount'],
                    'start_date'    => $_POST['start_date'],
                    'end_date'      => $_POST['end_date'],
                    'type'          => $_POST['type'],
                    'status'        => $_POST['status'],
                    'notes'         => $_POST['notes'],
                    'party_info_id' => $res,
                    'photos'        => $photos_json,
                    'customer_id'   => $_POST['customer_id']
                ];
                $res = $contract->insert($data);

                

                $this->success('成功',null,$_POST);
            }

        }
    }

    //编辑合同
    public function editContract(){
        $params = $this->request->param();
        $contract = new ContractModel();
        $contract = $contract->with('party,customer')->find($params['contract_id']);
        $photos = json_decode($contract['photos'],true);
        if ($photos == null){
            $photos = [];
        }
        $photos_num = count($photos);
        $this->assign('photos',$photos);
        $this->assign('photos_num',$photos_num);
        $this->assign('data',$contract);
        return $this->fetch();
    }

    public function editContractPost(){
        if ($this->request->isPost()) {
            $validate = new Validate([
                'id'                => 'require',
                'contract_name'     => 'require|max:10',
                'contract_id'       => 'require|unique:contract,contract_id',
                'total_amount'      => 'require',
                'start_date'        => 'require',
                'end_date'          => 'require',
                'a_name'            => 'require',
                'b_name'            => 'require',
                'customer_id'       => 'require'
            ],[
                'id'                    => '请输入ID',
                'contract_name'         => '请填写合同名称',
                'contract_id'           => '请填写合同编号',
                'contract_id.unique'    => '合同编号已存在',
                'total_amount'          => '请填写合同总金额',
                'start_date'            => '请填写合同开始时间',
                'end_date'              => '请填写合同到期时间',
                'a_name'                => '请填写甲方名称',
                'b_name'                => '请填写乙方名称',
                'customer_id'           => '请选择客户账号'
            ]);

            $result = $validate->check($_POST);
            if ($result !== true) {
                $this->error($validate->getError());
            } else {
                if(empty($_POST['status'])){$_POST['status'] = 0;}

                
                if(empty($_POST['photos'])){
                    $photos = [];
                }else{
                    $photos      = $_POST['photos'];
                }
                $photos_json = json_encode($photos);
                $_POST['total_amount'] = str_replace(',', '', $_POST['total_amount']);
                //写入信息
                $contract = new ContractModel();
                $contract = $contract->find($_POST['id']);

                $contract->contract_name        = $_POST['contract_name'];
                $contract->contract_id          = $_POST['contract_id'];
                $contract->total_amount         = $_POST['total_amount'];
                $contract->start_date           = $_POST['start_date'];
                $contract->end_date             = $_POST['end_date'];
                $contract->type                 = $_POST['type'];
                $contract->status               = $_POST['status'];
                $contract->notes                = $_POST['notes'];
                $contract->customer_id          = $_POST['customer_id'];
                $contract->photos               = $photos_json;
                $contract->party->a_name        = $_POST['a_name'];
                $contract->party->a_address     = $_POST['a_address'];
                $contract->party->a_represent   = $_POST['a_represent'];
                $contract->party->a_tel         = $_POST['a_tel'];
                $contract->party->b_name        = $_POST['b_name'];
                $contract->party->b_address     = $_POST['b_address'];
                $contract->party->b_represent   = $_POST['b_represent'];
                $contract->party->b_tel         = $_POST['b_tel'];
                $result = $contract->together('party')->isAutoWriteTimestamp(false)->save();

                if ($result !== false) {
                    $this->success("保存成功！");
                } else {
                    $this->error("保存失败！");
                }
            }
        }
    }

    //删除采购人员POST
    public function delContractPost(){
        $contract = new ContractModel();
        $contract = $contract->where('id='.$_POST['id'])->find();
        $res = $contract->together('party')->delete();
        $this->success('删除成功');
    }
}