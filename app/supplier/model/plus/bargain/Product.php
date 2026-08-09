<?php

namespace app\supplier\model\plus\bargain;

use app\common\model\plus\bargain\Product as ProductModel;
use app\common\model\plus\bargain\BargainSku as BargainSkuModel;

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
    public function edit($data){
        $this->startTrans();
        try {
            //添加商品
            $stock = array_sum(array_column($data['product']['spec_list'], 'bargain_stock'));
            $this->save([
                'status' => 0,
                'remark' => '',
                'stock' => $stock,
                'limit_num' => $data['limit_num']
            ]);
            //商品规格
            $sku_model = new BargainSkuModel();
            //先删除所有规格
            $sku_model->where('bargain_product_id', '=', $this['bargain_product_id'])->delete();
            $sku_data  = [];
            foreach ($data['product']['spec_list'] as $sku) {
                $sku_data[] = [
                    'bargain_product_id' => $this['bargain_product_id'],
                    'product_id' => $data['product_id'],
                    'product_sku_id' => $sku['product_sku_id'],
                    'bargain_price' => $sku['bargain_price'],
                    'product_price' => $sku['product_price'],
                    'bargain_stock' => $sku['bargain_stock'],
                    'product_attr' => isset($sku['product_attr'])?$sku['product_attr']:'',
                    'bargain_activity_id' => $this['bargain_activity_id'],
                    'bargain_num' => $sku['bargain_num'],
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
    public function getExcludeIds($shop_supplier_id){
        $list = $this->alias('product')->distinct(true)->field('product.*')
            ->where('product.is_delete', '=', 0)
            ->where('product.shop_supplier_id', '=', $shop_supplier_id)
            ->where('product.status', 'in', [0,10])
            ->where('activity.end_time', '>', time())
            ->join('bargain_activity activity', 'activity.bargain_activity_id = product.bargain_activity_id','left')
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
    public function remove($bargain_product_id, $shop_supplier_id){
        return $this->where('bargain_product_id', '=', $bargain_product_id)
            ->where('shop_supplier_id', '=', $shop_supplier_id)
            ->where('status', '<>', 10)
            ->delete();
    }

    /**
     * 检查商品是否能报名
     */
    public function checkProduct($product_id, $shop_supplier_id){
        $excludeIds = $this->getExcludeIds($shop_supplier_id);
        if(in_array($product_id, $excludeIds)){
            return false;
        }
        return true;
    }
}