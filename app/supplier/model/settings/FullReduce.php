<?php

namespace app\supplier\model\settings;

use app\common\model\shop\FullReduce as FullReduceModel;

/**
 * 满减模型
 */
class FullReduce extends FullReduceModel
{
    /**
     * 获取列表记录
     */
    public function getList($data)
    {
        return $this->where('is_delete', '=', 0)
            ->where('shop_supplier_id', $data['shop_supplier_id'])
            ->where('product_id', '=', 0)
            ->order(['create_time' => 'asc'])
            ->paginate($data);
    }

    /**
     * 新增记录
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
     * 软删除
     */
    public function setDelete()
    {
        return $this->save(['is_delete' => 1]);
    }

}