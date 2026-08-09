<?php

namespace app\common\model\plus\agent;

use app\common\model\BaseModel;

/**
 * 用户会员等级变更记录模型
 */
class GradeLog extends BaseModel
{
    protected $name = 'agent_grade_log';
    protected $pk = 'log_id';
    protected $updateTime = false;

    /**
     * 关联会员记录表
     */
    public function agent()
    {
        return $this->belongsTo('app\\common\\model\\plus\\agent\\User', 'user_id', 'user_id');
    }

    /**
     * 关联会员记录表
     */
    public function oldGrade()
    {
        return $this->belongsTo('app\\common\\model\\plus\\agent\\Grade', 'old_grade_id', 'grade_id');
    }

    /**
     * 关联会员记录表
     */
    public function grade()
    {
        return $this->belongsTo('app\\common\\model\\plus\\agent\\Grade',  'new_grade_id', 'grade_id');
    }

    public function getList($params)
    {
        return $this->with(['agent', 'oldGrade', 'grade'])
            ->order(['create_time' => 'desc'])
            ->paginate($params);
    }
}