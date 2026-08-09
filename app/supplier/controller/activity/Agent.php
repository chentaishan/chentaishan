<?php

namespace app\supplier\controller\activity;

use app\common\library\helper;
use app\common\model\plus\agent\Setting as AgentSetting;
use app\common\model\plus\agent\Setting as AgentSettingModel;
use app\supplier\controller\Controller;
use app\shop\model\plus\agent\Grade as AgentGradeModel;
use app\supplier\model\product\Product as ProductModel;
use app\shop\service\ProductService;
use app\supplier\model\plus\agent\Product as AgentProductModel;
use app\common\model\product\Category as CategoryModel;

/**
 * 商品运营控制器
 */
class Agent extends Controller
{
    /**
     * 商品列表
     */
    public function product()
    {
        $model = new ProductModel;
        $list = $model->getList(array_merge(['status' => -1, 'shop_supplier_id' => $this->getSupplierId()], $this->postData()));
        // 商品分类
        $category = CategoryModel::getCacheTree();
        return $this->renderSuccess('', compact('list', 'category'));
    }

    /**
     * 编辑分销商
     */
    public function edit($product_id)
    {
        $model = new AgentProductModel();
        if ($this->request->isGet()) {
            // 平台分销规则
            $basicSetting = AgentSetting::getItem('basic');
            $agent_product = AgentProductModel::detail($product_id);
            return $this->renderSuccess('', compact('agent_product', 'basicSetting'));
        }
        if ($model->edit($this->postData(),$this->getSupplierId())) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    /**
     * 设置状态
     */
    public function setAgent($productIds, $is_agent)
    {
        $model = new AgentProductModel();
        if ($model->setAgent($productIds, $is_agent)) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }
}