<?php

namespace app\common\model\plus\table;

use app\common\model\BaseModel;

/**
 * 万能表单模型
 */
class Record extends BaseModel
{
    protected $name = 'table_record';
    protected $pk = 'table_record_id';

    /**
     * 关联表单
     */
    public function tableM()
    {
        return $this->belongsTo('app\\common\\model\\plus\\table\\Table', 'table_id', 'table_id');
    }

    /**
     * 关联表单
     */
    public function user()
    {
        return $this->belongsTo('app\\common\\model\\user\\User', 'user_id', 'user_id');
    }

    /**
     * 获取详情
     */
    public static function detail($table_record_id)
    {
        return (new static())->find($table_record_id);
    }
}
