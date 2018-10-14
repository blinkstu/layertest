<?php
namespace api\user\model;

use think\Model;

class WorkModel extends Model
{

    public function lift()
    {
        return $this->belongsTo('LiftModel', 'lift_id', 'id')->bind('customer_id');
    }
}
