<?php

namespace app\supplier\model\plus\assemble;

use app\common\model\plus\assemble\Product as ProductModel;
use app\common\model\plus\assemble\AssembleSku as AssembleSkuModel;

/**
 * 参加记录模型
 */
class Product extends ProductModel
{
    /**
     * 获取秒杀商品列表
     */
    public static function getList($shop_supplier_id, $data)
    {
        return (new static())->with(['product.image.file', 'active'])
            ->where('shop_supplier_id', '=', $shop_supplier_id)
            ->order(['sort' => 'asc', 'create_time' => 'desc'])
            ->paginate($data);
    }

    /**
     * 修改
     */
    public function edit($data)
    {
        $this->startTrans();
        try {
            //添加商品
            $stock = array_sum(array_column($data['product']['spec_list'], 'assemble_stock'));
            $this->save([
                'status' => 0,
                'remark' => '',
                'stock' => $stock,
                'limit_num' => $data['limit_num'],
                'assemble_num' => $data['assemble_num'],
            ]);
            //商品规格
            $sku_model = new AssembleSkuModel();
            //先删除所有规格
            $sku_model->where('assemble_product_id', '=', $this['assemble_product_id'])->delete();
            $sku_data = [];
            foreach ($data['product']['spec_list'] as $sku) {
                $sku_data[] = [
                    'assemble_product_id' => $this['assemble_product_id'],
                    'product_id' => $data['product_id'],
                    'product_sku_id' => $sku['product_sku_id'],
                    'assemble_price' => $sku['assemble_price'],
                    'product_price' => $sku['product_price'],
                    'assemble_stock' => $sku['assemble_stock'],
                    'product_attr' => isset($sku['product_attr']) ? $sku['product_attr'] : '',
                    'assemble_activity_id' => $this['assemble_activity_id'],
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

    /**
     * 获取排除商品id,报名了活动，待审核，或者通过了，并且活动未结束
     */
    public function getExcludeIds($shop_supplier_id)
    {
        $list = $this->alias('product')->distinct(true)->field('product.*')
            ->where('product.is_delete', '=', 0)
            ->where('product.shop_supplier_id', '=', $shop_supplier_id)
            ->where('product.status', 'in', [0, 10])
            ->where('activity.end_time', '>', time())
            ->join('assemble_activity activity', 'activity.assemble_activity_id = product.assemble_activity_id', 'left')
            ->column('product_id');
        $exclude_ids = [];
        foreach ($list as $key => $item) {
            array_push($exclude_ids, $item);
        }
        return $exclude_ids;
    }

    /**
     * 删除
     */
    public function remove($assemble_product_id, $shop_supplier_id)
    {
        return $this->where('assemble_product_id', '=', $assemble_product_id)
            ->where('shop_supplier_id', '=', $shop_supplier_id)
            ->where('status', '<>', 10)
            ->delete();
    }

    /**
     * 检查商品是否能报名
     */
    public function checkProduct($product_id, $shop_supplier_id)
    {
        $excludeIds = $this->getExcludeIds($shop_supplier_id);
        if (in_array($product_id, $excludeIds)) {
            return false;
        }
        return true;
    }
}