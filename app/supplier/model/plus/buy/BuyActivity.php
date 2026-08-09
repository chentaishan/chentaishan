<?php

namespace app\supplier\model\plus\buy;

use app\common\model\plus\buy\BuyActivity as BuyActivityModel;
use app\common\model\product\Product as ProductModel;

/**
 * 买送模型
 */
class BuyActivity extends BuyActivityModel
{

    /**
     * 新增记录
     */
    public function add($data, $shop_supplier_id)
    {
        if (!isset($data['limit_product']) || !$data['limit_product']) {
            $this->error = '请添加购买商品';
            return false;
        }
        $data['shop_supplier_id'] = $shop_supplier_id;
        $result = $this->validateData($data);
        if (!$result) {
            return false;
        }
        // 开启事务
        $this->startTrans();
        try {
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            $data['app_id'] = self::$app_id;
            $this->save($data);
            // 购买商品
            $this->addProduct($data);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    /**
     * 编辑记录
     */
    public function edit($data)
    {
        if (!isset($data['limit_product']) || !$data['limit_product']) {
            $this->error = '请添加购买商品';
            return false;
        }
        $data['shop_supplier_id'] = $this['shop_supplier_id'];
        $result = $this->validateData($data, 'edit');
        if (!$result) {
            return false;
        }
        // 开启事务
        $this->startTrans();
        try {
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            $data['audit_status'] = 10;
            $this->save($data);
            // 购买商品
            $this->addProduct($data, true);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    /**
     * 添加购买商品
     */
    private function addProduct($data, $isUpdate = false)
    {
        // 更新模式: 先删除所有规格
        $model = new BuyActivityProduct();
        $isUpdate && $model->where('buy_id', '=', $this['buy_id'])->delete();
        $addProduct = [];
        foreach ($data['limit_product'] as $product) {
            $addProduct[] = [
                'buy_id' => $this['buy_id'],
                'product_name' => $product['product_name'],
                'product_id' => $product['product_id'],
                'product_num' => $product['product_num'],
                'shop_supplier_id' => $data['shop_supplier_id'],
                'app_id' => self::$app_id
            ];
        }
        $model->saveAll($addProduct);
    }

    /**
     * 验证
     */
    public function validateData($data, $scene = 'add')
    {
        //查询商品是否存在其他活动中
        $model = $this->alias('b');
        if ($scene == 'edit') {
            $model = $model->where('b.buy_id', '<>', $this['buy_id']);
        }
        $allProduct = $model->join('buy_activity_product p', 'p.buy_id=b.buy_id')
            ->where('b.is_delete', '=', 0)
            ->where('b.status', '=', 1)
            ->where('b.shop_supplier_id', '=', $data['shop_supplier_id'])
            ->column('product_id');
        $limitProductID = [];
        foreach ($data['limit_product'] as $item) {
            $limitProductID[] = $item['product_id'];
        }
        $sameProduct = array_intersect($limitProductID, $allProduct);
        if ($sameProduct) {
            $product_name = (new ProductModel())->where('shop_supplier_id', '=', $data['shop_supplier_id'])->where('product_id', 'in', $sameProduct)->value('product_name');
            $this->error = $product_name . '已参与活动';
            return false;
        }
        return true;
    }
}