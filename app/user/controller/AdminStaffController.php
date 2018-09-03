<?php
// +----------------------------------------------------------------------
//  用于管理员工 采购人员
// +----------------------------------------------------------------------
namespace app\user\controller;

use think\Db;
use think\Validate;
use cmf\controller\AdminBaseController;
use app\user\model\UserModel;

class AdminStaffController extends AdminBaseController
{
    //首页表格
    public function staff(){
        return $this->fetch();
    }

    //表格数据
    public function staffFetchData(){
        $request = input('request.');
        $page    = $request['page'];
        $limit   = $request['limit'];


        $user = new UserModel(); 
        $count = $user::hasWhere('role',['role_id'=>'3'])->order("create_time DESC")->where('user_type=1')->select();
        $list = $user::hasWhere('role',['role_id'=>'3'])->order("create_time DESC")->where('user_type=1')->with('role.roleinfo,desc')->page($page.','.$limit)->select();
        $result = [
            'code' => 0,
            'msg'  => '',
            'count'=> count($count),
            'data' => $list
        ];
        return json($result);
    }
 
    //添加采购人员
    public function addStaff(){
        return $this->fetch();
    }

    //添加采购人员POST
    public function addStaffPost(){
        if ($this->request->isPost()) {
            $validate = new Validate([
                'user_nickname'  => 'require|max:10',
                'user_login' => 'require|unique:user,user_login',
                'user_pass' => 'require',
                'mobile' => 'number|length:11',
            ],[
                'mobile.length'  => '手机号格式有误',
                'user_login.unique'  => '用户名已存在',
            ]);
            
            $result = $validate->check($_POST);
            if ($result !== true) {
                $this->error($validate->getError());
            } else {
                $post = [
                    'user_login'    => $_POST['user_login'],
                    'user_pass'     => cmf_password($_POST['user_pass']),
                    'user_nickname' => $_POST['user_nickname'],
                    'mobile'        => $_POST['mobile'],
                    'user_status'   => 1,
                ];
                if(empty($_POST['user_status'])){
                    $post['user_status'] = 0;
                }
                $result             = DB::name('user')->insertGetId($post);
                $data = [
                    'user_id'   =>  $result,
                    'description'      =>  $_POST['desc']
                ];
                $desc = DB::name('user_desc')->insert($data);
                if ($result !== false) {
                    //添加一个角色 3号 采购人员
                    Db::name('RoleUser')->insert(["role_id" => 3, "user_id" => $result]);
                    $this->success("添加成功！", url("user/index"));
                } else {
                    $this->error("添加失败！");
                }
            }
        }
    }

    //删除采购人员POST
    public function delStaffPost(){
        $user = new UserModel();
        $user = $user->where('id='.$_POST['id'])->find();
        $res = $user->together('desc,role')->delete();
        $this->success('删除成功');
    }

    //编辑采购人员页面
    public function editStaff(){
        $params = $this->request->param();
        $user = new UserModel();
        $res = $user->where('id='.$params['id'])->with('desc')->find();
        $this->assign('user',$res);
        return $this->fetch();
    }

    //编辑采购人员POST
    public function editStaffPost(){
        if ($this->request->isPost()) {
            $validate = new Validate([
                'user_nickname'  => 'require|max:10',
                'user_login' => 'require|unique:user,user_login',
                'user_pass' => 'require',
                'mobile' => 'number|length:11',
                'id' => 'number',
            ],[
                'mobile.length'  => '手机号格式有误',
                'user_login.unique'  => '用户名已存在',
                'id' => '需要指定一个ID'
            ]);
            $result = $validate->check($_POST);
            if ($result !== true) {
                $this->error($validate->getError());
            } else {
                $userModel = new userModel();
                $user = $userModel->where('id='.$_POST['id'])->find();

                if(empty($_POST['user_status'])){
                    $status = 0;
                }else{
                    $status = 1;
                }
                if($_POST['user_pass'] == $user['user_pass']){
                    $pass = $_POST['user_pass'];
                }else{
                    $pass = cmf_password($_POST['user_pass']);
                }

                $user->user_login = $_POST['user_login'];
                $user->user_nickname = $_POST['user_nickname'];
                $user->mobile = $_POST['mobile'];
                $user->user_status = $status;
                $user->user_pass = $pass;
                $user->desc->description = $_POST['desc'];
                $result = $user->together('desc')->save();

                if ($result !== false) {
                    $this->success("保存成功！", url("user/index"));
                } else {
                    $this->error("保存失败！");
                }
            }
        }
    }
}