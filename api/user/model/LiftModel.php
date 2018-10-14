<?php
namespace api\user\model;

use app\admin\model\UserModel;
use think\Model;

class LiftModel extends UserModel
{

    public function contract()
    {
        return $this->belongsTo('ContractModel', 'contract_id', 'id')->bind('customer_id');
    }
}
