<?php

namespace app\supplier\model\supplier;

use app\common\model\supplier\Service as ServiceModel;
use app\supplier\model\supplier\Supplier as SupplierModel;
use app\supplier\model\supplier\User as SupplierUserModel;
use app\supplier\model\user\User as UserModel;
use app\common\exception\BaseException;

/**
 * 供应商客服模型
 */
class Service extends ServiceModel
{
    /**
     * 保存
     */
    public static function saveService($shop_supplier_id, $data)
    {
        $model = static::detail($shop_supplier_id);
        if (!$model) {
            $model = new static();
            $data['shop_supplier_id'] = $shop_supplier_id;
            $data['app_id'] = self::$app_id;
        }
        return $model->save($data);
    }
}