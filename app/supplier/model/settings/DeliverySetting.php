<?php

namespace app\supplier\model\settings;

use app\common\model\settings\DeliverySetting as DeliverySettingModel;

/**
 * 电子面单设置
 */
class DeliverySetting extends DeliverySettingModel
{
    /**
     * 添加新记录
     */
    public function add($data, $shop_supplier_id)
    {
        $data['app_id'] = self::$app_id;
        $data['shop_supplier_id'] = $shop_supplier_id;
        return $this->save($data);
    }

    /**
     * 编辑记录
     */
    public function edit($data)
    {
        return $this->save($data);
    }

    /**
     * 删除记录
     */
    public function remove()
    {
        return $this->save(['is_delete' => 1]);
    }
}
