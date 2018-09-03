<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Powerless < wzxaini9@gmail.com>
// +----------------------------------------------------------------------
namespace app\lift\model;

use think\Db;
use think\Model;

class UserModel extends Model
{

    public function role()
    {
        return $this->belongsTo('RoleUserModel','id','user_id')->bind('name,role_id');
    }

    public function gotLifts()
    {
        return $this->hasMany('LiftModel','worker_id','id');
    }

    public function desc()
    {
        return $this->belongsTo('UserDescModel','id','user_id')->bind('description');;
    }
}
