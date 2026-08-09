<?php

namespace app\common\model\plus\seckill;

use app\common\model\BaseModel;

/**
 * 参与记录模型
 */
class Active extends BaseModel
{
    protected $name = 'seckill_activity';
    protected $pk = 'seckill_activity_id';
    //附加字段
    protected $append = ['status_text', 'start_time_text','end_time_text', 'join_status', 'join_end_time_text'];

    /**
     * 有效期-开始时间
     * @param $value
     * @return array
     */
    public function getStartTimeTextAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['start_time']);
    }

    /**
     * 有效期-开始时间
     * @param $value
     * @return array
     */
    public function getEndTimeTextAttr($value, $data)
    {
        return date('Y-m-d H:i:s', $data['end_time']);
    }

    /**
     * 有效期-开始时间
     * @param $value
     * @return array
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
        if($data['status'] == 0){
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
        if($data['status'] == 0){
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
    /**
     * 处理过的详情数据
     */
    public static function detailWithTrans($seckill_activity_id)
    {
        $model = (new static())->with(['file'])->where('seckill_activity_id', '=', $seckill_activity_id)->find();
        $detail = [
            'title' => $model['title'],
            'image_id' => $model['image_id'],
            'status' => $model['status'],
            'sort' => $model['sort'],
            'start_time' => $model['start_time'],
            'end_time' => $model['end_time'],
            'file_path' => $model['file']['file_path'],
            'join_end_time' => date('Y-m-d H:i:s', $model['join_end_time']),
            'active_date' => [
                date('Y-m-d H:i:s', $model['start_time']),
                date('Y-m-d H:i:s', $model['end_time']),
            ],
            'active_time' => [
               $model['day_start_time'],
               $model['day_end_time'],
            ]
        ];

        return $detail;
    }

    public static function detail($seckill_activity_id)
    {
        return (new static())->find($seckill_activity_id);
    }

    public function file()
    {
        return $this->belongsTo('app\\common\\model\\file\\UploadFile', 'image_id', 'file_id');
    }

    public function seckillProduct()
    {
        return $this->hasMany('app\\common\\model\\plus\\seckill\\Product', 'seckill_activity_id', 'seckill_activity_id');
    }


}