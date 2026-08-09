<?php

namespace app\shop\model\settings;

use app\common\model\settings\DeliveryTemplate as DeliveryTemplateModel;

/**
 * 电子面单模板
 */
class DeliveryTemplate extends DeliveryTemplateModel
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
