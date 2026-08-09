<?php

namespace app\common\model\plus\live;

use app\common\model\BaseModel;

/**
 * 礼物币充值模型
 */
class Plan extends BaseModel
{
    protected $name = 'live_plan';
    protected $pk = 'plan_id';

    protected $append = ['real_money'];

    /**
     * 计算实际到账 (到账金额 + 赠送金额)
     */
    public function getRealMoneyAttr($value, $data)
    {
        return $data['gift_money'] + $data['give_money'];
    }

    /**
     * 详情
     */
    public static function detail($plan_id)
    {
        return (new static())->find($plan_id);
    }

}
