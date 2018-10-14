<?php
namespace api\lift\model;

use think\Model;

class ContractModel extends Model{
    public function user()
    {
        return $this->belongsTo('UserModel', 'customer_id', 'id')->bind('user_nickname,user_login');
    }
}