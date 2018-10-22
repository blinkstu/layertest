<?php
namespace app\replacement\controller;

use app\replacement\model\ContractModel;
use app\replacement\model\ReplacementModel;
use cmf\controller\AdminBaseController;

class AdminReplacementController extends AdminBaseController
{

    public function index()
    {
        $params = $this->request->param();
        $id = $params['contract_id'];
        $contract = new ContractModel;
        $contract = $contract->find($id);
        $this->assign('contract', $contract);
        return $this->fetch('contractReplacement');
    }

    public function addReplacement()
    {
        return $this->fetch('addReplacement');
    }

    public function replacementFetch()
    {
        $params = $this->request->param();
        $request = input('request.');
        $page = $request['page'];
        $limit = $request['limit'];
        $id = $params['contract_id'];

        $payment = new ReplacementModel;
        $count = $payment->where('contract_id=' . $id)->order('create_time', 'desc')->select();
        $data = $payment->where('contract_id=' . $id)->order('create_time', 'desc')->page($page.','.$limit)->select();
        $result = [
            'code' => 0,
            'msg' => '',
            'count' => count($count),
            'data' => $data,
        ];
        return json($result);
    }

    public function addReplacementPost()
    {
        if ($this->request->isPost()) {
            if (empty($_POST['photos'])) {
                $photos = [];
            } else {
                $photos = $_POST['photos'];
            }
            $photos_json = json_encode($photos);
            $total_amount = preg_replace("/^[^\d.]+$/", '', $_POST['price']);

            $data = [
                'contract_id' => $_POST['contract_id'],
                'title' => $_POST['title'],
                'model' => $_POST['model'],
                'note' => $_POST['note'],
                'num' => $_POST['num'],
                'status' => 1,
                'price' => $total_amount,
                'photos' => $photos_json,
                'create_time' => date("Y-m-d H:i:s"),
            ];

            $replacement = new ReplacementModel;
            $replacement->insert($data);

            return $this->success('添加成功');
        }
    }

    public function editReplacement()
    {
        $params = $this->request->param();
        $id = $params['id'];

        $replacement = new ReplacementModel;
        $replacement = $replacement->find($id);
        $replacement = $replacement->toArray();
        $photos = json_decode($replacement['photos'], true);
        if ($photos == null) {$photos = [];}
        $photos_num = count($photos);
        $this->assign('photos', $photos);
        $this->assign('photos_num', $photos_num);
        $this->assign('replacement', $replacement);

        return $this->fetch('editReplacement');
    }

    public function editReplacementPost()
    {
        if ($this->request->isPost()) {

            if (empty($_POST['photos'])) {
                $photos = [];
            } else {
                $photos = $_POST['photos'];
            }
            $photos_json = json_encode($photos);

            //写入信息
            $replacement = new ReplacementModel;
            $replacement = $replacement->find($_POST['id']);

            $total_amount = preg_replace("/^[^\d.]+$/", '', $_POST['price']);
            $data = [
                'title' => $_POST['title'],
                'model' => $_POST['model'],
                'note' => $_POST['note'],
                'num' => $_POST['num'],
                'status' => 1,
                'price' => $total_amount,
                'photos' => $photos_json,
                'create_time' => date("Y-m-d H:i:s"),
            ];

            $result = $replacement->save($data);

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
        $replacement = new ReplacementModel;
        $replacement = $replacement->find($id)->delete();
        $this->success('删除成功');
    }
}
