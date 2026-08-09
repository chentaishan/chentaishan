<?php

namespace app\shop\controller\plus\agent;

use app\common\library\helper;
use app\common\model\plus\agent\Setting as AgentSetting;
use app\common\model\plus\agent\Setting as AgentSettingModel;
use app\shop\controller\Controller;
use app\shop\model\plus\agent\Grade as AgentGradeModel;
use app\shop\model\product\Product as ProductModel;
use app\shop\service\ProductService;
use app\shop\model\plus\agent\Product as AgentProductModel;
use app\shop\model\product\Category as CategoryModel;

/**
 * 商品运营控制器
 */
class Product extends Controller
{
    /**
     * 商品列表
     */
    public function index()
    {
        $model = new ProductModel;
        $list = $model->getList(array_merge(['status' => -1, 'is_agent' => 1], $this->postData()));
        // 商品分类
        $category = CategoryModel::getCacheTree();
        return $this->renderSuccess('', compact('list', 'category'));
    }

    /**
     * 详情
     */
    public function detail($product_id)
    {
        // 平台分销规则
        $basicSetting = AgentSetting::getItem('basic');
        $agent_product = AgentProductModel::detail($product_id);
        return $this->renderSuccess('', compact('agent_product', 'basicSetting'));

    }
}