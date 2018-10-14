<?php
// +----------------------------------------------------------------------
// | 文件说明：用户表关联model
// +----------------------------------------------------------------------
// | Copyright (c) 2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2017-7-26
// +----------------------------------------------------------------------
namespace api\user\model;

use think\Model;

class UserModel extends Model
{

    protected $type = [
        'more' => 'array',
    ];

    public function role()
    {
        return $this->belongsTo('RoleUserModel', 'id', 'user_id')->bind('name,role_id');
    }

    public function work()
    {
        return $this->hasMany('WorkModel', 'customer_id', 'id');
    }

    /**
     * avatar 自动转化
     * @param $value
     * @return string
     */
    public function getAvatarAttr($value)
    {
        return cmf_get_user_avatar_url($value);
    }
}
