<?php

namespace app\api\service\order\settled;

use app\common\enum\order\OrderSourceEnum;
use app\common\model\settings\Setting as SettingModel;
use app\api\model\order\Order as OrderModel;
use app\api\model\product\Product as ProductModel;

/**
 * 预售订单结算服务类
 */
class AdvanceOrderSettledService extends OrderSettledService
{
    private $config;

    /**
     * 构造函数
     */
    public function __construct($user, $productList, $params)
    {
        parent::__construct($user, $productList, $params);
        // 订单来源
        $this->orderSource = [
            'source' => OrderSourceEnum::ADVANCE,
        ];
        $this->config = SettingModel::getItem('advance');
        // 自身构造,差异化规则
        $this->settledRule = array_merge($this->settledRule, [
            'is_point' => $this->config['is_point'],     //积分抵扣
            'is_coupon' => $this->config['is_coupon'],
            'is_agent' => $this->config['is_agent'],
            'is_user_grade' => $this->config['is_user_grade'],     // 会员等级折扣
        ]);
    }

    /**
     * 验证订单商品的状态
     */
    public function validateProductList()
    {
        $advance = $this->params['order']['advance'];
        if ($advance['end_time'] > time()) {
            $this->error = "预售时间还未结束，不允许支付尾款";
            return false;
        }
        if ($this->params['order']['pay_end_time'] > 0 && $this->params['order']['pay_end_time'] < time()) {
            $this->error = "预售支付截至时间已结束，不允许支付尾款";
            return false;
        }
        $product = ProductModel::detail($this->productList[0]['product_id']);

        if ($product['product_status']['value'] != 10) {
            $this->error = "很抱歉，预售商品已下架";
            return false;
        }
        return true;
    }
}