<?php

namespace app\common\model\plus\bargain;

use app\common\model\BaseModel;
use app\common\library\helper;

/**
 * 砍价任务模型
 * Class Task
 * @package app\common\model\bargain
 */
class Task extends BaseModel
{
    protected $name = 'bargain_task';
    protected $pk = 'bargain_task_id';

    /**
     * 追加的字段
     * @var array $append
     */
    protected $append = [
        'is_end',   // 是否已结束
        'surplus_money',    // 剩余砍价金额
        'bargain_rate', // 砍价进度百分比(0-100)
        'end_time_text', //砍价结束时间格式化
    ];

    /**
     * 关联用户表
     * @return \think\model\relation\BelongsTo
     */
    public function user()
    {
        return $this->BelongsTo('app\\common\\model\\user\\User');
    }
    /**
     *关联活动
     */
    public function active()
    {
        return $this->belongsTo('app\\common\\model\\plus\\bargain\\Active', 'bargain_activity_id', 'bargain_activity_id');
    }

    /**
     * 关联文件库
     */
    public function file()
    {
        return $this->belongsTo('app\\common\\model\\file\\UploadFile', 'image_id', 'file_id')
            ->bind(['file_path', 'file_name', 'file_url']);
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
     * 获取器：活动是否已结束
     * @param $value
     * @param $data
     * @return false|string
     */
    public function getIsEndAttr($value, $data)
    {
        return $value ?: $data['end_time'] <= time();
    }

    /**
     * 获取器：剩余砍价金额
     * @param $value
     * @param $data
     * @return false|string
     */
    public function getSurplusMoneyAttr($value, $data)
    {
        $maxCutMoney = helper::bcsub($data['product_price'], $data['bargain_price']);
        return $value ?: helper::bcsub($maxCutMoney, $data['cut_money']);
    }

    /**
     * 获取器：砍价进度百分比
     * @param $value
     * @param $data
     * @return false|string
     */
    public function getBargainRateAttr($value, $data)
    {
        $maxCutMoney = helper::bcsub($data['product_price'], $data['bargain_price']);
        $rate =  helper::bcdiv($data['cut_money'], $maxCutMoney) * 100;
        return $value ?:  helper::number2($rate);
    }

    /**
     * 获取器：砍价金额区间
     * @param $value
     * @return mixed
     */
    public function getSectionAttr($value)
    {
        return json_decode($value, true);
    }

    /**
     * 修改器：砍价金额区间
     * @param $value
     * @return string
     */
    public function setSectionAttr($value)
    {
        return json_encode($value);
    }

    /**
     * 砍价任务详情
     */
    public static function detail($bargain_task_id, $with = ['user'])
    {
        return (new static())->with($with)->find($bargain_task_id);
    }



}