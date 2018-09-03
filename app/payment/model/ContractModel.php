<?php

namespace app\payment\model;

use think\Db;
use think\Model;

class ContractModel extends Model
{
    protected $autoWriteTimestamp = true;

    public function party()
    {
        return $this->belongsTo('ContractPartyModel','party_info_id','id')->bind('a_name,a_address,a_represent,a_tel,b_name,b_address,b_represent,b_tel');
    }

}
