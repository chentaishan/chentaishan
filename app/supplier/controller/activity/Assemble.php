<?php

namespace app\supplier\controller\activity;

use app\common\model\product\Product as ProductModel;
use app\supplier\controller\Controller;
use app\supplier\model\plus\assemble\Active as ActiveModel;
use app\supplier\model\plus\assemble\Product as AssembleProductModel;

/**
 * 拼团活动控制器
 */
class Assemble extends Controller
{
    /**
     * 列表
     */
    public function index()
    {
        $list = (new ActiveModel())->getList($this->postData());
        //排除id
        $exclude_ids = (new AssembleProductModel())->getExcludeIds($this->getSupplierId());
        return $this->renderSuccess('', compact('list', 'exclude_ids'));
    }

    /**
     * 报名
     */
    public function add($product_id, $assemble_activity_id)
    {
        if ($this->request->isGet()) {
            //商品详情
            $model = ProductModel::detail($product_id);
            //活动详情
            $active = ActiveModel::detail($assemble_activity_id);
            return $this->renderSuccess('', compact('model', 'active'));
        }
        $data = $this->postData();
        // 新增记录
        $supplier = $this->supplier['user'];
        if (!(new AssembleProductModel())->checkProduct($product_id, $this->getSupplierId())) {
            return $this->renderError('商品已经参加活动了，请勿重复提交');
        }
        $model = new ActiveModel();
        if ($model->add($supplier['shop_supplier_id'], $data)) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    /**
     * 列表
     */
    public function my()
    {
        $supplier = $this->supplier['user'];
        $list = (new AssembleProductModel())->getList($supplier['shop_supplier_id'], $this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 报名
     */
    public function edit($assemble_product_id)
    {
        if ($this->request->isGet()) {
            //商品详情
            $model = AssembleProductModel::detail($assemble_product_id, ['active', 'product' => ['sku', 'image.file'], 'assembleSku']);
            return $this->renderSuccess('', compact('model'));
        }
        $data = $this->postData();
        // 新增记录
        $supplier = $this->supplier['user'];
        $model = AssembleProductModel::detail($assemble_product_id);
        if ($model->edit($data)) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    /**
     * 删除
     */
    public function del($assemble_product_id)
    {
        $model = new AssembleProductModel();
        if ($model->remove($assemble_product_id, $this->getSupplierId())) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }
}