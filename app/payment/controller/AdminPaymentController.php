<?php
namespace app\payment\controller;

use app\payment\model\ContractModel;
use app\payment\model\PaymentModel;
use cmf\controller\AdminBaseController;
use think\Controller;

class AdminPaymentController extends AdminBaseController
{
    public function contractPayment()
    {
        $params = $this->request->param();
        $id = $params['contract_id'];
        $contract = new ContractModel;
        $contract = $contract->find($id);
        $this->assign('contract', $contract);

        return $this->fetch('contractPayment');
    }

    public function contractPaymentFetchData()
    {
        $params = $this->request->param();
        $request = input('request.');
        $page = $request['page'];
        $limit = $request['limit'];
        $id = $params['contract_id'];

        $payment = new PaymentModel;
        $count = $payment->where('contractId=' . $id)->order('payTime', 'desc')->select();
        $data = $payment->where('contractId=' . $id)->order('payTime','desc')->page($page.','.$limit)->select();
        $result = [
            'code' => 0,
            'msg' => '',
            'count' => count($count),
            'data' => $data,
        ];
        return json($result);
    }

    public function addPayment()
    {
        $params = $this->request->param();
        $contract_id = $params['contract_id'];
        $contract = new ContractModel;
        $contract = $contract->with('party')->find($contract_id);
        $this->assign('contract', $contract);
        return $this->fetch('addPayment');
    }

    public function addPaymentPost()
    {
        if ($this->request->isPost()) {
            if (empty($_POST['photos'])) {
                $photos = [];
            } else {
                $photos = $_POST['photos'];
            }
            $photos_json = json_encode($photos);
            $payment = new PaymentModel();

            $_POST['totalAmount'] = str_replace(',', '', $_POST['totalAmount']);
            
            $data = [
                'contractId' => $_POST['contractId'],
                'paymentType' => $_POST['paymentType'],
                'payer' => $_POST['payer'],
                'payee' => $_POST['payee'],
                'payTime' => $_POST['payTime'],
                'billType' => $_POST['billType'],
                'totalAmount' => $_POST['totalAmount'],
                'taxRate' => $_POST['taxRate'],
                'taxAmount' => $_POST['taxAmount'],
                'paymentNote' => $_POST['paymentNote'],
                'paymentContent' => $_POST['paymentContent'],
                'paymentScaned' => $photos_json,
            ];
            $res = $payment->insert($data);

            $this->success('成功', null, $_POST);
        }
    }

    public function editPayment()
    {
        $params = $this->request->param();
        $id = $params['id'];
        $payment = new PaymentModel;
        $payment = $payment->find($id);
        $photos = json_decode($payment['paymentScaned'], true);
        if ($photos == null) {$photos = [];}
        $photos_num = count($photos);
        $this->assign('photos', $photos);
        $this->assign('photos_num', $photos_num);
        $this->assign('payment', $payment);
        return $this->fetch('editPayment');
    }

    public function editPaymentPost () 
    {
        if ($this->request->isPost()) {

                
                if(empty($_POST['photos'])){
                    $photos = [];
                }else{
                    $photos      = $_POST['photos'];
                }
                $photos_json = json_encode($photos);

                //写入信息
                $payment = new PaymentModel();
                $payment = $payment->find($_POST['id']);

                $_POST['totalAmount'] = str_replace(',', '', $_POST['totalAmount']);

                $data = [
                    'paymentType' => $_POST['paymentType'],
                    'payer' => $_POST['payer'],
                    'payee' => $_POST['payee'],
                    'payTime' => $_POST['payTime'],
                    'billType' => $_POST['billType'],
                    'totalAmount' => $_POST['totalAmount'],
                    'taxRate' => $_POST['taxRate'],
                    'taxAmount' => $_POST['taxAmount'],
                    'paymentNote' => $_POST['paymentNote'],
                    'paymentContent' => $_POST['paymentContent'],
                    'paymentScaned' => $photos_json,
                ];
                $result = $payment->save($data);

                if ($result !== false) {
                    $this->success("保存成功！");
                } else {
                    $this->error("保存失败！");
                }
        }
    }

    public function delReplacementPost() {
        $params = $this->request->param();
        $id = $params['id'];
        $payment = new PaymentModel;
        $payment = $payment->find($id)->delete();
        $this->success('删除成功');
    }
}
