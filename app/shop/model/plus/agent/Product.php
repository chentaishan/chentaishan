<?php

namespace app\shop\model\plus\agent;

use app\common\model\plus\agent\Product as AgentProductModel;
use app\common\model\product\Product as ProductModel;

/**
 * 分销商用户模型
 */
class Product extends AgentProductModel
{
    public function getList($product_id)
    {
        return $this->where('product_id', '=', $product_id)
            ->select();
    }

    /**
     * 保存
     */
    public function edit($params)
    {
        $this->startTrans();
        try {
            (new ProductModel())->where('product_id', '=', $params['product_id'])
                ->save([
                    'is_agent' => $params['is_agent'],
                ]);
            // 参与分销
            if ($params['is_agent'] == 1) {
                // 先删除
                $model = self::detail($params['product_id']);
                if (!$model) {
                    $model = new self();
                }
                $model->save(array_merge($params, [
                    'app_id' => self::$app_id
                ]));
            }
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    //设置状态
    public function setAgent($productIds, $is_agent)
    {
        return (new ProductModel)->where('product_id', 'in', $productIds)->save(['is_agent' => $is_agent]);
    }
}