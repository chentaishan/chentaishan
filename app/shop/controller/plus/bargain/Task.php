<?php

namespace app\shop\controller\plus\bargain;

use app\shop\controller\Controller;
use app\shop\model\plus\bargain\Task as TaskModel;
use app\shop\model\plus\bargain\TaskHelp as TaskHelpModel;
/**
 * 砍价记录控制器
 */
class Task extends Controller
{
    /**
     * 砍价任务列表
     */
    public function index()
    {
        $model = new TaskModel;
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 砍价任务列表
     */
    public function help($bargain_task_id)
    {
        $list = TaskHelpModel::getList($bargain_task_id, $this->postData());
        return $this->renderSuccess('', compact('list'));
    }

}