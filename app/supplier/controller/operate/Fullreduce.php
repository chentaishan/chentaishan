<?php

namespace app\supplier\controller\operate;

use app\supplier\controller\Controller;
use app\supplier\model\plus\product\Reduce as ReduceModel;
use app\common\model\product\Category as CategoryModel;

/**
 * 满减
 */
class Fullreduce extends Controller
{

    /**
     *商品满减
     */
    public function product()
    {
        $model = new ReduceModel;
        $list = $model->getList($this->postData(), $this->getSupplierId());
        // 商品分类
        $category = CategoryModel::getCacheTree();
        return $this->renderSuccess('', compact('list', 'category'));
    }

    /**
     *商品满减
     */
    public function editProduct($product_id)
    {
        $model = new ReduceModel;
        // 更新记录
        if ($model->edit($this->postData(), $this->getSupplierId())) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }
}