<?php

namespace app\common\service\order;

use app\common\library\helper;
use app\common\model\order\CloudBillRecord;
use app\common\model\order\CloudRatio;
use app\common\model\settings\Setting;
use app\common\model\supplier\Capital as SupplierCapitalModel;
use app\common\model\supplier\Supplier as SupplierModel;
use app\common\model\user\User as UserModel;
use app\common\model\settings\Setting as SettingModel;
use app\common\model\plus\agent\Order as AgentOrderModel;
use app\common\model\user\PointsLog as PointsLogModel;
use app\common\enum\order\OrderTypeEnum;
use app\common\model\order\OrderSettled as OrderSettledModel;
use app\common\service\activity\ActivityRewardService;

/**
 * 已完成订单结算服务类
 */
class OrderCompleteService
{
    // 订单类型
    private $orderType;

    /**
     * 订单模型类
     * @var array
     */
    private $orderModelClass = [
        OrderTypeEnum::MASTER => 'app\common\model\order\Order',
    ];

    // 模型
    private $model;

    /* @var UserModel $model */
    private $UserModel;

    private $supplierModel;

    /**
     * 构造方法
     */
    public function __construct($orderType = OrderTypeEnum::MASTER)
    {
        $this->orderType = $orderType;
        $this->model = $this->getOrderModel();
        $this->UserModel = new UserModel;
        $this->supplierModel = new SupplierModel();
    }

    /**
     * 初始化订单模型类
     */
    private function getOrderModel()
    {
        $class = $this->orderModelClass[$this->orderType];
        return new $class;
    }

    /**
     * 执行订单完成后的操作
     */
    public function complete($orderList, $appId)
    {
        $activityRewardService = new ActivityRewardService();
        // 已完成订单结算
        // 条件：后台订单流程设置 - 已完成订单设置0天不允许申请售后
        if (SettingModel::getItem('trade', $appId)['order']['refund_days'] == 0) {
            $this->settled($orderList);
        }
        // 发放分销商佣金
        foreach ($orderList as $order) {
            AgentOrderModel::grantMoney($order, $this->orderType);
            $activityRewardService->onOrderCompleted($order);
        }
        //区域代理奖励处理
        $this->setAreaAgentDispose($orderList);
        return true;
    }

    /**
     * 执行订单结算
     */
    public function settled($orderList)
    {
        // 订单id集
        $orderIds = helper::getArrayColumn($orderList, 'order_id');
        // 累积用户实际消费金额
        $this->setIncUserExpend($orderList);
        // 处理订单赠送的积分
        $this->setGiftPointsBonus($orderList);
        // 将订单设置为已结算
        $this->model->onBatchUpdate($orderIds, ['is_settled' => 1]);
        // 供应商结算
        $this->setIncSupplierMoney($orderList);
        return true;
    }

    /**
     * 供应商金额=支付金额-运费
     */
    private function setIncSupplierMoney($orderList)
    {
        // 计算并累积实际消费金额(需减去售后退款的金额)
        $supplierData = [];
        $supplierCapitalData = [];
        // 订单结算记录
        $orderSettledData = [];
        foreach ($orderList as $order) {
            if ($order['shop_supplier_id'] == 0) {
                continue;
            }
            // 供应价格+运费
            $supplierMoney = $order['supplier_money'];
//            $sysMoney = $order['sys_money'];
            $sysMoney = 0;
            // B2b模式，如果有参与分销，减去分销的佣金
            // 商城设置
            $sys_percent = $this->supplierModel->where('shop_supplier_id', '=', $order['shop_supplier_id'])->value('commission_rate');
            $refundSupplierMoney = 0;
            $refundTotalMoney = 0;
            // 减去订单退款的金额
            foreach ($order['product'] as $product) {
                //计算退款金额
                $refundMoney = 0;
                if (!empty($product['refund'])) {
                    $refundMoney = $product['refund']['refund_money'];
                }
                $refundTotalMoney += $refundMoney;
                $sysRealMoney = ($product['total_pay_price'] + $product['coupon_money'] - $refundMoney) * $sys_percent / 100;
                $sysMoney += $sysRealMoney;
            }
            $supplierMoney = $supplierMoney + $order['sys_money'] - $sysMoney - $refundTotalMoney;
            // 分销佣金,只要未失效，都算结算，不管后续是否有退款，因此结算时间设置要注意
            $agentOrder = AgentOrderModel::getDetailByOrderId($order['order_id'], OrderTypeEnum::MASTER);
            $agentMoney = 0;
            $agentTotalMoney = 0;
            if ($agentOrder && $agentOrder['is_invalid'] == 0) {
                $agentTotalMoney = $agentOrder['total_money'];
                $agentMoney = $agentOrder['first_money'] + $agentOrder['second_money'] + $agentOrder['third_money'];
                $supplierMoney -= $agentMoney;
            }

            !isset($supplierData[$order['shop_supplier_id']]) && $supplierData[$order['shop_supplier_id']] = 0.00;
            $supplierMoney > 0 && $supplierData[$order['shop_supplier_id']] += $supplierMoney;
            $refund_supplier_money = round($order['supplier_money'] - $supplierMoney, 2);
            if ($refund_supplier_money >= $agentMoney) {
                $refund_supplier_money = $refund_supplier_money - $agentMoney;
            }
            $orderSettledData[] = [
                'order_id' => $order['order_id'],
                'shop_supplier_id' => $order['shop_supplier_id'],
                'order_money' => $order['order_price'],
                'pay_money' => $order['pay_price'],
                'express_money' => $order['express_price'],
                'supplier_money' => $order['supplier_money'],
                'real_supplier_money' => $supplierMoney,
                'sys_money' => $order['sys_money'],
                'real_sys_money' => $sysMoney,
                'agent_total_money' => $agentTotalMoney,
                'agent_money' => $agentMoney,
                'refund_money' => $refundTotalMoney,
                'refund_supplier_money' => $refund_supplier_money,
                'refund_sys_money' => $order['sys_money'] - $sysMoney,
                'app_id' => $order['app_id']
            ];
            // 商家结算记录
            $supplierCapitalData[] = [
                'shop_supplier_id' => $order['shop_supplier_id'],
                'money' => $supplierMoney,
                'describe' => '订单结算，订单号：' . $order['order_no'],
                'app_id' => $order['app_id']
            ];
        }
        // 累积到供应商表记录
        $this->supplierModel->onBatchIncSupplierMoney($supplierData);
        // 修改平台结算金额
        (new OrderSettledModel())->saveAll($orderSettledData);
        // 供应商结算明细金额
        (new SupplierCapitalModel())->saveAll($supplierCapitalData);
        return true;
    }

    /**
     * 处理订单赠送的积分
     * 只有消费区(zone_type=2)的订单才赠送积分
     */
    private function setGiftPointsBonus($orderList)
    {
        // 计算用户所得积分
        $userData = [];
        $logData = [];
        foreach ($orderList as $order) {
            // 只有消费区订单才赠送积分
            $zoneType = (int)($order['zone_type'] ?? 0);
            if ($zoneType != 2) {
                continue;  // 非消费区订单,不赠送积分
            }
            
            // 计算用户所得积分
            $pointsBonus = $order['points_bonus'];
            if ($pointsBonus <= 0) continue;
            // 减去订单退款的积分
            foreach ($order['product'] as $product) {
                if (
                    !empty($product['refund'])
                    && $product['refund']['type']['value'] == 10      // 售后类型：退货退款
                    && $product['refund']['is_agree']['value'] == 10  // 商家审核：已同意
                ) {
                    $pointsBonus -= $product['points_bonus'];
                }
            }
            // 计算用户所得积分
            !isset($userData[$order['user_id']]) && $userData[$order['user_id']] = 0;
            $userData[$order['user_id']] += $pointsBonus;
            // 整理用户积分变动明细
            $logData[] = [
                'user_id' => $order['user_id'],
                'value' => $pointsBonus,
                'describe' => "订单赠送：{$order['order_no']}",
                'app_id' => $order['app_id'],
            ];
        }
        if (!empty($userData)) {
            // 累积到会员表记录
            $this->UserModel->onBatchIncPoints($userData);
            // 批量新增积分明细记录
            (new PointsLogModel)->onBatchAdd($logData);
        }
        return true;
    }

    /**
     * 累积用户实际消费金额
     */
    private function setIncUserExpend($orderList)
    {
        // 计算并累积实际消费金额(需减去售后退款的金额)
        $userData = [];
        foreach ($orderList as $order) {
            // 订单实际支付金额
            $expendMoney = $order['pay_price'];
            // 减去订单退款的金额
            foreach ($order['product'] as $product) {
                if (
                    !empty($product['refund'])
                    && $product['refund']['type']['value'] == 10      // 售后类型：退货退款
                    && $product['refund']['is_agree']['value'] == 10  // 商家审核：已同意
                ) {
                    $expendMoney -= $product['refund']['refund_money'];
                }
            }
            !isset($userData[$order['user_id']]) && $userData[$order['user_id']] = 0.00;
            $expendMoney > 0 && $userData[$order['user_id']] += $expendMoney;
        }
        // 累积到会员表记录
        $this->UserModel->onBatchIncExpendMoney($userData);
        return true;
    }

    //区域代理奖励处理
    private function setAreaAgentDispose($orderList){
        // 获取购买增送代理参数
        $ratioF = CloudRatio::whereIn('ratio_id',[77,78,79])->column('num','ratio_id');
        foreach ($orderList as $order) {
            $orderId = $order['order_id'];
            $payPrice = $order['pay_price'];
            $provinceId = $order->address['province_id'];
            $cityId = $order->address['city_id'];
            $regionId = $order->address['region_id'];
            $zoneType = $order['zone_type'];
            if(!in_array($zoneType,[1])){
                continue;
            }
            /*echo $orderId.','.$payPrice.','.$provinceId.','.$cityId.','.$regionId;
            exit;*/
            //找出省区域代理用户id
            $provinceUser = $this->UserModel->where(['agent_province_id'=>$provinceId,'agent_city_id'=>0,'agent_district_id'=>0,'is_delete'=>0])->field('user_id,app_id,voucher')->select();
            if(count($provinceUser) > 0 && $ratioF && isset($ratioF[77]) && $ratioF[77] > 0){
                $revenue = ($payPrice * $ratioF[77] / 100) / count($provinceUser);
                foreach ($provinceUser as $userInfo){
                    $this->setAreaAgentDistribution($userInfo, $revenue, '省代理', $orderId);
                }
            }
            //找出市区域代理用户id
            $cityUser = $this->UserModel->where(['agent_province_id'=>$provinceId,'agent_city_id'=>$cityId,'agent_district_id'=>0,'is_delete'=>0])->field('user_id,app_id,voucher')->select();
            if(count($cityUser) > 0 && $ratioF && isset($ratioF[78]) && $ratioF[78] > 0){
                $revenue = ($payPrice * $ratioF[78] / 100) / count($cityUser);
                foreach ($cityUser as $userInfo){
                    $this->setAreaAgentDistribution($userInfo, $revenue, '市代理', $orderId);
                }
            }
            //找出区区域代理用户id
            $regionUser = $this->UserModel->where(['agent_province_id'=>$provinceId,'agent_city_id'=>$cityId,'agent_district_id'=>$regionId,'is_delete'=>0])->field('user_id,app_id,voucher')->select();
            if(count($regionUser) > 0 && $ratioF && isset($ratioF[79]) && $ratioF[79] > 0){
                $revenue = ($payPrice * $ratioF[79] / 100) / count($regionUser);
                foreach ($regionUser as $userInfo){
                    $this->setAreaAgentDistribution($userInfo, $revenue, '区代理', $orderId);
                }
            }
        }
    }

    //区域代理奖励分红
    private function setAreaAgentDistribution($userInfo,$dividend,$profit,$order_id){
        $userId = $userInfo['user_id'];
        //修改用户信息
        \app\common\model\user\User::where('user_id', '=', $userId)->inc('voucher', $dividend)->update();
        //记录日志
        CloudBillRecord::create(['user_id' => $userId,'price' => $dividend, 'ac_type' => 1, 'cur_type' => 2,
            'profit' => $profit."赠积分",'order_id' => $order_id, 'old_num' => $userInfo['voucher'], 'new_num' => $userInfo['voucher'] + $dividend,
        ]);
        \app\common\model\user\VoucherLog::add([
            'user_id'  => $userId,
            'scene'    => \app\common\enum\user\voucher\VoucherLogSceneEnum::PARTNER_DIVIDEND,
            'value'    => $dividend,
            'describe' => $profit.'赠积分',
            'remark'   => '',
            'app_id'   => $userInfo['app_id'],
        ]);
    }

}
