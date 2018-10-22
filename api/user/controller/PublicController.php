<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace api\user\controller;

use think\Db;
use think\Validate;
use think\Request;
use cmf\controller\RestBaseController;
use api\user\model\UserModel;
use api\user\model\WorkModel;
use api\user\model\LiftModel;

class PublicController extends RestBaseController
{
    // 用户注册
    public function register()
    {
        $validate = new Validate([
            'username'          => 'require',
            'password'          => 'require',
            'verification_code' => 'require'
        ]);

        $validate->message([
            'username.require'          => '请输入手机号,邮箱!',
            'password.require'          => '请输入您的密码!',
            'verification_code.require' => '请输入数字验证码!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $user = [];

        $findUserWhere = [];

        if (Validate::is($data['username'], 'email')) {
            $user['user_email']          = $data['username'];
            $findUserWhere['user_email'] = $data['username'];
        } else if (cmf_check_mobile($data['username'])) {
            $user['mobile']          = $data['username'];
            $findUserWhere['mobile'] = $data['username'];
        } else {
            $this->error("请输入正确的手机或者邮箱格式!");
        }

        $errMsg = cmf_check_verification_code($data['username'], $data['verification_code']);
        if (!empty($errMsg)) {
            $this->error($errMsg);
        }

        $findUserCount = Db::name("user")->where($findUserWhere)->count();

        if ($findUserCount > 0) {
            $this->error("此账号已存在!");
        }

        $user['create_time'] = time();
        $user['user_status'] = 1;
        $user['user_type']   = 2;
        $user['user_pass']   = cmf_password($data['password']);

        $result = Db::name("user")->insert($user);


        if (empty($result)) {
            $this->error("注册失败,请重试!");
        }

        $this->success("注册并激活成功,请登录!");

    }

    // 用户登录 TODO 增加最后登录信息记录,如 ip
    public function login()
    {
        $validate = new Validate([
            'username' => 'require',
            'password' => 'require'
        ]);
        $validate->message([
            'username.require' => '请输入手机号,邮箱或用户名!',
            'password.require' => '请输入您的密码!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $findUserWhere = [];

        if (Validate::is($data['username'], 'email')) {
            $findUserWhere['user_email'] = $data['username'];
        } else if (cmf_check_mobile($data['username'])) {
            $findUserWhere['mobile'] = $data['username'];
        } else {
            $findUserWhere['user_login'] = $data['username'];
        }

        $findUser = Db::name("user")->where($findUserWhere)->find();

        if (empty($findUser)) {
            $this->error("用户不存在!");
        } else {

            switch ($findUser['user_status']) {
                case 0:
                    $this->error('您已被拉黑!');
                case 2:
                    $this->error('账户还没有验证成功!');
            }

            if (!cmf_compare_password($data['password'], $findUser['user_pass'])) {
                $this->error("密码不正确!");
            }
        }

        $allowedDeviceTypes = $this->allowedDeviceTypes;

        if (empty($data['device_type']) || !in_array($data['device_type'], $allowedDeviceTypes)) {
            $this->error("请求错误,未知设备!");
        }

        $userTokenQuery = Db::name("user_token")
            ->where('user_id', $findUser['id'])
            ->where('device_type', $data['device_type']);
        $findUserToken  = $userTokenQuery->find();
        $currentTime    = time();
        $expireTime     = $currentTime + 24 * 3600 * 180;
        $token          = md5(uniqid()) . md5(uniqid());
        if (empty($findUserToken)) {
            $result = $userTokenQuery->insert([
                'token'       => $token,
                'user_id'     => $findUser['id'],
                'expire_time' => $expireTime,
                'create_time' => $currentTime,
                'device_type' => $data['device_type']
            ]);
        } else {
            $result = $userTokenQuery
                ->where('user_id', $findUser['id'])
                ->where('device_type', $data['device_type'])
                ->update([
                    'token'       => $token,
                    'expire_time' => $expireTime,
                    'create_time' => $currentTime
                ]);
        }


        if (empty($result)) {
            $this->error("登录失败!");
        }

        $this->success("登录成功!", ['token' => $token, 'user' => $findUser]);
    }

    public function userInfo() {
        $userId   = $this->getUserId();
        $user = new UserModel();
        $userData = $user->with('role.roleinfo')->where('id='.$userId)->find();
        $userData = $userData->toArray();
        $request = Request::instance();
        if($userData['avatar']==""){
            $header = $this->request->header();
            $userData['avatar'] = "https://".$header['host']."/static/images/headimg.jpg";
        }
        $today = strtotime(date('Y-m-d'));
        $time = strtotime('+3 days ',$today);
        $time_1day_after = date('Y-m-d',$time);

        $contract = Db::name('contract')->where('customer_id',$userId)->select();
        $contractNum = count($contract);
        $userData['contract_num'] = $contractNum;

        $work = Db::name('work')->where('customer_id',$userId)->where('status',0)->select();
        $workNum = count($work);
        $userData['work_num'] = $workNum;

        $liftModel = new LiftModel;
        $lift = $liftModel::hasWhere('contract',['customer_id'=>$userId])->select();
        $liftNumCustomer = count($lift);
        $userData['lift_num_customer'] = $liftNumCustomer;

        $liftData = Db::name('lift')->where('worker_id',$userId)->where('next_date' ,'<= time',$time_1day_after)->select();
        $liftNum = count($liftData);
        $userData['lift_num'] = $liftNum;

        $notyData = Db::name('noty')->where('user_id',$userId)->where('status',1)->select();
        $notyNum = count($notyData);
        $userData['noty_num'] = $notyNum;

        $this->success("获取成功", $userData);
    }

    // 用户退出
    public function logout()
    {
        $userId = $this->getUserId();
        Db::name('user_token')->where([
            'token'       => $this->token,
            'user_id'     => $userId,
            'device_type' => $this->deviceType
        ])->update(['token' => '']);

        $this->success("退出成功!");
    }

    public function changePass(){
        $validate = new Validate([
            'password_new'          => 'require',
            'password_re'          => 'require',
        ]);

        $validate->message([
            'password_new'          => '请输入新密码',
            'password_re'          => '请重复新密码',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $userId = $this->getUserId();
        $user = new UserModel();
        $userData = $user->find($userId);

        if(cmf_password($data['password_new']) == $userData['user_pass']) {
            $this->error('不能与旧密码相同');
        } else {
            $user->where('id='.$userId)->update(['user_pass' => cmf_password($data['password_new'])]);
            $this->success('修改成功');
        }

    }

    // 用户密码重置
    public function passwordReset()
    {
        $validate = new Validate([
            'username'          => 'require',
            'password'          => 'require',
            'verification_code' => 'require'
        ]);

        $validate->message([
            'username.require'          => '请输入手机号,邮箱!',
            'password.require'          => '请输入您的密码!',
            'verification_code.require' => '请输入数字验证码!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $userWhere = [];
        if (Validate::is($data['username'], 'email')) {
            $userWhere['user_email'] = $data['username'];
        } else if (cmf_check_mobile($data['username'])) {
            $userWhere['mobile'] = $data['username'];
        } else {
            $this->error("请输入正确的手机或者邮箱格式!");
        }

        $errMsg = cmf_check_verification_code($data['username'], $data['verification_code']);
        if (!empty($errMsg)) {
            $this->error($errMsg);
        }

        $userPass = cmf_password($data['password']);
        Db::name("user")->where($userWhere)->update(['user_pass' => $userPass]);

        $this->success("密码重置成功,请使用新密码登录!");

    }


    public function wechatLogin(){
        $params = $this->request->param();
        $lift_settings = cmf_get_option('lift_settings');
        $secret = $lift_settings['secret'];
        $appId = $lift_settings['appId'];

        $validate = new Validate([
            'code'           => 'require',
        ]);

        $validate->message([
            'code.require'           => '缺少参数code!',
        ]);

        $data = $this->request->param(); 
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $currentTime = time();
        $ip          = $this->request->ip(0, true);
        $code = $params['code'];
        //$secret = 'ab6f9d92ef6be3c8c44ccc6dcb5dd983';
        //$appId = 'wx863684d4865e70f9';

        $response = cmf_curl_get("https://api.weixin.qq.com/sns/oauth2/access_token?appid=$appId&secret=$secret&code=$code&grant_type=authorization_code");
        $response = json_decode($response, true);
        if (!empty($response['errcode'])) {
            $this->error('操作失败!');
        }

        $openid     = $response['openid'];
        $access_token = $response['access_token'];

        $findThirdPartyUser = Db::name("third_party_user")
            ->where('openid', $openid)
            ->where('app_id', $appId)
            ->find();

        if ($findThirdPartyUser) {
            $userId = $findThirdPartyUser['user_id'];

            if ($userId != '' || $userId != 0) {
                //找到第三方用户 并且已绑定
                $token  = cmf_generate_user_token($findThirdPartyUser['user_id'], 'wxapp');

                $userData = [
                    'last_login_ip'   => $ip,
                    'last_login_time' => $currentTime,
                    'login_times'     => Db::raw('login_times+1'),
                ];
    
                Db::name("third_party_user")
                    ->where('openid', $openid)
                    ->where('app_id', $appId)
                    ->update($userData);
                
                $response = array();
                $response['openid'] = $openid;
                $response['bind']   = true;
                $response['token']  = $token;

                $this->success('登陆成功',$response);
            } else {
                //找到第三方用户 没有绑定
                $response = array();
                $response['openid'] = $openid;
                $response['bind']   = false;

                return $this->error('没有绑定账号',$response);
            }
        } else {
            //没有第三方用户 做注册事务
            $response = cmf_curl_get("https://api.weixin.qq.com/sns/userinfo?access_token=$access_token&openid=$openid&lang=zh_CN");
            $response = json_decode($response, true);
            if (!empty($response['errcode'])) {
                $this->error('操作失败!');
            }

            Db::name("third_party_user")->insert([
                'openid'          => $openid,
                'third_party'     => 'wxapp',
                'app_id'          => $appId,
                'last_login_ip'   => $ip,
                'nickname'        => $response['nickname'],
                'last_login_time' => $currentTime,
                'create_time'     => $currentTime,
                'login_times'     => 1,
                'status'          => 1,
                'more'            => json_encode($response)
            ]);

            $response['bind'] = false;

            $this->success('请绑定账号',$response);
        }



        $this->success($response);
    }

    public function WechatBind(){
        $validate = new Validate([
            'username' => 'require',
            'password' => 'require',
            'openid'   => 'require'
        ]);
        $validate->message([
            'username.require' => '请输入手机号,邮箱或用户名!',
            'password.require' => '请输入您的密码!',
            'openid.require'   => '需要Openid'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $findUserWhere = [];

        if (Validate::is($data['username'], 'email')) {
            $findUserWhere['user_email'] = $data['username'];
        } else if (cmf_check_mobile($data['username'])) {
            $findUserWhere['mobile'] = $data['username'];
        } else {
            $findUserWhere['user_login'] = $data['username'];
        }

        $findUser = Db::name("user")->where($findUserWhere)->find();

        if (empty($findUser)) {
            $this->error("用户不存在!");
        } else {

            switch ($findUser['user_status']) {
                case 0:
                    $this->error('您已被拉黑!');
                case 2:
                    $this->error('账户还没有验证成功!');
            }

            if (!cmf_compare_password($data['password'], $findUser['user_pass'])) {
                $this->error("密码不正确!");
            }
        }

        $allowedDeviceTypes = $this->allowedDeviceTypes;

        if (empty($data['device_type']) || !in_array($data['device_type'], $allowedDeviceTypes)) {
            $this->error("请求错误,未知设备!");
        }

        $userTokenQuery = Db::name("user_token")
            ->where('user_id', $findUser['id'])
            ->where('device_type', $data['device_type']);
        $findUserToken  = $userTokenQuery->find();
        $currentTime    = time();
        $expireTime     = $currentTime + 24 * 3600 * 180;
        $token          = md5(uniqid()) . md5(uniqid());
        if (empty($findUserToken)) {
            $result = $userTokenQuery->insert([
                'token'       => $token,
                'user_id'     => $findUser['id'],
                'expire_time' => $expireTime,
                'create_time' => $currentTime,
                'device_type' => $data['device_type']
            ]);
        } else {
            $result = $userTokenQuery
                ->where('user_id', $findUser['id'])
                ->where('device_type', $data['device_type'])
                ->update([
                    'token'       => $token,
                    'expire_time' => $expireTime,
                    'create_time' => $currentTime
                ]);
        }


        if (empty($result)) {
            $this->error("登录失败!");
        }

        $openid = $data['openid'];
        $userData = [
            'user_id' => $findUser['id']
        ];
        Db::name("third_party_user")->where('openid', $openid)->update($userData);

        $findThirdPartyUser = Db::name("third_party_user")->where('openid', $openid)->find();

        $more = $findThirdPartyUser['more'];
        $more = json_decode($more,true);
        $avatar = $more['headimgurl'];
        $userData = [
            'avatar' => $avatar
        ];
        Db::name("user")
                    ->where('id', $findUser['id'])
                    ->update($userData);


        $this->success("登录成功!", ['token' => $token, 'user' => $findUser]);
    }
}
