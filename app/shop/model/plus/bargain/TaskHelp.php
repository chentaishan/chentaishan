<?php

namespace app\shop\model\plus\bargain;

use app\common\model\plus\bargain\TaskHelp as TaskHelpModel;

/**
 * Class BargainProduct
 * 砍价商品模型
 * @package app\shop\model\plus\bargain
 */
class TaskHelp extends TaskHelpModel
{
    /**
     *列表
     */
    public static function getList($bargain_task_id, $params)
    {
        return (new static())->with(['user'])
            ->where('bargain_task_id', '=', $bargain_task_id)
            ->order('create_time', 'asc')
            ->paginate($params);
    }

}