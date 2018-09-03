<?php 
namespace app\lift\model;

use think\Model;
use think\Db;

class RoleUserModel extends Model{
    public function roleinfo()
    {
        return $this->belongsTo('RoleModel','role_id','id')->bind('name');
    }
}