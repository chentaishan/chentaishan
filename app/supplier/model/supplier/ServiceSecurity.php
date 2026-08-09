<?php

namespace app\supplier\model\supplier;

use app\common\model\supplier\ServiceSecurity as ServiceSecurityModel;
/**
 * 供应商服务保障模型
 */
class ServiceSecurity extends ServiceSecurityModel
{
     public  function getList($shop_supplier_id)
    {	
    	$list = parent::getAll(['status'=>1]);
        foreach ($list as $key => &$value) {
        	$status = ServiceApply::getStatus($value['service_security_id'],$shop_supplier_id);
        	$value['status'] = (isset($status)&&$status<=1)?$status:2;
        }
        return $list;
    }
}