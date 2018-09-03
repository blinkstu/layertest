<?php 
namespace app\lift\model;

use think\Db;
use think\Model;

class LiftModel extends Model
{
    public function worker()
    {
        return $this->belongsTo('UserModel','worker_id','id')->bind('user_nickname');
    }
}