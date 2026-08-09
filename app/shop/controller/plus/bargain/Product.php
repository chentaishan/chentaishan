<?php

namespace app\shop\controller\plus\bargain;

use app\shop\controller\Controller;
use app\shop\model\plus\bargain\Product as BargainProductModel;

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
        $model = new BargainProductModel;
        $list = $model->getAllList($data);
        return $this->renderSuccess('', compact('list'));
    }

    //审核产品
    public function edit($bargain_product_id)
    {
        if ($this->request->isGet()) {
            $detail = BargainProductModel::detail($bargain_product_id, ['product.sku', 'bargainSku', 'active', 'product.image.file']);
            return $this->renderSuccess('', compact('detail'));
        }
        $data = $this->postData();
        // 修改
        $model = BargainProductModel::detail($bargain_product_id);
        if ($model->editProduct($data)) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');

    }

    /**
     * 删除商品
     */
    public function delete($bargain_product_id)
    {
        // 详情
        $model = BargainProductModel::detail($bargain_product_id);
        if ($model->setDelete()) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }

    /**
     * 设置状态
     */
    public function state($bargain_product_id)
    {
        // 详情
        $model = BargainProductModel::detail($bargain_product_id);
        // 更新记录
        if ($model->setState($this->request->post())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }
}