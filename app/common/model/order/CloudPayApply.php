<?php

namespace app\common\model\order;

use think\Model;

/**
 * 充值申请表
 */
class CloudPayApply extends Model
{
    protected $pk = 'pay_apply_id';
    protected $name = 'cloud_pay_apply';

    public function users(){
        return $this->hasOne('app\common\model\user\User','user_id','user_id');
    }


}