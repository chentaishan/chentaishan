<?php

namespace app\supplier\controller\activity;

use app\common\model\product\Product as ProductModel;
use app\supplier\controller\Controller;
use app\supplier\model\plus\point\Product as PointProductModel;
/**
 * 积分活动控制器
 */
class Point extends Controller
{
    /**
     * 添加
     */
    public function add($product_id)
    {
        if($this->request->isGet()){
            $model = ProductModel::detail($product_id);
            return $this->renderSuccess('', compact('model'));
        }
        $model = new PointProductModel();
        if ($model->checkProduct($product_id)) {
             return $this->renderError('商品已经参加活动了，请勿重复提交');
        }
        if ($model->saveProduct($this->getSupplierId(), $this->postData())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    /**
     * 列表
     */
    public function index()
    {
        $supplier = $this->supplier['user'];
        $list = (new PointProductModel())->getList($supplier['shop_supplier_id'], $this->postData());
        //排除id
        $exclude_ids = (new PointProductModel())->getExcludeIds($this->getSupplierId());
        return $this->renderSuccess('', compact('list', 'exclude_ids'));
    }

    /**
     * 编辑
     */
    public function edit($point_product_id)
    {
        if($this->request->isGet()){
            //商品详情
            $model = PointProductModel::detail($point_product_id, ['product' => ['image.file'],'sku']);
            return $this->renderSuccess('', compact('model'));
        }
        $data = $this->postData();
        $model = PointProductModel::detail($point_product_id);
        if ($model->edit($data)) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?:'添加失败');
    }

    /**
     * 删除
     */
    public function del($point_product_id)
    {
        $model = PointProductModel::detail($point_product_id);
        if ($model->remove($point_product_id, $this->getSupplierId())) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model->getError() ?: '删除失败');
    }
}