<?php

namespace app\common\model\plus\agent;

use app\common\model\BaseModel;
use app\common\enum\order\OrderTypeEnum;
use app\common\model\plus\agent\Product as AgentProductModel;
use app\common\model\plus\agent\User as AgentUserModel;

/**
 * 分销商订单模型
 */
class Order extends BaseModel
{
    protected $name = 'agent_order';
    protected $pk = 'id';

    /**
     * 订单所属用户
     * @return \think\model\relation\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('app\common\model\user\User');
    }

    /**
     * 一级分销商用户
     * @return \think\model\relation\BelongsTo
     */
    public function agentFirst()
    {
        return $this->belongsTo('app\common\model\user\User', 'first_user_id');
    }

    /**
     * 二级分销商用户
     * @return \think\model\relation\BelongsTo
     */
    public function agentSecond()
    {
        return $this->belongsTo('app\common\model\user\User', 'second_user_id');
    }

    /**
     * 三级分销商用户
     * @return \think\model\relation\BelongsTo
     */
    public function agentThird()
    {
        return $this->belongsTo('app\common\model\user\User', 'third_user_id');
    }

    /**
     * 订单类型
     * @param $value
     * @return array
     */
    public function getOrderTypeAttr($value)
    {
        $types = OrderTypeEnum::getTypeName();
        return ['text' => $types[$value], 'value' => $value];
    }

    /**
     * 订单详情
     */
    public static function getDetailByOrderId($orderId, $orderType)
    {
        return (new static())->where('order_id', '=', $orderId)
            ->where('order_type', '=', $orderType)
            ->find();
    }

    /**
     * 发放分销订单佣金
     * @param $order
     * @param int $orderType
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function grantMoney($order, $orderType = OrderTypeEnum::MASTER)
    {
        // 订单是否已完成
        if ($order['order_status']['value'] != 30) {
            return false;
        }
        // 佣金结算天数
        $settleDays = Setting::getItem('settlement', $order['app_id'])['settle_days'];
        // 判断该订单是否满足结算时间 (订单完成时间 + 佣金结算时间) ≤ 当前时间
        $deadlineTime = $order['receipt_time'] + ((int)$settleDays * 86400);
        if ($settleDays > 0 && $deadlineTime > time()) {
            return false;
        }

        // 分销订单详情
        $model = self::getDetailByOrderId($order['order_id'], $orderType);
        if (!$model || $model['is_settled'] == 1) {
            return false;
        }
        // 重新计算分销佣金
//        $capital = $model->getCapitalByOrder($order);
        // 发放一级分销商佣金
        $model['first_user_id'] > 0 && User::grantMoney($model['first_user_id'], $model['first_money']);
        // 发放二级分销商佣金
        $model['second_user_id'] > 0 && User::grantMoney($model['second_user_id'], $model['second_money']);
        // 发放三级分销商佣金
        $model['third_user_id'] > 0 && User::grantMoney($model['third_user_id'], $model['third_money']);
        // 更新分销订单记录
        $model->save([
            'is_settled' => 1,
            'settle_time' => time()
        ]);
        $model['first_user_id'] > 0 && event('AgentUserGrade', $model['first_user_id']);
        $model['second_user_id'] > 0 && event('AgentUserGrade', $model['second_user_id']);
        $model['third_user_id'] > 0 && event('AgentUserGrade', $model['third_user_id']);
        return true;
    }

    /**
     * 计算订单分销佣金
     * @param $order
     * @return array
     */
    protected function getCapitalByOrder($order)
    {
        // 分销佣金设置
        $setting = Setting::getItem('commission', $order['app_id']);
        // 分销层级
        $level = Setting::getItem('basic', $order['app_id'])['level'];
        // 分销订单佣金数据
        $capital = [
            // 订单总金额(不含运费)
            'orderPrice' => bcsub($order['pay_price'], $order['express_price'], 2),
            // 一级分销佣金
            'first_money' => 0.00,
            // 二级分销佣金
            'second_money' => 0.00,
            // 三级分销佣金
            'third_money' => 0.00
        ];
        // 计算分销佣金
        foreach ($order['product'] as $product) {
            // 如果商品未开启分销
            if ($product['is_agent'] == 0) {
                continue;
            }
            // 判断商品存在售后退款则不计算佣金
            if ($this->checkProductRefund($product)) {
                continue;
            }
            // 商品实付款金额
            $productPrice = min($capital['orderPrice'], $product['total_pay_price']);
            // 计算商品实际佣金
            $productCapital = $this->calculateProductCapital($setting, $product, $productPrice);
            // 累积分销佣金
            $level >= 1 && $capital['first_money'] += $productCapital['first_money'];
            $level >= 2 && $capital['second_money'] += $productCapital['second_money'];
            $level == 3 && $capital['third_money'] += $productCapital['third_money'];
        }
        return $capital;
    }

    /**
     * 计算商品实际佣金
     * @param $setting
     * @param $product
     * @param $productPrice
     * @return float[]|int[]
     */
    private function calculateProductCapital($setting, $product, $productPrice)
    {
        $first_user = AgentUserModel::detail($this['first_user_id'], ['grade']);
        $second_user = AgentUserModel::detail($this['second_user_id'], ['grade']);
        $third_user = AgentUserModel::detail($this['third_user_id'], ['grade']);
        $add_first_money = 0;
        if ($first_user && $first_user['grade']) {
            $add_first_money = $productPrice * ($first_user['grade']['first_percent'] * 0.01);
        }
        $add_second_money = 0;
        if ($second_user && $second_user['grade']) {
            $add_second_money = $productPrice * ($second_user['grade']['second_percent'] * 0.01);
        }
        $add_third_money = 0;
        if ($third_user && $third_user['grade']) {
            $add_third_money = $productPrice * ($third_user['grade']['third_percent'] * 0.01);
        }
        $agent_product = AgentProductModel::detail($product['product_id']);
        // 全局分销
        if (!isset($agent_product) || (isset($agent_product['is_ind_agent']) && $agent_product['is_ind_agent'] == 0)) {
            // 全局分销比例
            return [
                'first_money' => $productPrice * ($setting['first_money'] * 0.01) + $add_first_money,
                'second_money' => $productPrice * ($setting['second_money'] * 0.01) + $add_second_money,
                'third_money' => $productPrice * ($setting['third_money'] * 0.01) + $add_third_money
            ];
        }
        // 商品单独分销
        if (isset($agent_product['agent_money_type']) && $agent_product['agent_money_type'] == 10) {
            // 分销佣金类型：百分比
            return [
                'first_money' => $productPrice * ($agent_product['first_money'] * 0.01) + $add_first_money,
                'second_money' => $productPrice * ($agent_product['second_money'] * 0.01) + $add_second_money,
                'third_money' => $productPrice * ($agent_product['third_money'] * 0.01) + $add_third_money
            ];
        } else {
            return [
                'first_money' => isset($agent_product['first_money']) ? $product['total_num'] * $agent_product['first_money'] : 0 + $add_first_money,
                'second_money' => isset($agent_product['second_money']) ? $product['total_num'] * $agent_product['second_money'] : 0 + $add_second_money,
                'third_money' => isset($agent_product['third_money']) ? $product['total_num'] * $agent_product['third_money'] : 0 + $add_third_money
            ];
        }
    }

    /**
     * 验证商品是否存在售后
     * @param $product
     * @return bool
     */
    private function checkProductRefund($product)
    {
        return !empty($product['refund'])
            && $product['refund']['type']['value'] != 20
            && $product['refund']['is_agree']['value'] != 20;
    }

    /**
     * 重新计算分销佣金
     */
    public static function updateOrder($order)
    {
        // 分销订单模型
        $model = new self;
        // 分销商基本设置
        $setting = Setting::getItem('basic', $order['app_id']);
        $agentOrder = $model->where('order_id', '=', $order['order_id'])->find();
        if ($agentOrder) {
            // 计算订单分销佣金
            $capital = $agentOrder->getUpdateCapitalByOrder($order);
            // 保存分销订单记录
            return $agentOrder->save([
                'first_money' => $agentOrder['first_user_id'] > 0 ? max($capital['first_money'], 0) : 0,
                'second_money' => $agentOrder['second_user_id'] > 0 ? max($capital['second_money'], 0) : 0,
                'third_money' => $agentOrder['third_user_id'] > 0 ? max($capital['third_money'], 0) : 0,
            ]);
        }
        return false;

    }

    /**
     * 计算订单分销佣金
     * @param $order
     * @return array
     */
    protected function getUpdateCapitalByOrder($order)
    {
        // 分销佣金设置
        $setting = Setting::getItem('commission', $order['app_id']);
        // 分销层级
        $level = Setting::getItem('basic', $order['app_id'])['level'];
        // 分销订单佣金数据
        $capital = [
            // 订单总金额(不含运费)
            'orderPrice' => bcsub($order['pay_price'], $order['express_price'], 2),
            // 一级分销佣金
            'first_money' => 0.00,
            // 二级分销佣金
            'second_money' => 0.00,
            // 三级分销佣金
            'third_money' => 0.00
        ];
        // 计算分销佣金
        foreach ($order['product'] as $product) {
            // 商品实付款金额
            $productPrice = min($capital['orderPrice'], $product['total_pay_price']);
            //商品退款金额
            if (!empty($product['refund'])) {
                $productPrice = $productPrice - $product['refund']['refund_money'];
            }
            $productPrice = $productPrice > 0 ? $productPrice : 0;
            if ($productPrice > 0) {
                // 计算商品实际佣金
                $productCapital = $this->calculateProductCapital($setting, $product, $productPrice);
            } else {
                $productCapital = [
                    // 一级分销佣金
                    'first_money' => 0.00,
                    // 二级分销佣金
                    'second_money' => 0.00,
                    // 三级分销佣金
                    'third_money' => 0.00
                ];
            }
            // 累积分销佣金
            $capital['first_money'] += $productCapital['first_money'];
            $capital['second_money'] += $productCapital['second_money'];
            $capital['third_money'] += $productCapital['third_money'];
        }
        return $capital;
    }

}
