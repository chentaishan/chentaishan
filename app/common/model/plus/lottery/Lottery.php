<?php

namespace app\common\model\plus\lottery;

use app\common\model\BaseModel;

/**
 * Class GiftPackage
 * 转盘模型
 * @package
 */
class Lottery extends BaseModel
{
    protected $name = 'lottery';
    protected $pk = 'lottery_id';
    /**
     * 追加字段
     * @var string[]
     */
    protected $append = ['status_text'];

    /**
     * 用户等级ID
     */
    public function setGradesAttr($value)
    {
        return $value ? implode(',', $value) : '';
    }

    /**
     * 开始时间
     */
    public function setStartTimeAttr($value)
    {
        return $value ? strtotime($value) : 0;
    }

    /**
     * 结束时间
     */
    public function setEndTimeAttr($value)
    {
        return $value ? strtotime($value) : 0;
    }

    /**
     * 开始时间
     */
    public function getStartTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i:s', $value) : '';
    }

    /**
     * 结束时间
     */
    public function getEndTimeAttr($value)
    {
        return $value ? date('Y-m-d H:i:s', $value) : '';
    }

    /**
     * 用户等级ID
     */
    public function getGradesAttr($value)
    {
        return $value ? explode(',', $value) : [];
    }

    /**
     * 转盘详情
     */
    public static function detail()
    {
        return (new static())->with(['image'])->find();
    }

    /**
     * 状态
     */
    public function getStatusTextAttr($value, $data)
    {
        $text = '';
        if ($value == 1) {
            $text = '开启';
        } else {
            $text = '关闭';
        }
        return $text;
    }
    /**
     * 关联奖项
     */
    public function prize()
    {
        return $this->hasMany('app\\common\\model\\plus\\lottery\\LotteryPrize', 'lottery_id', 'lottery_id');
    }
    /**
     * 关联文件库
     */
    public function image()
    {
        return $this->belongsTo('app\\common\\model\\file\\UploadFile', 'image_id', 'file_id')
            ->bind(['file_path']);
    }
}