<?php

namespace app\supplier\model\plus\point;

use app\common\model\plus\point\Product as PointProductModel;
use app\common\model\plus\point\ProductSku as PointSkuModel;

/**
 * 参加记录模型
 */
class Product extends PointProductModel
{

    /*
     * 获取列表
     */
    public function getList($shop_supplier_id,$query = [])
    {
        $model = $this;
        if($query['status'] == 'has_audit'){
            $model = $model->where('status' ,'=', 10);
        }
        if($query['status'] == 'wait_audit'){
            $model = $model->where('status' ,'=', 0);
        }
        // 获取列表数据
        return $model->with(['product.image.file','sku'])
            ->where('shop_supplier_id', '=', $shop_supplier_id)
            ->where('is_delete','=', 0)
            ->order(['sort' => 'asc'])
            ->paginate($query);
    }
    /**
     * 添加商品
     * @param $data
     * @return bool
     */
    public function saveProduct($shop_supplier_id, $data)
    {
        $product = $data['product'];
        $this->startTrans();
        try {
            $stock = array_sum(array_column($product['spec_list'], 'point_stock'));
            //添加商品表
            $this->save([
                'product_id' => $data['product_id'],
                'limit_num' => $data['limit_num'],
                'stock' => $stock,
                'shop_supplier_id' => $shop_supplier_id,
                'app_id' => self::$app_id,
            ]);
            //商品规格
            $sku_model = new PointSkuModel();
            $sku_data  = [];
            foreach ($product['spec_list'] as $sku) {
                $sku_data[] = [
                    'point_product_id' => $this['point_product_id'],
                    'product_id' => $data['product_id'],
                    'product_sku_id' => $sku['product_sku_id'],
                    'point_stock' => $sku['point_stock'],
                    'point_num' => $sku['point_num'],
                    'product_attr' => $sku['product_attr'],
                    'product_price' => $sku['product_price'],
                    'point_money' => $sku['point_money'],
                    'app_id' => self::$app_id,
                ];
            }
            //新增规格
            $sku_model->saveAll($sku_data);
            $this->commit();
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
        return true;
    }
    /**
     * 修改
     */
    public function edit($data){
        $this->startTrans();
        try {
            //添加商品
            $stock = array_sum(array_column($data['product']['spec_list'], 'point_stock'));
            $this->save([
                'status' => 0,
                'remark' => '',
                'stock' => $stock,
                'limit_num' => $data['limit_num']
            ]);
            //商品规格
            $sku_model = new PointSkuModel();
            //先删除所有规格
            $sku_model->where('point_product_id', '=', $this['point_product_id'])->delete();
            $sku_data  = [];
            foreach ($data['product']['spec_list'] as $sku) {
                $sku_data[] = [
                    'point_product_id' => $this['point_product_id'],
                    'product_id' => $data['product_id'],
                    'product_sku_id' => $sku['product_sku_id'],
                    'point_stock' => $sku['point_stock'],
                    'point_num' => $sku['point_num'],
                    'product_attr' => $sku['product_attr'],
                    'product_price' => $sku['product_price'],
                    'point_money' => $sku['point_money'],
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
     * 检查商品是否存在
     */
    public function checkProduct($product_id)
    {
        return $this->where('product_id', '=', $product_id)->where('is_delete', '=', 0)->find();
    }

    /**
     * 获取排除商品id,报名了活动，待审核，或者通过了，并且活动未结束
     */
    public function getExcludeIds($shop_supplier_id){
        $list = $this->distinct(true)->field('product.*')
            ->where('is_delete', '=', 0)
            ->where('shop_supplier_id', '=', $shop_supplier_id)
            ->where('status', 'in', [0,10])
            ->column('product_id');
        $exclude_ids = [];
        foreach ($list as $key => $item){
            array_push($exclude_ids, $item);
        }
        return $exclude_ids;
    }

    /**
     * 删除
     */
    public function remove($point_product_id, $shop_supplier_id){
        // 如果不是审核通过，真删
        if($this['status'] != 10){
            return $this->where('point_product_id', '=', $point_product_id)
                ->where('shop_supplier_id', '=', $shop_supplier_id)
                ->delete();
        }else{
            // 逻辑删
            return $this->where('point_product_id', '=', $point_product_id)
                ->where('shop_supplier_id', '=', $shop_supplier_id)
                ->update([
                    'is_delete' => 1
                ]);
        }
    }
}