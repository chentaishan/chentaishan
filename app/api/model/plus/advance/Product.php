<?php

namespace app\api\model\plus\advance;

use app\common\exception\BaseException;
use app\common\model\plus\advance\Product as AdvanceProductModel;
use app\api\model\product\Product as ProductModel;

/**
 * 预售商品模型
 */
class Product extends AdvanceProductModel
{
    /*
     * 获取列表
     */
    public function getList($params)
    {
        $model = $this;
        if (isset($params['search']) && $params['search']) {
            $model = $model->where('product.product_name', 'like', '%' . $params['search'] . '%');
        }
        // 获取列表数据
        $list = $model->alias('a')
            ->with(['product.image.file', 'sku'])
            ->join('product product', 'product.product_id=a.product_id')
            ->where('a.is_delete', '=', 0)
            ->where('a.status', '=', 10)
            ->where('a.audit_status', '=', 20)
            ->where('product.is_delete', '=', 0)
            ->where('product.audit_status', '=', 10)
            ->where('product.product_status', '=', 10)
            ->where('a.end_time', '>', time())
            ->field('a.*')
            ->order(['sort' => 'asc', 'create_time' => 'asc'])
            ->paginate($params);
        foreach ($list as $key => $val) {
            $list[$key]['product_image'] = $val['product']['image'][0]['file_path'];
        }
        return $list;
    }

    /**
     * 获取预售商品列表（用于订单结算）
     */
    public static function getAdvanceProduct($params)
    {
        // 预售商品详情
        $advance = self::detail($params['advance_product_id'], ['sku']);
        if (empty($advance) || $advance['status'] == 20 || $advance['is_delete'] == 1 || $advance['audit_status'] != 20) {
            throw new BaseException(['msg' => '预售商品不存在或已结束']);
        }
        // 积分商品详情
        $product = ProductModel::detail($advance['product_id']);
        // 积分商品sku信息
        $advance_sku = null;
        if ($product['spec_type'] == 10) {
            $advance_sku = $advance['sku'][0];
        } else {
            //多规格
            foreach ($advance['sku'] as $sku) {
                if ($sku['advance_product_sku_id'] == $params['advance_product_sku_id']) {
                    $advance_sku = $sku;
                    break;
                }
            }
        }
        if ($advance_sku == null) {
            throw new BaseException(['msg' => '预售商品规格不存在']);
        }
        // 商品sku信息
        $product['product_sku'] = ProductModel::getProductSku($product, $params['product_sku_id']);
        $product['advance_sku'] = $advance_sku;
        $product['advance'] = $advance;
        // 商品列表
        $productList = [$product->hidden(['category', 'content', 'image', 'sku'])];
        // 只会有一个商品
        foreach ($productList as &$item) {
            // 商品定金
            $item['front_price'] = $advance['money'];
            // 商品立减金额
            $item['reduce_money'] = $advance_sku['advance_price'];
            // 商品单价
            $item['product_price'] = $advance_sku['product_price'];
            // 商品购买数量
            $item['total_num'] = $params['product_num'];
            $item['spec_sku_id'] = $item['product_sku']['spec_sku_id'];
            // 商品购买总金额
            $item['total_price'] = $advance_sku['product_price'] * $item['total_num'];
            // 商品购买总定金
            $item['total_front_price'] = $item['front_price'] * $item['total_num'];
            $item['advance_product_sku_id'] = $advance_sku['advance_product_sku_id'];
            $item['product_sku_id'] = $params['product_sku_id'];
            $item['product_source_id'] = $advance_sku['advance_product_id'];
            $item['sku_source_id'] = $advance_sku['advance_product_sku_id'];
            // 预售商品最大购买数
            $item['advance_product'] = [
                'limit_num' => $advance['limit_num']
            ];
        }
        $supplierData[] = [
            'shop_supplier_id' => $product['shop_supplier_id'],
            'supplier' => $product['supplier'],
            'productList' => $productList
        ];
        unset($product['supplier']);
        return $supplierData;
    }

}
