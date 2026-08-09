<?php

namespace app\common\model\plus\assemble;

use app\common\model\BaseModel;

/**
 * 参与记录模型
 */
class Active extends BaseModel
{
    protected $name = 'assemble_activity';
    protected $pk = 'assemble_activity_id';

    //附加字段
    protected $append = ['status_text', 'start_time_text', 'end_time_text', 'join_end_time_text', 'join_status'];

    /**
     * 有效期-开始时间
     */
    public function getStartTimeTextAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['start_time']);
    }

    /**
     * 有效期-开始时间
     */
    public function getEndTimeTextAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['end_time']);
    }

    /**
     * 报名截止日期
     */
    public function getJoinEndTimeTextAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['join_end_time']);
    }

    /**
     * 状态
     * @param $val
     * @return string
     */
    public function getStatusTextAttr($value, $data)
    {
        if ($data['status'] == 0) {
            return '未生效';
        }
        if ($data['start_time'] > time()) {
            return '未开始';
        }
        if ($data['end_time'] < time()) {
            return '已结束';
        }
        if ($data['start_time'] < time() && $data['end_time'] > time()) {
            return '生效-进行中';
        }
        return '';
    }

    /**
     * 状态
     * @param $val
     * @return string
     */
    public function getJoinStatusAttr($value, $data)
    {
        if ($data['status'] == 0) {
            return 0;
        }
        if ($data['end_time'] < time()) {
            return 0;
        }
        if ($data['join_end_time'] < time()) {
            return 0;
        }

        return 1;
    }

    public static function detail($assemble_activity_id)
    {
        return (new static())->with(['file'])->where('assemble_activity_id', '=', $assemble_activity_id)->find($assemble_activity_id);
    }

    /**
     * 处理过的详情数据
     */
    public static function detailWithTrans($assemble_activity_id)
    {
        $model = (new static())->with(['file'])->where('assemble_activity_id', '=', $assemble_activity_id)->find();
        $detail = [];
        if ($model) {
            $detail = [
                'title' => $model['title'],
                'image_id' => $model['image_id'],
                'file_path' => $model['file']['file_path'],
                'status' => $model['status'],
                'fail_type' => $model['fail_type'],
                'is_single' => $model['is_single'],
                'sort' => $model['sort'],
                'is_delete' => $model['is_delete'],
                'together_time' => $model['together_time'],
                'start_time' => $model['start_time'],
                'end_time' => $model['end_time'],
                'join_end_time' => date('Y-m-d H:i:s', $model['join_end_time']),
                'active_time' => [
                    date('Y-m-d H:i:s', $model['start_time']),
                    date('Y-m-d H:i:s', $model['end_time']),
                ],
            ];
        }
        return $detail;
    }

    public function file()
    {
        return $this->belongsTo('app\\common\\model\\file\\UploadFile', 'image_id', 'file_id');
    }

    public function assembleProduct()
    {
        return $this->hasMany('Product', 'assemble_activity_id', 'assemble_activity_id');
    }

}