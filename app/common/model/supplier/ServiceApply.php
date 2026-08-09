<?php


namespace app\common\model\supplier;

use app\common\model\BaseModel;

/**
 * 供应商服务保障申请模型
 */
class ServiceApply extends BaseModel
{
    protected $pk = 'service_apply_id';
    protected $name = 'supplier_service_apply';

    /**
     * 详情
     */
    public static function detail($service_security_id, $shop_supplier_id)
    {
        return (new static())->where('service_security_id', '=', $service_security_id)->where('shop_supplier_id', '=', $shop_supplier_id)->find();
    }

    /**
     * 关联供应商表
     */
    public function supplier()
    {
        return $this->belongsTo('app\\common\\model\\supplier\\Supplier', 'shop_supplier_id', 'shop_supplier_id');
    }

    /**
     * 关联服务
     */
    public function server()
    {
        return $this->hasOne('app\\common\\model\\supplier\\ServiceSecurity', 'service_security_id', 'service_security_id');
    }
}