<?php

namespace app\supplier\model\plus\assemble;

use app\common\model\plus\assemble\Active as ActiveModel;
use app\common\model\plus\assemble\Product as AssembleProductModel;
use app\common\model\plus\assemble\AssembleSku as AssembleSkuModel;

/**
 *
 */
class Active extends ActiveModel
{
    public function getList($data)
    {
        // 获取数据列表
        return $this->order(['sort' => 'asc', 'create_time' => 'desc'])
            ->where('status', '=', 1)
            ->where('is_delete', '=', 0)
            ->paginate($data);
    }

    public function add($shop_supplier_id, $data)
    {
        $this->startTrans();
        try {
            //添加商品
            $stock = array_sum(array_column($data['product']['spec_list'], 'assemble_stock'));
            $arr = [
                'product_id' => $data['product_id'],
                'assemble_activity_id' => $data['assemble_activity_id'],
                'app_id' => self::$app_id,
                'stock' => $stock,
                'limit_num' => $data['limit_num'],
                'assemble_num' => $data['assemble_num'],
                'shop_supplier_id' => $shop_supplier_id
            ];

            $model = new AssembleProductModel();
            $model->save($arr);
            //商品规格
            $sku_model = new AssembleSkuModel();
            $sku_data = [];
            foreach ($data['product']['spec_list'] as $sku) {
                $sku_data[] = [
                    'assemble_product_id' => $model['assemble_product_id'],
                    'product_id' => $data['product_id'],
                    'product_sku_id' => $sku['product_sku_id'],
                    'assemble_price' => $sku['assemble_price'],
                    'product_price' => $sku['product_price'],
                    'assemble_stock' => $sku['assemble_stock'],
                    'product_attr' => isset($sku['product_attr']) ? $sku['product_attr'] : '',
                    'assemble_activity_id' => $data['assemble_activity_id'],
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