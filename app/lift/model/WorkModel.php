<?php 
namespace app\lift\model;

use think\Model;
use think\Db;

class WorkModel extends Model
{
    public function lift()
    {
        return $this->belongsTo('LiftModel','lift_id','id');
    }

    public function worker()
    {
        return $this->belongsTo('UserModel','worker_id','id')->bind('user_nickname');
    }
}