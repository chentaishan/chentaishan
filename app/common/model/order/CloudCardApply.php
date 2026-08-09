<?php

namespace app\common\model\order;

use think\Model;

/**
 * 提现申请表
 */
class CloudCardApply extends Model
{
    protected $pk = 'card_apply_id';
    protected $name = 'cloud_card_apply';

    public function users(){
        return $this->hasOne('app\common\model\user\User','user_id','user_id');
    }

}