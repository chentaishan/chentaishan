<?php

namespace app\shop\controller\plus\advance;

use app\shop\controller\Controller;
use app\shop\model\plus\advance\Product as AdvanceProductModel;
use app\common\model\product\Product as ProductModel;
use app\shop\service\ProductService;

/**
 * 活动控制器
 */
class Product extends Controller
{
    /**
     * 新增活动会场
     */
    public function index()
    {
        $model = new AdvanceProductModel();
        $list = $model->getList($this->postData());
        //获取排除id
        $exclude_ids = $model->getExcludeIds();
        $exclude_ids = array_column($exclude_ids, 'product_id');
        return $this->renderSuccess('', compact('list', 'exclude_ids'));
    }

    /**
     *修改商品
     */
    public function edit($advance_product_id)
    {
        $model = AdvanceProductModel::detail($advance_product_id, ['product.image.file', 'sku']);
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
        $model = new AdvanceProductModel();
        if ($model->del($id)) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError('删除失败');
    }

    /**
     *获取diy秒删商品
     */
    public function getDiyProduct()
    {
        $model = new AdvanceProductModel;
        $list = $model->getDiyProduct();
        return $this->renderSuccess('', compact('list'));
    }
}