<?php

namespace app\shop\service;

use app\common\model\plus\agent\Setting as AgentSetting;
use app\common\service\product\BaseProductService;
use app\shop\model\product\Category as CategoryModel;
use app\shop\model\settings\Delivery as DeliveryModel;
use app\shop\model\user\Grade as GradeModel;
use app\shop\model\supplier\Supplier as SupplierModel;

/**
 * 商品服务类
 */
class ProductService extends BaseProductService
{
    /**
     * 商品管理公共数据
     */
    public static function getEditData($model = null, $scene = 'edit')
    {
        // 商品分类
        $category = CategoryModel::getCacheTree();
        // 配送模板,仅仅查当前模板
        $delivery = [];
        if ($model) {
            $delivery = [DeliveryModel::detail($model['delivery_id'])];
        }
        // 会员等级列表
        $gradeList = GradeModel::getUsableList();
        // 商品sku数据
        $specData = static::getSpecData($model);
        // 商品规格是否锁定
        $isSpecLocked = static::checkSpecLocked($model, $scene);
        // 平台分销规则
        $agentSetting = AgentSetting::getItem('commission');
        $basicSetting = AgentSetting::getItem('basic');
        // 配送方式
        $logistics = (new SupplierModel())->getLogistics($model['shop_supplier_id']);
        return compact('category', 'delivery', 'gradeList', 'specData', 'isSpecLocked', 'agentSetting', 'basicSetting', 'logistics');
    }
}