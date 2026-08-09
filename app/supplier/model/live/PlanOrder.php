<?php

namespace app\supplier\model\live;

use app\common\model\plus\live\PlanOrder as PlanOrderModel;

/**
 * 充值模型
 */
class PlanOrder extends PlanOrderModel
{
    /**
     * 列表
     */
    public function getList($params)
    {
        return $this->with(['user'])->order(['create_time' => 'desc'])
            ->paginate($params);
    }

    /**
     * 添加新记录
     */
    public function add($data)
    {
        $data['app_id'] = self::$app_id;
        return $this->save($data);
    }

    /**
     * 更新记录
     */
    public function edit($data)
    {
        return $this->save($data);
    }

    /**
     * 删除记录 (软删除)
     */
    public function setDelete($where)
    {
        return self::update(['is_delete' => 1], $where);
    }
}
