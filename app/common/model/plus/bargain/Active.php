<?php

namespace app\common\model\plus\bargain;

use app\common\model\BaseModel;

/**
 * 砍价模型
 * @package app\common\model\plus\bargain
 */
class Active extends BaseModel
{
    protected $name = 'bargain_activity';
    protected $pk = 'bargain_activity_id';

    protected $append = ['status_text', 'start_time_text', 'end_time_text', 'join_status', 'join_end_time_text'];

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

    /**
     *关联商品表
     */
    public function product()
    {
        return $this->hasMany('app\\common\\model\\plus\\bargain\\BargainProduct', 'bargain_id', 'bargain_id');
    }


    /**
     *关联图片
     */
    public function file()
    {
        return $this->hasOne('app\\common\\model\\file\\UploadFile', 'file_id', 'image_id');
    }


    /**
     * 砍价活动详情
     */
    public static function detail($bargain_activity_id, $with = [])
    {
        return (new static())->with($with)->find($bargain_activity_id);
    }

    /**
     * 处理过的详情数据
     */
    public static function detailWithTrans($bargain_activity_id)
    {
        $model = (new static())->with(['file'])->where('bargain_activity_id', '=', $bargain_activity_id)->find();
        $detail = [];
        if ($model) {
            $detail = [
                'title' => $model['title'],
                'image_id' => $model['image_id'],
                'file_path' => $model['file']['file_path'],
                'sort' => $model['sort'],
                'is_delete' => $model['is_delete'],
                'conditions' => $model['conditions'],
                'together_time' => $model['together_time'],
                'status' => $model['status'],
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
}