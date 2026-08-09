<?php

namespace app\common\model\order;

use app\common\model\BaseModel;
use app\common\model\order\Order as OrderModel;
use app\shop\model\user\User as UserModel;
use app\common\service\order\OrderRefundService;
use app\common\service\message\MessageService;
use app\common\enum\order\OrderPayTypeEnum;
use app\common\enum\order\OrderSourceEnum;
use app\common\enum\order\OrderStatusEnum;
use app\common\model\plus\agent\Order as AgentOrderModel;
use app\common\service\activity\ActivityRewardService;
use app\common\service\greenpoints\GreenPointsService;
use app\common\service\product\factory\ProductFactory;

/**
 * 售后管理模型
 */
class OrderRefund extends BaseModel
{
    protected $name = 'order_refund';
    protected $pk = 'order_refund_id';

    /**
     * 关联用户表
     */
    public function user()
    {
        return $this->belongsTo('app\\common\\model\\user\\User');
    }

    /**
     * 关联订单主表
     */
    public function orderMaster()
    {
        return $this->belongsTo('app\\common\\model\\order\\Order');
    }

    /**
     * 关联订单商品表
     */
    public function orderproduct()
    {
        return $this->belongsTo('app\\common\\model\\order\\OrderProduct', 'order_product_id', 'order_product_id');
    }

    /**
     * 关联图片记录表
     */
    public function image()
    {
        return $this->hasMany('app\\common\\model\\order\\OrderRefundImage');
    }

    /**
     * 关联物流公司表
     */
    public function express()
    {
        return $this->belongsTo('app\\api\\model\\settings\\Express');
    }

    /**
     * 关联物流公司表
     */
    public function sendexpress()
    {
        return $this->belongsTo('app\\api\\model\\settings\\Express', 'send_express_id', 'express_id');
    }

    /**
     * 关联用户表
     */
    public function address()
    {
        return $this->hasOne('app\\api\\model\\order\\OrderRefundAddress');
    }

    /**
     * 关联供应商表
     */
    public function supplier()
    {
        return $this->belongsTo('app\\common\\model\\supplier\\Supplier', 'shop_supplier_id', 'shop_supplier_id');
    }

    /**
     * 售后类型
     */
    public function getTypeAttr($value)
    {
        $status = [10 => '退货退款', 20 => '换货', 30 => '仅退款'];
        return ['text' => $status[$value], 'value' => $value];
    }

    /**
     * 售后类型
     */
    public function getPlateStatusAttr($value)
    {
        $status = [0 => '未申请', 10 => '待审核', 20 => '已同意', 30 => '已拒绝'];
        return ['text' => $status[$value], 'value' => $value];
    }

    /**
     * 商家是否同意售后
     */
    public function getIsAgreeAttr($value)
    {
        $status = [0 => '待审核', 10 => '已同意', 20 => '已拒绝'];
        return ['text' => $status[$value], 'value' => $value];
    }

    /**
     * 售后单状态
     */
    public function getStatusAttr($value)
    {
        $status = [0 => '进行中', 10 => '已拒绝', 20 => '已完成', 30 => '已取消'];
        return ['text' => $status[$value], 'value' => $value];
    }

    /**
     * 售后类型
     */
    public function getDeliverTimeAttr($value)
    {

        return isset($value) && $value > 0 ? date('Y-m-d H:i:s', $value) : '';
    }

    /**
     * 订单是否存在进行中的售后单
     */
    public static function hasActiveByOrderId(int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }
        return (bool)(new static())
            ->where('order_id', '=', $orderId)
            ->where('status', '=', 0)
            ->value('order_refund_id');
    }

    /**
     * 售后单详情
     */
    public static function detail($where)
    {
        is_array($where) ? $filter = $where : $filter['order_refund_id'] = (int)$where;
        return (new static())->with(['order_master.advance', 'image.file', 'orderproduct.image', 'express', 'address', 'user', 'sendexpress'])->where($filter)->find();
    }

    /**
     * 获取退款订单总数 (可指定某天)
     * 已同意的退款
     */
    public function getOrderRefundData($startDate, $endDate, $type, $shop_supplier_id)
    {
        $model = $this;
        $model = $model->where('create_time', '>=', strtotime($startDate));
        if (is_null($endDate)) {
            $model = $model->where('create_time', '<', strtotime($startDate) + 86400);
        } else {
            $model = $model->where('create_time', '<', strtotime($endDate) + 86400);
        }

        if ($shop_supplier_id > 0) {
            $model = $model->where('shop_supplier_id', '=', $shop_supplier_id);
        }

        $model = $model->where('is_agree', '=', 10);

        if ($type == 'order_refund_money') {
            // 退款金额
            return $model->sum('refund_money');
        } else if ($type == 'order_refund_total') {
            // 退款数量
            return $model->count();
        }
        return 0;
    }

    /**
     * 商家审核
     */
    public function audit($data)
    {
        if ($this['is_agree']['value'] != 0) {
            $this->error = '售后已审核';
            return false;
        }
        if ($data['is_agree'] == 20 && empty($data['refuse_desc'])) {
            $this->error = '请输入拒绝原因';
            return false;
        }
        if ($data['is_agree'] == 10 && $this['type']['value'] != 30 && empty($data['address_id'])) {
            $this->error = '请选择退货地址';
            return false;
        }
        $this->startTrans();
        try {
            if ($this['refund_money'] > 0) {
                $this->error = '平台已退款';
                return false;
            }
            // 拒绝申请, 标记售后单状态为已拒绝
            $data['is_agree'] == 20 && $data['status'] = 10;
            // 同意换货申请, 标记售后单状态为已完成
            //$data['is_agree'] == 10 && $this['type']['value'] == 20 && $data['status'] = 20;
            // 更新退款单状态
            $this->save($data);
            // 同意售后申请, 记录退货地址
            if ($data['is_agree'] == 10 && $this['type']['value'] != 30) {
                $model = new OrderRefundAddress();
                $model->add($this['order_refund_id'], $data['address_id']);
            }
            // 订单详情
            $order = Order::detail($this['order_id']);
            // 发送模板消息
            (new MessageService)->refund(self::detail($this['order_refund_id']), $order['order_no'], 'audit');
            // 如果是仅退款，同时撤销订单对应的待发放奖励
            if ($data['is_agree'] == 10 && $this['type']['value'] == 30) {
                (new ActivityRewardService)->cancelOrderRewards((int)$this['order_id']);
                $total_refund = $this['orderproduct']['total_pay_price'];
                if ($order['order_source'] == 70 && $order['advance']['money_return'] == 1) {
                    $total_refund = round($total_refund + $order['advance']['pay_price'], 2);
                }
                if ($data['refund_money'] > $total_refund) {
                    $this->error = '退款金额不能大于商品实付款金额';
                    return false;
                }
                $this->refundMoney($order, $data);
            }
            // 事务提交
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    /**
     * 确认收货并退款
     */
    public function receipt($data)
    {
        // 订单详情
        $order = Order::detail($this['order_id']);
        $total_refund = $this['orderproduct']['total_pay_price'];
        if ($order['order_source'] == 70 && $order['advance']['money_return'] == 1) {
            $total_refund = round($total_refund + $order['advance']['pay_price'], 2);

        }
        if ($data['refund_money'] > $total_refund) {
            $this->error = '退款金额不能大于商品实付款金额';
            return false;
        }
        $this->transaction(function () use ($order, $data) {
            (new ActivityRewardService)->reclaimOrderRewards((int)$this['order_id']);
            $this->refundMoney($order, $data);
        });
        return true;
    }

    public function refundMoney($order, $data)
    {
        $advance_money = 0;//预售定金
        if ($order['order_source'] == 70 && $order['advance']['money_return'] == 1) {
            $totalMoney = round($order['pay_price'] + $order['advance']['pay_price'], 2);
            if ($data['refund_money'] > $order['pay_price']) {
                $data['refund_money'] = $order['pay_price'];
                $advance_money = round($totalMoney - $data['refund_money'], 2);
            }
        }
        $update = [
            'is_receipt' => 1,
            'status' => 20
        ];
        if ($this['type']['value'] == 20 && isset($data['send_express_id'])) {
            $update['send_express_id'] = $data['send_express_id'];
            $update['send_express_no'] = $data['send_express_no'];
            $update['deliver_time'] = time();
            $update['is_plate_send'] = 1;
        }
        $data['refund_money'] > 0 && $update['refund_money'] = $data['refund_money'];
        $out_refund_no = '';
        if ($order['pay_type']['value'] == OrderPayTypeEnum::ALIPAY) {
            $out_refund_no = (new OrderModel)->orderNo();
        }
        $out_refund_no && $update['out_refund_no'] = $out_refund_no;
        // 更新售后单状态
        $this->save($update);
        // 消减用户的实际消费金额
        // 条件：判断订单是否已结算
        if ($order['is_settled'] == true && $data['refund_money'] > 0) {
            (new UserModel)->setDecUserExpend($order['user_id'], $data['refund_money']);
        }
        // 执行原路退款
        $data['refund_money'] > 0 && (new OrderRefundService)->execute($order, $data['refund_money'], $out_refund_no);
        $newRefundMoney = round((float)$order['refund_money'] + (float)($data['refund_money'] ?? 0), 2);
        $data['refund_money'] > 0 && $order->save(['refund_money' => $newRefundMoney]);
        //退预售定金
        $advance_money > 0 && (new OrderRefundService)->execute($order['advance'], $advance_money);
        $advance_money > 0 && $order['advance']->save(['refund_money' => $advance_money]);
        //重新计算分销佣金
        AgentOrderModel::updateOrder($order);
        // 退款完成后同步主订单状态（移出待发货/待收货列表）
        $this->syncOrderStatusAfterRefund((int)$order['order_id'], $newRefundMoney);
        // 发送模板消息
        (new MessageService)->refund(self::detail($this['order_refund_id']), $order['order_no'], 'receipt');
    }

    /**
     * 售后退款完成后：整单退款则关闭主订单，避免仍出现在待发货/待收货列表
     */
    protected function syncOrderStatusAfterRefund(int $orderId, float $totalRefundMoney): void
    {
        if ($orderId <= 0) {
            return;
        }
        // 换货完成不关闭主订单
        if ((int)$this['type']['value'] === 20) {
            return;
        }

        $order = Order::detail($orderId);
        if (!$order || in_array((int)$order['order_status']['value'], OrderStatusEnum::closedStatusList(), true)) {
            return;
        }

        $payPrice = round((float)$order['pay_price'], 2);
        if ($order['order_source'] == OrderSourceEnum::ADVANCE && !empty($order['advance']['money_return'])) {
            $payPrice = round($payPrice + (float)$order['advance']['pay_price'], 2);
        }

        $productCount = count($order['product'] ?? []);
        $completedRefundCount = (int)(new static())
            ->where('order_id', '=', $orderId)
            ->where('status', '=', 20)
            ->where('type', '<>', 20)
            ->count();

        $shouldClose = false;
        if ($payPrice > 0 && $totalRefundMoney >= $payPrice - 0.01) {
            $shouldClose = true;
        }
        if ($productCount > 0 && $completedRefundCount >= $productCount) {
            $shouldClose = true;
        }
        if ((int)$this['type']['value'] === 30 && $productCount <= 1) {
            $shouldClose = true;
        }
        if (!$shouldClose) {
            return;
        }

        $deliveryStatus = (int)$order['delivery_status']['value'];
        if ($deliveryStatus !== 20) {
            ProductFactory::getFactory($order['order_source'])->backProductStock($order['product'], true);
        }

        $user = UserModel::detail($order['user_id']);
        if ($user && (float)$order['points_num'] > 0) {
            $user->setIncPoints($order['points_num'], "订单已售后：{$order['order_no']}");
        }
        $order->backCoupon();
        (new GreenPointsService())->refundVoucherForCancelledOrder($order->toArray());

        if ($order['order_source'] == OrderSourceEnum::ADVANCE) {
            $order['advance']->save(['order_status' => OrderStatusEnum::REFUNDED]);
        }
        (new AgentOrderModel)->where('order_id', '=', $orderId)->update(['is_invalid' => 1]);
        $order->save([
            'order_status' => OrderStatusEnum::REFUNDED,
            'cancel_remark' => '已售后',
        ]);
    }
}