<?php

namespace app\shop\service\statistics;

use app\shop\model\order\OrderProduct as OrderProductModel;
use app\common\enum\order\OrderStatusEnum;
use app\common\enum\order\OrderPayStatusEnum;
use app\shop\model\product\Product as ProductModel;
use app\shop\model\order\OrderRefund as OrderRefundModel;

/**
 * 数据统计-商品销售榜
 */
class ProductRankingService
{
    /**
     * 商品销售榜
     */
    public function getSaleRanking()
    {
        return (new OrderProductModel)->alias('o_product')
            ->with(['image'])
            ->field([
                '*',
                'SUM(total_pay_price) AS sales_volume',
                'SUM(total_num) AS total_sales_num'
            ])->hidden(['content'])
            ->join('order', 'order.order_id = o_product.order_id')
            ->where('order.pay_status', '=', OrderPayStatusEnum::SUCCESS)
            ->whereNotIn('order.order_status', OrderStatusEnum::closedStatusList())
            ->group('o_product.product_id')
            ->having('total_sales_num>0')
            ->order(['total_sales_num' => 'DESC'])
            ->limit(10)
            ->select();
    }

    /**
     * 商品浏览榜
     */
    public function getViewRanking()
    {
        return (new ProductModel)->with(['image.file'])
            ->hidden(['content'])
            ->where('view_times', '>', 0)
            ->where('is_delete', '=', 0)
            ->order(['view_times' => 'DESC'])
            ->limit(10)
            ->select();
    }

    /**
     * 商品退款榜
     */
    public function getRefundRanking()
    {
        return (new OrderRefundModel)->alias('order_refund')
            ->with(['orderproduct.image'])
            ->field([
                '*',
                'count(product_id) AS refund_count',
            ])->hidden(['content'])
            ->join('order_product', 'order_product.order_product_id = order_refund.order_product_id')
            ->group('order_product.product_id')
            ->having('refund_count>0')
            ->order(['refund_count' => 'DESC'])
            ->limit(10)
            ->select();
    }
}