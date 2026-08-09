<?php

namespace app\shop\model\plus\bargain;

use app\common\model\plus\bargain\Task as TaskModel;

/**
 * Class BargainProduct
 * 砍价商品模型
 * @package app\shop\model\plus\bargain
 */
class Task extends TaskModel
{
    /**
     *列表
     */
    public function getList($params)
    {
        $model = $this;
        if(isset($params['search']) && !empty($params['search'])){
            $model = $model->where('user.nickname|task.product_name', 'like', '%' . trim($params['search']) . '%');
        }
        return $model->alias('task')->with(['user','file'])
            ->join('user user', 'user.user_id = task.user_id', 'left')
            ->order('task.create_time', 'desc')
            ->paginate($params);
    }

}