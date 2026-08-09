<?php

namespace app\supplier\service;

use app\common\model\plus\agent\Setting as AgentSetting;
use app\common\model\settings\Setting;
use app\common\service\product\BaseProductService;
use app\shop\model\product\Category as CategoryModel;
use app\shop\model\settings\Delivery as DeliveryModel;
use app\supplier\model\supplier\Supplier as SupplierModel;

/**
 * 商品服务类
 */
class ProductService extends BaseProductService
{
    /**
     * 商品管理公共数据
     */
    public static function getEditData($model, $scene, $shop_supplier_id)
    {
        // 商品分类
        $category = CategoryModel::getCacheTree();
        // 配送模板
        $delivery = DeliveryModel::getAll($shop_supplier_id);
        // 商品sku数据
        $specData = static::getSpecData($model);
        // 平台分销规则
        $agentSetting = AgentSetting::getItem('commission');
        $basicSetting = AgentSetting::getItem('basic');
        // 商品规格是否锁定
        $isSpecLocked = static::checkSpecLocked($model, $scene);
        // 商品是否开启平台审核
        $audit_setting = Setting::getItem('store');
        // 配送方式
        $logistics = (new SupplierModel())->getLogistics($shop_supplier_id);
        return compact('category', 'delivery', 'specData', 'agentSetting', 'basicSetting', 'isSpecLocked', 'audit_setting', 'logistics');
    }
}