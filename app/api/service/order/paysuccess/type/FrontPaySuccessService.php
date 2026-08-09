<?php

namespace app\api\service\order\paysuccess\type;

use app\api\model\order\Order as OrderModel;
use app\api\model\order\OrderAdvance as OrderAdvanceModel;
use app\api\model\user\User as UserModel;
use app\common\enum\order\OrderPayTypeEnum;
use app\common\enum\user\balanceLog\BalanceLogSceneEnum;
use app\common\model\user\BalanceLog as BalanceLogModel;
use app\common\service\BaseService;
use app\common\model\settings\Setting as SettingModel;
use app\common\service\product\factory\ProductFactory;

/**
 * 定金订单支付成功后的回调
 */
class FrontPaySuccessService extends BaseService
{
    // 订单模型
    public $model;

    // 当前用户信息
    private $user;

    /**
     * 构造函数
     */
    public function __construct($orderNo, $pay_status = 10)
    {
        // 实例化订单模型
        $this->model = OrderAdvanceModel::getPayDetail($orderNo, $pay_status);
        // 获取用户信息
        $this->user = UserModel::detail($this->model['user_id']);
    }

    /**
     * 返回app_id，大于0则存在订单信息
     */
    public function isExist()
    {
        if ($this->model) {
            return $this->model['app_id'];
        }
        return 0;
    }

    /**
     * 订单支付成功业务处理
     */
    public function onPaySuccess($payType, $payData = [])
    {
        if (empty($this->model)) {
            $this->error = '未找到该订单信息';
            return false;
        }
        // 更新付款状态
        $status = $this->updatePayStatus($payType, $payData);
        // 订单支付成功行为
        if ($status == true && $payType == OrderPayTypeEnum::WECHAT) {
            // 获取订单详情
            $detail = OrderAdvanceModel::getUserOrderDetail($this->model['order_id'], $this->user['user_id']);
            //小程序发货
            (new OrderAdvanceModel)->sendWxExpress($detail, $this->user);
        }
        return $status;
    }

    /**
     * 更新付款状态
     */
    private function updatePayStatus($payType, $payData = [])
    {
        // 事务处理
        $this->model->transaction(function () use ($payType, $payData) {
            // 更新订单状态
            $this->updateOrderInfo($payType, $payData);
            // 记录订单支付信息
            $this->updatePayInfo();
            // 更新主订单
            $this->updatePayOrderInfo();
        });
        return true;
    }

    /**
     * 更新订单记录
     */
    private function updatePayOrderInfo()
    {
        $config = SettingModel::getItem('advance', $this->model['app_id']);
        $pay_time = $config['pay_time'];
        if ($pay_time > 0) {
            $order['pay_end_time'] = $this->model['end_time'] + ($pay_time * 3600);
            // 更新订单状态
            return $this->model['orderM']->save($order);
        }
    }

    /**
     * 更新订单记录
     */
    private function updateOrderInfo($payType, $payData)
    {
        // 更新商品库存、销量
        ProductFactory::getFactory($this->model['orderM']['order_source'])->updateAdvanceStockSales($this->model['orderM']['product']);
        // 更新商品库存 (针对下单减库存的商品)
        ProductFactory::getFactory($this->model['orderM']['order_source'])->updateProductStock($this->model['orderM']['product']);
        // 整理订单信息
        $pay_source = '';
        if (isset($payData['attach'])) {
            $attach = json_decode($payData['attach'], true);
            $pay_source = isset($attach['pay_source']) ? $attach['pay_source'] : '';
        }
        $order = [
            'pay_type' => $payType,
            'pay_status' => 20,
            'pay_time' => time(),
            'pay_source' => $pay_source,
            'main_order_no' => $this->model['orderM']['order_no'],
        ];
        if ($payType == OrderPayTypeEnum::WECHAT || $payType == OrderPayTypeEnum::ALIPAY) {
            $order['transaction_id'] = $payData['transaction_id'];
        }
        // 更新订单状态
        return $this->model->save($order);
    }

    /**
     * 记录订单支付信息
     */
    private function updatePayInfo()
    {
        // 余额支付
        if ($this->model['balance'] > 0) {
            // 更新用户余额
            (new UserModel())->where('user_id', '=', $this->user['user_id'])
                ->dec('balance', $this->model['balance'])
                ->update();
            BalanceLogModel::add(BalanceLogSceneEnum::CONSUME, [
                'user_id' => $this->user['user_id'],
                'money' => -$this->model['balance'],
                'app_id' => $this->model['app_id']
            ], ['order_no' => $this->model['order_no']]);
        }
    }
}