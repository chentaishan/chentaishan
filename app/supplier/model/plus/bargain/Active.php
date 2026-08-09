<?php

namespace app\supplier\model\plus\bargain;

use app\common\model\plus\bargain\Active as ActiveModel;
use app\common\model\plus\bargain\Product as BargainProductModel;
use app\common\model\plus\bargain\BargainSku as BargainSkuModel;

/**
 *
 */
class Active extends ActiveModel
{
    public function getList($data){
        // 获取数据列表
        return $this->order(['sort' => 'asc', 'create_time' => 'desc'])
            ->where('status','=', 1)
            ->where('is_delete','=', 0)
            ->paginate($data);
    }

    public function add($shop_supplier_id, $data){
        $this->startTrans();
        try {
            //添加商品
            $stock = array_sum(array_column($data['product']['spec_list'], 'bargain_stock'));
            $arr = [
                'product_id' => $data['product_id'],
                'bargain_activity_id' => $data['bargain_activity_id'],
                'app_id' => self::$app_id,
                'stock' => $stock,
                'limit_num' => $data['limit_num'],
                'shop_supplier_id' => $shop_supplier_id
            ];

            $model = new BargainProductModel();
            $model->save($arr);
            //商品规格
            $sku_model = new BargainSkuModel();
            $sku_data  = [];
            foreach ($data['product']['spec_list'] as $sku) {
                $sku_data[] = [
                    'bargain_product_id' => $model['bargain_product_id'],
                    'product_id' => $data['product_id'],
                    'product_sku_id' => $sku['product_sku_id'],
                    'bargain_price' => $sku['bargain_price'],
                    'product_price' => $sku['product_price'],
                    'bargain_stock' => $sku['bargain_stock'],
                    'product_attr' => isset($sku['product_attr'])?$sku['product_attr']:'',
                    'bargain_activity_id' => $data['bargain_activity_id'],
                    'bargain_num' => $data['product']['spec_list'][0]['bargain_num'],
                    'app_id' => self::$app_id,
                ];
            }
            $sku_model->saveAll($sku_data);
            // 事务提交
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }
}