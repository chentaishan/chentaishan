<?php

namespace app\common\model\plus\live;

use app\common\model\BaseModel;
use app\common\enum\order\OrderPayStatusEnum;
use app\common\enum\order\OrderPayTypeEnum;
use app\common\service\order\OrderService;

/**
 * 礼物币充值模型
 */
class PlanOrder extends BaseModel
{
    protected $name = 'live_plan_order';
    protected $pk = 'order_id';

    /**
     * 关联用户表
     */
    public function user()
    {
        return $this->belongsTo('app\\common\\model\\user\\User', 'user_id', 'user_id');
    }

    /**
     * 付款状态
     */
    public function getPayTypeAttr($value)
    {
        return ['text' => OrderPayTypeEnum::data()[$value]['name'], 'value' => $value];
    }

    /**
     * 付款状态
     */
    public function getPayStatusAttr($value)
    {
        return ['text' => OrderPayStatusEnum::data()[$value]['name'], 'value' => $value];
    }

    /**
     * 订单详情
     */
    public static function detail($where, $with = ['user'])
    {
        is_array($where) ? $filter = $where : $filter['order_id'] = (int)$where;
        return (new static())->with($with)->find($where);
    }

    /**
     * 生成订单号
     */
    public function orderNo()
    {
        return OrderService::createOrderNo();
    }
}
