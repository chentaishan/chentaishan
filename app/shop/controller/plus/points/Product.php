<?php

namespace app\shop\controller\plus\points;

use app\shop\controller\Controller;
use app\shop\model\plus\point\Product as PointProductModel;
use app\shop\model\settings\Setting as SettingModel;
use app\shop\model\order\Order as OrderModel;
use app\common\model\product\Product as ProductModel;

/**
 * 积分兑换控制器
 */
class Product extends Controller
{
    /**
     *积分商品
     */
    public function index()
    {
        $model = new PointProductModel();
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     *添加积分商品
     */
    public function add($product_id)
    {
        if ($this->request->isGet()) {
            $model = ProductModel::detail($product_id);
            return $this->renderSuccess('', compact('model'));
        }
        $model = new PointProductModel();
        if ($model->checkProduct($product_id)) {
            return $this->renderError('商品已经存在');
        }
        if ($model->saveProduct($this->postData(), false)) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    /**
     *修改商品
     */
    public function edit($point_product_id)
    {
        $model = PointProductModel::detail($point_product_id, ['product.image.file', 'sku']);
        if ($this->request->isGet()) {
            return $this->renderSuccess('', compact('model'));
        }

        if ($model->saveProduct($this->postData(), true)) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }

    /**
     *删除商品
     */
    public function delete($id)
    {
        $model = new PointProductModel();
        if ($model->del($id)) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError('删除失败');
    }


    /**
     *配置
     */
    public function settings()
    {
        if ($this->request->isGet()) {
            $vars['values'] = SettingModel::getItem('pointsmall');
            return $this->renderSuccess('', compact('vars'));
        }

        $model = new SettingModel;
        $data = $this->request->param();
        if ($model->edit('pointsmall', $data)) {
            return $this->renderSuccess('操作成功');
        }
    }

    /**
     *获取兑换记录
     */
    public function record()
    {
        $model = new OrderModel;
        $list = $model->getExchange($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 设置状态
     */
    public function state($point_product_id)
    {
        // 详情
        $model = PointProductModel::detail($point_product_id);
        // 更新记录
        if ($model->setState($this->request->post())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }
}