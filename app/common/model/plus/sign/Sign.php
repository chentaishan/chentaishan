<?php

namespace app\common\model\plus\sign;

use app\common\model\BaseModel;

/**
 * 用户签到模型
 */
class Sign extends BaseModel
{
    protected $name = 'user_sign';
    protected $pk = 'user_sign_id';

    /**
     * 优惠券信息
     */
    public function getCouponAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    /**
     * 优惠券信息
     */
    public function setCouponAttr($value)
    {
        return $value ? json_encode($value, true) : '';
    }
    
    /**
     * 关联用户
     * @return \think\model\relation\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo("app\\common\\model\\user\\User", 'user_id', 'user_id');
    }

}
