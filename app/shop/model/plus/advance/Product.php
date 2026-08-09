<?php

namespace app\shop\model\plus\advance;

use app\common\model\plus\advance\Product as ProductModel;
use app\common\model\plus\advance\AdvanceSku as AdvanceSkuModel;

/**
 * Class Partake
 * 预售商品模型
 */
class Product extends ProductModel
{
    /*
     * 获取列表
     */
    public function getList($param)
    {
        $model = $this;
        if (isset($param['audit_status']) && $param['audit_status']) {
            $model = $model->where('audit_status', '=', $param['audit_status']);
        }
        // 获取列表数据
        return $model->with(['product.image.file', 'sku', 'supplier'])
            ->where('is_delete', '=', 0)
            ->order(['sort' => 'asc', 'create_time' => 'asc'])
            ->paginate($param);
    }

    /*
     * 获取排除id
     */
    public function getExcludeIds()
    {
        // 获取列表数据
        return $this->where('is_delete', '=', 0)
            ->select()->toArray();
    }

    /**
     * 添加商品
     * @param $data
     * @return bool
     */
    public function saveProduct($data, $isUpdate = false)
    {
        $product = $data['product'];
        $this->startTrans();
        try {
            $stock = array_sum(array_column($product['spec_list'], 'advance_stock'));
            //添加商品表
            $this->save([
                'product_id' => $data['product_id'],
                'money' => $data['money'],
                'reduce_money' => $data['reduce_money'],
                'audit_status' => $data['audit_status'],
                'limit_num' => $data['limit_num'],
                'sort' => $data['sort'],
                'status' => $data['status'],
                'stock' => $stock,
                'start_time' => strtotime($data['active_time'][0]),
                'end_time' => strtotime($data['active_time'][1]),
                'app_id' => self::$app_id
            ]);
            //商品规格
            $sku_model = new AdvanceSkuModel();
            $save_data = [];
            $not_in_sku_id = [];
            foreach ($product['spec_list'] as $sku) {
                $sku_data = [
                    'advance_product_id' => $this['advance_product_id'],
                    'product_id' => $data['product_id'],
                    'product_sku_id' => $sku['product_sku_id'],
                    'advance_stock' => $sku['advance_stock'],
                    'product_attr' => $sku['product_attr'],
                    'product_price' => $sku['product_price'],
                    'advance_price' => $sku['advance_price'],
                    'app_id' => self::$app_id,
                ];
                if ($sku['advance_product_sku_id'] > 0) {
                    $detail = $sku_model->find($sku['advance_product_sku_id']);
                    if ($detail) {
                        $detail->save($sku_data);
                        array_push($not_in_sku_id, $sku['advance_product_sku_id']);
                    }
                } else {
                    $save_data[] = $sku_data;
                }
            }

            //删除规格
            count($not_in_sku_id) > 0 && $sku_model->where('advance_product_id', '=', $data['advance_product_id'])
                ->whereNotIn('advance_product_sku_id', $not_in_sku_id)
                ->delete();
            //新增规格
            count($save_data) > 0 && $sku_model->saveAll($save_data);
            $this->commit();
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
        return true;
    }

    /**
     * 检查商品是否存在
     * @param int $product_id
     */
    public function checkProduct($product_id)
    {
        return $this->where('product_id', '=', $product_id)->where('is_delete', '=', 0)->find();
    }

    /**
     * 获取商品信息
     * @param $id
     */
    public function getPointData($arr)
    {
        return $this->where('advance_product_id', '=', $arr['advance_product_id'])->with(['product.image.file', 'sku'])->find();
    }

    /**
     * @param $data
     * 修改商品信息
     */
    public function edit($data)
    {
        $this->startTrans();
        try {
            //添加商品表
            $this->save([
                'limit_num' => $data['limit_num'],
                'sort' => $data['sort'],
                'stock' => isset($data['stock']) ? $data['stock'] : 0,
                'status' => $data['status'],
            ]);
            if ($data['spec_type'] == 20) {
                $stock = 0;//总库存
                foreach ($data['specData']['spec_list'] as $item) {
                    $sku = $this->getSkuModel($item['spec_form']['point_product_sku_id']);
                    $sku->save([
                        'stock' => $item['spec_form']['stock'],
                        'point_num' => $item['spec_form']['point_num'],
                        'point_money' => $item['spec_form']['point_money'],
                    ]);
                    $stock += $item['spec_form']['stock'];
                }
                $this->save([
                    'stock' => $stock,
                ]);
            } else {
                $sku_model = $this['sku'][0];
                //单规格
                $sku_model->save([
                    'stock' => $data['skuData']['stock'],
                    'point_num' => $data['skuData']['point_num'],
                    'point_money' => $data['skuData']['point_money'],
                ]);
            }
            $this->commit();
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
        return true;
    }

    private function getSkuModel($point_product_sku_id)
    {
        foreach ($this['sku'] as $sku) {
            if ($sku['point_product_sku_id'] == $point_product_sku_id) {
                return $sku;
            }
        }
        return false;
    }

    /**
     * 删除
     */
    public function del($id)
    {
        return $this->where('advance_product_id', '=', $id)->update([
            'is_delete' => 1
        ]);
    }

    /**
     * 商品ID是否存在
     */
    public static function isExistProductId($productId)
    {
        return !!(new static)->where('product_id', '=', $productId)
            ->where('is_delete', '=', 0)
            ->value('advance_product_id');
    }

    /**
     * 获取diy预售活动商品
     */
    public function getDiyProduct()
    {
        $res = $this->with(['advanceProduct.advanceSku', 'advanceProduct.product'])->where('end_time', '>', time())
            ->where('end_time', '>=', time())->find();
        if (isset($res['advanceProduct'])) {
            $list = [];
            foreach ($res['advanceProduct'] as $k => $val) {
                $list[$k]['product_name'] = $val['product']['product_name'];
                $list[$k]['product_id'] = $val['product_id'];
                $list[$k]['product_name'] = $val['product']['product_name'];
            }
            return $res['advanceProduct'];
        }
        return [];
    }

    /**
     * 获取待审核数量
     */
    public static function getApplyCount()
    {
        return (new static())->where('audit_status', '=', 10)
            ->where('is_delete', '=', 0)
            ->count();
    }
}