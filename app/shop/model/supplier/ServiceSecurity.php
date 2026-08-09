<?php

namespace app\shop\model\supplier;

use app\common\model\supplier\ServiceSecurity as ServiceSecurityModel;
/**
 * 供应商服务保障模型
 */
class ServiceSecurity extends ServiceSecurityModel
{
    
    /**
     * 添加新记录
     */
    public function add($data)
    {
        $data['app_id'] = self::$app_id;
        return $this->save($data);
    }

    /**
     * 编辑记录
     */
    public function edit($data)
    {
      
        $data['update_time'] = time();
        return $this->save($data);
    }

    /**
     * 删除
     */
    public function remove()
    {
        // 判断是否存在
        $Count = (new ServiceApply())->where('service_security_id','=' ,$this['service_security_id'])->where('status','in','1,2')->count();
        if ($Count > 0) {
            $this->error = '该服务使用中，不允许删除';
            return false;
        }
        return $this->delete();
    }

 
}