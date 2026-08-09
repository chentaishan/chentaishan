<?php

namespace app\common\model\order;

use app\common\model\BaseModel;

/**
 * Class OrderAddress
 */
class OrderDelivery extends BaseModel
{
    protected $name = 'order_delivery';
    protected $pk = 'order_delivery_id';

    /**
     * 关联物流公司表
     * @return \think\model\relation\BelongsTo
     */
    public function express()
    {
        return $this->belongsTo('app\\common\\model\\settings\\Express', 'express_id', 'express_id');
    }

    /**
     * 获取包裹信息
     * @param $value
     * @return array
     */
    public function getDeliveryDataAttr($value, $data)
    {
        return $value ? json_decode($value, true) : [];
    }

    /**
     * 设置包裹信息
     * @param $value
     * @return array
     */
    public function setDeliveryDataAttr($value, $data)
    {
        return $value ? json_encode($value) : '';
    }

    /**
     * 详情
     */
    public static function detail($order_delivery_id)
    {
        return (new static())->find($order_delivery_id);
    }

}