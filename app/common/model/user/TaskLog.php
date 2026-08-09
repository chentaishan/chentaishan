<?php

namespace app\common\model\user;

use app\common\model\BaseModel;

/**
 * 用户任务记录模型
 */
class TaskLog extends BaseModel
{
    protected $name = 'user_task_log';
    protected $pk = 'log_id';

    /**
     * 关联会员记录表
     */
    public function user()
    {
        return $this->belongsTo('app\\common\\model\\user\\User', 'user_id', 'user_id');
    }
}