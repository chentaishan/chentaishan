<?php

namespace app\supplier\controller\product;

use app\supplier\controller\Controller;
use app\common\model\product\Category as CategoryModel;

/**
 * 商品分类
 */
class Category extends Controller
{
    /**
     * 商品分类列表
     */
    public function index()
    {
        $model = new CategoryModel;
        $list = $model->getShowCacheTree();
        return $this->renderSuccess('', compact('list'));
    }


}
