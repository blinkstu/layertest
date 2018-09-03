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
namespace app\contract\model;

use think\Db;
use think\Model;

class ContractModel extends Model
{
    protected $autoWriteTimestamp = true;

    public function party()
    {
        return $this->belongsTo('ContractPartyModel','party_info_id','id')->bind('a_name,a_address,a_represent,a_tel,b_name,b_address,b_represent,b_tel');
    }

    public function customer()
    {
        return $this->belongsTo('UserModel','customer_id','id')->bind('user_login,user_nickname');
    }
}
