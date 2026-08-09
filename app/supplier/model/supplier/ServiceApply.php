<?php

namespace app\supplier\model\supplier;

use app\common\model\supplier\ServiceApply as ServiceApplyModel;

/**
 * 供应商服务保障申请模型
 */
class ServiceApply extends ServiceApplyModel
{
    //申请
    public function apply($data)
    {
        $detail = self::detail($data['service_security_id'], $data['shop_supplier_id']);
        if (isset($detail['status']) && $detail['status'] <= 1) {
            $this->error = "已经成功申请或者审核中";
            return false;
        }
        $data['app_id'] = self::$app_id;
        $this->startTrans();
        try {
            if ($detail) {
                $data['service_apply_id'] = $detail['service_apply_id'];
                $data['status'] = 0;
                $detail->save($data);
            } else {
                $this->save($data);
            }
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }


    }

    //退出
    public function quit()
    {

        if ($this['status'] != 1) {
            $this->error = "当前状态不允许退出";
            return false;
        }
        $this->save(['status' => 2]);
        return true;
    }

    public static function getStatus($value, $shop_supplier_id)
    {
        return (new static())->where('service_security_id', '=', $value)->where('shop_supplier_id', '=', $shop_supplier_id)->value('status');
    }
}