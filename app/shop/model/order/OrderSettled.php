<?php

namespace app\shop\model\order;

use app\common\library\helper;
use app\common\model\order\OrderSettled as OrderSettledModel;
use app\shop\model\supplier\Supplier as SupplierModel;
use app\shop\model\order\Order as OrderModel;

/**
 * 订单结算模型
 */
class OrderSettled extends OrderSettledModel
{
    /**
     * 获取数据概况
     */
    public function getSettledData()
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $data = [
            // 供应商结算
            'real_supplier_money' => [
                'today' => number_format($this->getDatas(null, $today, 'real_supplier_money'), 2),
                'yesterday' => number_format($this->getDatas(null, $yesterday, 'real_supplier_money'), 2)
            ],
            // 平台提成
            'real_sys_money' => [
                'today' => number_format($this->getDatas($today, null, 'real_sys_money'), 2),
                'yesterday' => number_format($this->getDatas($yesterday, null, 'real_sys_money'), 2)
            ],
            // 分销佣金
            'agent_money' => [
                'today' => number_format($this->getDatas($today, null, 'agent_money'), 2),
                'yesterday' => number_format($this->getDatas($yesterday, null, 'agent_money'), 2)
            ],
            // 退款金额
            'refund_money' => [
                'today' => number_format($this->getDatas($today, null, 'refund_money'), 2),
                'yesterday' => number_format($this->getDatas($yesterday, null, 'refund_money'), 2)
            ],
        ];
        return $data;
    }

    /**
     * 按日期获取结算数据
     */
    public function getSettledDataByDate($days)
    {
        $data = [];
        foreach ($days as $day) {
            $data[] = [
                'day' => $day,
                'real_supplier_money' => $this->getDatas($day, null, 'real_supplier_money'),
                'real_sys_money' => $this->getDatas($day, null, 'real_sys_money'),
                'agent_money' => $this->getDatas($day, null, 'agent_money'),
                'refund_money' => $this->getDatas($day, null, 'refund_money')
            ];
        }
        return $data;
    }

    /**
     * 获取供应商统计数量
     */
    public function getDatas($startDate = null, $endDate = null, $type = 'real_supplier_money')
    {
        $model = $this;
        if (!is_null($startDate)) {
            $model = $model->where('create_time', '>=', strtotime($startDate));
        } else {
            $model = $model->where('create_time', '>=', strtotime($endDate) - 86400);
        }
        if (is_null($endDate)) {
            $model = $model->where('create_time', '<', strtotime($startDate) + 86400);
        } else {
            $model = $model->where('create_time', '<', strtotime($endDate) + 86400);
        }
        if ($type == 'real_supplier_money') {
            return $model->sum('real_supplier_money');
        } else if ($type == 'real_sys_money') {
            return $model->sum('real_sys_money');
        } else if ($type == 'agent_money') {
            return $model->sum('agent_money');
        } else if ($type == 'refund_money') {
            return $model->sum('refund_money');
        }
        return 0;
    }


    /**
     * 获取售后单列表
     */
    public function getList($params)
    {
        $model = $this;
        // 查询条件：订单号
        if (isset($params['order_no']) && !empty($params['order_no'])) {
            $model = $model->where('order.order_no', 'like', "%{$params['order_no']}%");
        }
        if (isset($params['start_day']) && !empty($params['start_day'])) {
            $model = $model->where('settled.create_time', '>=', strtotime($params['start_day']));
        }
        if (isset($params['end_day']) && !empty($params['end_day'])) {
            $model = $model->where('settled.create_time', '<', strtotime($params['end_day']) + 86400);
        }
        // 是否结算
        if (isset($params['is_settled']) && $params['is_settled'] > -1) {
            $model = $model->where('settled.is_settled', '=', $params['is_settled']);
        }
        // 是否结算
        if (isset($params['shop_supplier_id']) && $params['shop_supplier_id'] > 0) {
            $model = $model->where('settled.shop_supplier_id', '=', $params['shop_supplier_id']);
        }
        // 获取列表数据
        return $model->alias('settled')->field('settled.*')
            ->with(['orderMaster', 'supplier'])
            ->join('order', 'order.order_id = settled.order_id')
            ->order(['settled.create_time' => 'desc'])
            ->paginate($params);
    }

    /**
     * 获取列表数据
     */
    public function getSettledList($params)
    {
        $model = new SupplierModel;
        if (isset($params['shop_supplier_id']) && $params['shop_supplier_id']) {
            $model = $model->where('shop_supplier_id', '=', $params['shop_supplier_id']);
        }
        // 查询列表数据
        $list = $model->where('is_delete', '=', '0')
            ->order(['create_time' => 'desc'])
            ->paginate($params);
        foreach ($list as $item) {
            $settledModel = $this;
            if (isset($params['startDate']) && $params['startDate']) {
                $settledModel = $settledModel->where('create_time', 'between', [strtotime($params['startDate']), strtotime($params['endDate']) + 86399]);
            }
            $orderDetail = $settledModel->where('shop_supplier_id', '=', $item['shop_supplier_id'])
                ->field('sum(order_money+express_money) as total_money,sum(real_supplier_money) as real_money,sum(real_sys_money) as real_sys_money,sum(agent_money) as agent_money')
                ->find();
            // 订单销售总金额以order表pay_price为准（pay_status=20+is_delete=0，与cash/index口径一致）
            $orderTotalMoney = (new OrderModel())
                ->where('shop_supplier_id', '=', $item['shop_supplier_id'])
                ->where('pay_status', '=', 20)
                ->where('is_delete', '=', 0)
                ->sum('pay_price');
            $item['total_money'] = (float)($orderTotalMoney ?: 0);
            $item['real_money'] = $orderDetail['real_money'] ? $orderDetail['real_money'] : 0;
            $item['real_sys_money'] = $orderDetail['real_sys_money'] ? $orderDetail['real_sys_money'] : 0;
            $item['agent_money'] = $orderDetail['total_money'] ? $orderDetail['agent_money'] : 0;

            // 优品区总业绩（与cash/index口径一致：pay_status=20+is_delete=0）
            $firstZoneMoney = (new OrderModel())
                ->where('shop_supplier_id', '=', $item['shop_supplier_id'])
                ->where('pay_status', '=', 20)
                ->where('is_delete', '=', 0)
                ->where('zone_type', '=', 1)
                ->sum('pay_price');
            $item['first_zone_money'] = (float)($firstZoneMoney ?: 0);

            // 消费区总业绩（与cash/index口径一致：pay_status=20+is_delete=0）
            $rebuyZoneMoney = (new OrderModel())
                ->where('shop_supplier_id', '=', $item['shop_supplier_id'])
                ->where('pay_status', '=', 20)
                ->where('is_delete', '=', 0)
                ->where('zone_type', '=', 2)
                ->sum('pay_price');
            $item['rebuy_zone_money'] = (float)($rebuyZoneMoney ?: 0);
        }
        return $list;
    }

    /**
     * 获取列表数据
     */
    public function getSettledDetail($params)
    {
        $supplier = SupplierModel::detail($params['shop_supplier_id']);
        // 获取列表数据
        $detail = $this->where('create_time', '>=', strtotime($params['start_day']))
            ->where('create_time', '<', strtotime($params['end_day']) + 86400)
            ->where('shop_supplier_id', '=', $params['shop_supplier_id'])
            ->field(['sum(order_money) as order_money,sum(express_money) as express_money,
            sum(pay_money) as pay_money,sum(supplier_money) as supplier_money,sum(refund_supplier_money) as refund_supplier_money,
            sum(real_supplier_money) as real_supplier_money,sum(sys_money) as sys_money,sum(refund_sys_money) as refund_sys_money,
            sum(real_sys_money) as real_sys_money,sum(agent_money) as agent_money,sum(refund_money) as refund_money,sum(agent_total_money) as agent_total_money'])->find();
        $orderId = $this->where('create_time', '>=', strtotime($params['start_day']))
            ->where('create_time', '<', strtotime($params['end_day']) + 86400)
            ->where('shop_supplier_id', '=', $params['shop_supplier_id'])
            ->column('order_id');
        $orderDetail = (new OrderModel())->where('order_id', 'in', $orderId)
            ->field('sum(coupon_money) as coupon_money,sum(coupon_money_sys) as coupon_money_sys,sum(points_money) as points_money')
            ->find();
        $detail['agent_total_money'] = $detail['agent_total_money'] > 0 ? $detail['agent_total_money'] : $detail['agent_money'];
        $detail['name'] = $supplier['name'];
        $detail['coupon_money'] = $orderDetail['coupon_money'] ? $orderDetail['coupon_money'] : 0;
        $detail['coupon_money_sys'] = $orderDetail['coupon_money_sys'] ? $orderDetail['coupon_money_sys'] : 0;
        $detail['points_money'] = $orderDetail['points_money'] ? $orderDetail['points_money'] : 0;
        $detail['pay_money'] = helper::number2($detail['pay_money'] - $detail['express_money']);
        $detail['order_money'] = helper::number2($detail['order_money'] + $detail['coupon_money_sys'] + $detail['coupon_money'] + $detail['points_money'] + $detail['express_money']);
        return $detail;
    }
}