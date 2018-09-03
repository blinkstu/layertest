<?php
namespace api\admin\model;

use think\Model;
use think\Db;

class LiftModel extends Model
{
    public function worker()
    {
        return $this->belongsTo('UserModel','worker_id','id');
    }

    public function contract()
    {
        return $this->belongsTo('ContractModel','contract_id','id');
    }
}