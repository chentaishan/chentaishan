<?php

namespace app\supplier\model\plus\product;

use app\common\model\plus\product\Reduce as ReduceModel;
use app\common\model\product\Category;
use app\supplier\model\product\Product as ProductModel;
use app\common\model\shop\FullReduce as FullReduceModel;

/**
 * 商品满减
 */
class Reduce extends ReduceModel
{
    public function getList($data, $shop_supplier_id)
    {
        $model = new ProductModel();
        if (isset($data['is_join']) && $data['is_join'] != -1) {
            if ($data['is_join'] == 1) {
                $model = $model->where('reduce.product_id', '>', 0);
            } else if ($data['is_join'] == 0) {
                $model = $model->where('reduce.product_id', '=', null);
            }
        }
        if ($data['category_id'] > 0) {
            $arr = Category::getSubCategoryId($data['category_id']);
            $model = $model->where('category_id', 'IN', $arr);
        }
        if (!empty($data['product_name'])) {
            $model = $model->where('product_name', 'like', '%' . trim($data['product_name']) . '%');
        }
        $list = $model->field(['product.product_id,product.product_name,product.product_price,product.product_stock,product.sales_actual,product.sales_initial,reduce.product_id as reduce_pid'])
            ->alias('product')
            ->with(['image.file'])
            ->where('product_status', '=', 10)
            ->where('audit_status', '=', 10)
            ->where('shop_supplier_id', '=', $shop_supplier_id)
            ->join('product_reduce reduce', 'reduce.product_id = product.product_id', 'left')
            ->order(['reduce.create_time' => 'desc', 'product.create_time' => 'desc'])
            ->paginate($data);
        $full_model = new FullReduceModel();
        foreach ($list as &$item) {
            if ($item['reduce_pid'] != null) {
                $item['reduce_list'] = $full_model->where('product_id', '=', $item['reduce_pid'])->select();
            } else {
                $item['reduce_list'] = [];
            }
        }
        return $list;
    }

    public function edit($data, $shop_supplier_id)
    {
        $this->startTrans();
        try {
            $full_model = new FullReduceModel();
            $this->where('product_id', '=', $data['product_id'])->delete();
            $full_model->where('product_id', '=', $data['product_id'])->delete();
            if ($data['is_join'] == 1) {
                $this->save([
                    'product_id' => $data['product_id'],
                    'app_id' => self::$app_id
                ]);
                $save_data = [];
                if (isset($data['reduce_list']) && $data['reduce_list']) {
                    foreach ($data['reduce_list'] as $item) {
                        $save_data[] = [
                            'active_name' => '商品满减',
                            'full_type' => $data['full_type'],
                            'full_value' => $item['full_value'],
                            'reduce_type' => $data['reduce_type'],
                            'reduce_value' => $item['reduce_value'],
                            'product_id' => $data['product_id'],
                            'shop_supplier_id' => $shop_supplier_id,
                            'app_id' => self::$app_id
                        ];
                    }
                }
                $full_model->saveAll($save_data);
            }
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }
}