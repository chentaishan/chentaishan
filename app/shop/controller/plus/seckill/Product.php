<?php

namespace app\shop\controller\plus\seckill;

use app\shop\controller\Controller;
use app\shop\model\plus\seckill\Product as SeckillProductModel;

/**
 * 产品控制器
 */
class Product extends Controller
{
    /**
     * 产品列表
     */
    public function index()
    {
        $data = $this->postData();
        $model = new SeckillProductModel;
        $list = $model->getAllList($data);
        return $this->renderSuccess('', compact('list'));
    }

    //审核产品
    public function edit($seckill_product_id)
    {
        if ($this->request->isGet()) {
            $detail = SeckillProductModel::detail($seckill_product_id);
            return $this->renderSuccess('', compact('detail'));
        }
        $data = $this->postData();
        // 修改
        $model = SeckillProductModel::detail($seckill_product_id);
        if ($model->editProduct($data)) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }

    /**
     * 删除商品
     */
    public function delete($seckill_product_id)
    {
        // 详情
        $model = SeckillProductModel::detail($seckill_product_id);
        if ($model->setDelete()) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }

    /**
     * 设置状态
     */
    public function state($seckill_product_id)
    {
        // 详情
        $model = SeckillProductModel::detail($seckill_product_id);
        // 更新记录
        if ($model->setState($this->request->post())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }
}