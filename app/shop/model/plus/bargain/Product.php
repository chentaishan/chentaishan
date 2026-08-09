<?php

namespace app\shop\model\plus\bargain;

use app\common\model\plus\bargain\Product as ProductModel;
use app\common\model\plus\bargain\BargainSku as BargainSkuModel;

/**
 * Class BargainProduct
 * 砍价商品模型
 * @package app\shop\model\plus\bargain
 */
class Product extends ProductModel
{
    /**
     * 获取砍价商品列表
     */
    public static function getList($bargain_activity_id)
    {
        return (new static())->with(['product', 'bargainSku'])
            ->where('bargain_activity_id', '=', $bargain_activity_id)
            ->order(['sort' => 'asc', 'create_time' => 'asc'])
            ->select();
    }

    /**
     * 获取砍价商品列表
     */
    public static function getAllList($data)
    {
        $model = new self();
        if (isset($data['bargain_activity_id']) && $data['bargain_activity_id']) {
            $model = $model->where('bargain_activity_id', '=', $data['bargain_activity_id']);
        }
        return $model->with(['product', 'bargainSku', 'supplier'])
            ->where('status', '=', $data['status'])
            ->where('is_delete', '=', 0)
            ->order(['sort' => 'asc', 'create_time' => 'asc'])
            ->paginate($data);
    }

    /**
     * 检查商品是否存在
     * @param int $product_id
     */
    public function checkProduct($product_id)
    {
        return $this->where('product_id', '=', $product_id)->find();
    }

    /**
     * 新增
     * @param $data
     */
    public function add($bargain_activity_id, $product_list)
    {
        //添加活动
        foreach ($product_list as $product) {
            $this->addProduct($bargain_activity_id, $product);
        }
    }

    /**
     * 修改
     * @param $data
     * @return bool
     */
    public function edit($bargain_activity_id, $product_list)
    {
        //添加活动
        foreach ($product_list as $product) {
            $this->addProduct($bargain_activity_id, $product, true);
        }
    }

    //审核商品状态
    public function editProduct($data)
    {

        $where['bargain_product_id'] = $data['bargain_product_id'];
        return self::update($data, $where);
    }

    /**
     * 添加商品
     * @param $data
     * @return bool
     */
    public function addProduct($bargain_activity_id, $product, $isUpdate = false)
    {
        //添加商品
        $stock = array_sum(array_column($product['spec_list'], 'bargain_stock'));
        $arr = [
            'product_id' => $product['product_id'],
            'limit_num' => $product['limit_num'],
            'stock' => $stock,
            'bargain_activity_id' => $bargain_activity_id,
            'sort' => $product['sort'],
            'sales_initial' => $product['sales_initial'],
            'is_delete' => $product['is_delete'],
            'app_id' => self::$app_id,
        ];
        if ($isUpdate) {
            $model = static::detail($product['bargain_product_id']) ?: new self();
        } else {
            $model = new self();
        }
        $model->save($arr);
        //商品规格
        $sku_model = new BargainSkuModel();
        $save_data = [];
        $not_in_sku_id = [];
        foreach ($product['spec_list'] as $sku) {
            $sku_data = [
                'bargain_product_id' => $model['bargain_product_id'],
                'product_id' => $product['product_id'],
                'product_sku_id' => $sku['product_sku_id'],
                'bargain_price' => $sku['bargain_price'],
                'bargain_num' => $sku['bargain_num'],
                'product_price' => $sku['product_price'],
                'bargain_stock' => $sku['bargain_stock'],
                'product_attr' => isset($sku['product_attr']) ? $sku['product_attr'] : '',
                'bargain_activity_id' => $bargain_activity_id,
                'app_id' => self::$app_id,
            ];
            if ($sku['bargain_product_sku_id'] > 0) {
                $detail = $sku_model->find($sku['bargain_product_sku_id']);
                if ($detail) {
                    $detail->save($sku_data);
                    array_push($not_in_sku_id, $sku['bargain_product_sku_id']);
                }
            } else {
                $save_data[] = $sku_data;
            }
        }

        //删除规格
        count($not_in_sku_id) > 0 && $sku_model->where('bargain_product_id', '=', $model['bargain_product_id'])
            ->whereNotIn('bargain_product_sku_id', $not_in_sku_id)
            ->delete();
        //新增规格
        count($save_data) > 0 && $sku_model->saveAll($save_data);
    }


    /**
     * 商品删除
     */
    public function setDelete()
    {
        return $this->save([
            'is_delete' => 1
        ]);
    }

    /**
     * 获取待审核数量
     */
    public static function getApplyCount()
    {
        return self::where('status', '=', 0)
            ->where('is_delete', '=', 0)
            ->count();
    }

    /**
     * 上下架
     */
    public function setState($data)
    {
        return $this->save($data) !== false;
    }
}