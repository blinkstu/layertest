<?php
namespace app\admin\model;

use think\Model;

class NotyModel extends Model {
    public function lift(){
        
        return $this->belongsTo('LiftModel','lift_id');
    }
}