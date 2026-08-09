<?php

namespace app\shop\model\supplier;

use app\common\model\supplier\DepositOrder as DepositOrderModel;

/**
 * 押金订单模型类
 */
class DepositOrder extends DepositOrderModel
{
    /**
     * 获取列表数据
     */
    public function getList($params)
    {
        $model = $this;
        if ($params['pay_type']) {
            $model = $model->where('pay_type', '=', $params['pay_type']);
        }
        if ($params['pay_status']) {
            $model = $model->where('pay_status', '=', $params['pay_status']);
        }
        if (isset($params['shop_supplier_id']) && $params['shop_supplier_id'] > 0) {
            $model = $model->where('shop_supplier_id', '=', $params['shop_supplier_id']);
        }
        // 查询列表数据
        return $model->with(['user', 'supplier'])
            ->field("*,if(pay_time>0,FROM_UNIXTIME(pay_time,'%Y-%m-%d %H:%i:%s'),'') as pay_time")
            ->order(['create_time' => 'desc'])
            ->paginate($params);
    }
}
