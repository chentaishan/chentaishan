<?php

namespace app\shop\model\plus\assemble;

use app\common\model\plus\assemble\Product as ProductModel;

/**
 * 拼团商品模型
 */
class Product extends ProductModel
{
    /**
     * 获取秒杀商品列表
     */
    public static function getList($assemble_activity_id)
    {
        return (new static())->with(['product', 'assembleSku'])
            ->where('assemble_activity_id', '=', $assemble_activity_id)
            ->order(['sort' => 'asc', 'create_time' => 'asc'])
            ->select();
    }

    /**
     * 获取砍价商品列表
     */
    public static function getAllList($data)
    {
        $model = new self();
        if (isset($data['assemble_activity_id']) && $data['assemble_activity_id']) {
            $model = $model->where('assemble_activity_id', '=', $data['assemble_activity_id']);
        }
        return $model->with(['product', 'assembleSku', 'supplier'])
            ->where('status', '=', $data['status'])
            ->where('is_delete', '=', 0)
            ->order(['sort' => 'asc', 'create_time' => 'asc'])
            ->paginate($data);
    }

    /**
     * 新增
     */
    public function add($assemble_activity_id, $product_list)
    {
        //添加活动
        foreach ($product_list as $product) {
            $this->addProduct($assemble_activity_id, $product);
        }
    }

    public function addProduct($assemble_activity_id, $product, $isUpdate = false)
    {
        //添加商品
        $stock = array_sum(array_column($product['spec_list'], 'assemble_stock'));
        $arr = [
            'product_id' => $product['product_id'],
            'limit_num' => $product['limit_num'],
            'stock' => $stock,
            'assemble_activity_id' => $assemble_activity_id,
            'assemble_num' => $product['assemble_num'],
            'sort' => $product['sort'],
            'sales_initial' => $product['sales_initial'],
            'is_delete' => $product['is_delete'],
            'app_id' => self::$app_id,
        ];
        if ($isUpdate) {
            $model = static::detail($product['assemble_product_id']) ?: new self();
        } else {
            $model = new self();
        }
        $model->save($arr);
        //商品规格
        $sku_model = new AssembleSku();
        $save_data = [];
        $not_in_sku_id = [];
        foreach ($product['spec_list'] as $sku) {
            $sku_data = [
                'assemble_product_id' => $model['assemble_product_id'],
                'product_id' => $product['product_id'],
                'product_sku_id' => $sku['product_sku_id'],
                'assemble_price' => $sku['assemble_price'],
                'product_price' => $sku['product_price'],
                'assemble_stock' => $sku['assemble_stock'],
                'product_attr' => isset($sku['product_attr']) ? $sku['product_attr'] : '',
                'assemble_activity_id' => $assemble_activity_id,
                'app_id' => self::$app_id,
            ];
            if ($sku['assemble_product_sku_id'] > 0) {
                $detail = $sku_model->find($sku['assemble_product_sku_id']);
                if ($detail) {
                    $detail->save($sku_data);
                    array_push($not_in_sku_id, $sku['assemble_product_sku_id']);
                }
            } else {
                $save_data[] = $sku_data;
            }
        }

        //删除规格
        count($not_in_sku_id) > 0 && $sku_model->where('assemble_product_id', '=', $model['assemble_product_id'])
            ->whereNotIn('assemble_product_sku_id', $not_in_sku_id)
            ->delete();
        //新增规格
        count($save_data) > 0 && $sku_model->saveAll($save_data);
    }


    /**
     * 修改
     */
    public function edit($assemble_activity_id, $product_list)
    {
        //添加活动
        foreach ($product_list as $product) {
            $this->addProduct($assemble_activity_id, $product, true);
        }
    }

    //审核商品状态
    public function editProduct($data)
    {

        $where['assemble_product_id'] = $data['assemble_product_id'];
        return self::update($data, $where);
    }

    public function del($assemble_product_id)
    {
        $this->startTrans();
        try {
            self::destroy($assemble_product_id);
            $model = new AssembleSku();
            $model->delAll($assemble_product_id);
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
     *获取指定活动商品
     */
    public function getProductList($assemble_activity_id, $param = [])
    {
        $model = $this;
        $res = $model->with(['product.image.file', 'assembleSku', 'active.file'])
            ->where('assemble_activity_id', '=', $assemble_activity_id)
            ->paginate($param);
        if (!empty($res)) {
            $res = $res->toArray();
            foreach ($res['data'] as $key => $val) {
                $arr = array_column($res['data'][$key]['assembleSku'], 'assemble_price');
                if (count($arr) == 1) {
                    $res['data'][$key]['assemble_price'] = '￥' . current($arr);
                } else {
                    sort($arr);
                    $res['data'][$key]['assemble_price'] = '￥' . current($arr) . ' - ￥' . end($arr);
                }

            }
        }
        return $res;
    }

    public function getAssembleDetail($assemble_product_id)
    {
        $res = $this->with(['product.image.file', 'assembleSku.productSku.image'])
            ->where('assemble_product_id', '=', $assemble_product_id)->find();
        if (!empty($res)) {
            $arr = array_column($res->toArray()['assembleSku'], 'assemble_price');
            foreach ($res['assembleSku'] as $key => $val) {
                $res['assembleSku'][$key]['price'] = $res['assembleSku'][$key]['productSku']['product_price'];
            }
            $arr1 = array_column($res->toArray()['assembleSku'], 'price');
            sort($arr);
            sort($arr1);
            $res['assemble_price'] = '￥' . current($arr);
            $res['line_price'] = '￥' . current($arr1);
            if (count($arr) > 1) {
                $res['assemble_price'] = '￥' . current($arr) . ' - ￥' . end($arr);
                $res['line_price'] = '￥' . current($arr1) . ' - ￥' . end($arr1);
            }
        }
        return $res;
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
        return (new static())->where('status', '=', 0)
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