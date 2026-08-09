<?php

namespace app\api\controller\plus\task;

use app\api\controller\Controller;
use app\api\model\settings\Setting as SettingModel;
use app\api\model\user\TaskLog as TaskLogModel;

/**
 * 任务中心控制器
 */
class Task extends Controller
{
    /**
     * 任务中心数据
     */
    public function index()
    {
        $user = $this->getUser();   // 用户信息
        //获取任务设置
        $data = (new TaskLogModel)->getTask($user);
        return $this->renderSuccess('', compact('data'));
    }

    /**
     * 完成日常任务
     */
    public function dayTask($task_type)
    {
        $user = $this->getUser();
        $data['task_type'] = $task_type;
        $data['user_id'] = $user['user_id'];
        event('UserTask', $data);
        return $this->renderSuccess('');
    }

}