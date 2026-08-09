<?php

namespace app\api\service\order\settled;

use app\common\enum\order\OrderSourceEnum;
use app\common\model\settings\Setting as SettingModel;
use app\api\model\order\OrderAdvance as OrderAdvanceModel;

/**
 * 预售订单定金结算服务类
 */
class FrontOrderSettledService extends AdvanceSettledService
{
    private $config;

    /**
     * 构造函数
     */
    public function __construct($user, $supplierData, $params)
    {
        parent::__construct($user, $supplierData, $params);
        // 订单来源
        $this->orderSource = [
            'source' => OrderSourceEnum::ADVANCE,
        ];
        $this->config = SettingModel::getItem('advance');
        // 自身构造,差异化规则
        $this->settledRule = array_merge($this->settledRule, [
            'is_agent' => $this->config['is_agent'],
        ]);
    }

    /**
     * 验证订单商品的状态
     */
    public function validateProductList()
    {

        foreach ($this->supplierData[0]['productList'] as $product) {
            // 判断商品是否开始预售
            if ($product['advance']['start_time'] > time()) {
                $this->error = "很抱歉，还未到达预售时间";
                return false;
            }
            if ($product['advance']['end_time'] < time()) {
                $this->error = "很抱歉，预售商品时间已结束";
                return false;
            }
            if ($product['advance']['audit_status'] != 20 || $product['advance']['status'] != 10) {
                $this->error = "很抱歉，预售商品已下架";
                return false;
            }
            // 判断商品是否下架
            if ($product['product_status']['value'] != 10) {
                $this->error = "很抱歉，预售商品已下架";
                return false;
            }
            // 判断商品库存
            if ($product['total_num'] > $product['advance_sku']['advance_stock']) {
                $this->error = "很抱歉，预售商品库存不足";
                return false;
            }

            // 是否超过购买数
            if ($product['advance_product']['limit_num'] > 0) {
                $hasNum = OrderAdvanceModel::getPlusOrderNum($this->user['user_id'], $product['product_source_id']);
                if ($hasNum + $product['total_num'] > $product['advance_product']['limit_num']) {
                    $this->error = "很抱歉，你购买数量超过最大限购数量";
                    return false;
                }
            }
        }
        return true;
    }
}