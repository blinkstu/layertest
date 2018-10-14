<?php
namespace api\lift\model;

use think\Model;

class UserModel extends Model {
    function role()
    {
        return $this->belongsTo('RoleUserModel', 'id', 'user_id')->bind('name,role_id');
    }
}