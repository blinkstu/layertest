<?php
namespace api\user\model;

use think\Model;

class ContractModel extends Model
{
    public function user()
    {
        return $this->belongsTo('UserModel', 'customer_id', 'id');
    }
}
