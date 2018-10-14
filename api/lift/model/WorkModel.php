<?php
namespace api\lift\model;

use think\Model;

class WorkModel extends Model{
    public function lift(){
        return $this->belongsTo('LiftModel', 'lift_id');
    }
}