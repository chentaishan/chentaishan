<?php

namespace app\api\controller\product;

use app\api\model\product\Category as CategoryModel;
use app\api\controller\Controller;
use app\api\model\product\Product as ProductModel;
use app\api\model\settings\Setting as SettingModel;
use app\common\model\page\PageCategory as PageCategoryModel;
use app\api\model\order\Cart as CartModel;

/**
 * 商品分类控制器
 */
class Category extends Controller
{
    /**
     * 分类页面
     */
    public function index()
    {
        // 分类模板
        $template = PageCategoryModel::detail();
        // 商品分类列表
        $list = array_values(CategoryModel::getShowCacheTree());
        return $this->renderSuccess('', compact('template', 'list'));
    }

    /**
     * 购物车列表
     */
    public function lists()
    {
        // 购物车商品列表
        $productList = (new CartModel)->getList($this->getUser(false));
        if ($productList) {
            // 会员价
            $product_model = new ProductModel();
            foreach ($productList as $supplier) {
                foreach ($supplier['productList'] as $product) {
                    $product_model->setProductGradeMoney($this->getUser(), $product);
                }
            }
        }
        return $this->renderSuccess('', compact('productList'));
    }

}