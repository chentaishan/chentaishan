<?php

namespace app\common\model\plus\lottery;

use app\common\model\BaseModel;

/**
 * Class GiftPackage
 * 转盘奖项模型
 * @package app\common\model\plus\giftpackage
 */
class LotteryPrize extends BaseModel
{
    protected $name = 'lottery_prize';
    protected $pk = 'prize_id';
    /**
     * 追加字段
     * @var string[]
     */
    protected $append = ['status_text'];

    /**
     * 转盘详情
     */
    public static function detail($lottery_id)
    {
        return (new static())->where('status', '=', 10)->where('lottery_id', '=', $lottery_id)->select();
    }

    /**
     * 奖项下架列表
     */
    public static function getlist($data, $lottery)
    {
        if (!$lottery) {
            return [];
        }
        return (new static())->where('status', '=', 20)
            ->where('lottery_id', '=', $lottery['lottery_id'])
            ->order('prize_id desc')
            ->paginate($data);
    }

    /**
     * 状态
     */
    public function getStatusTextAttr($value, $data)
    {
        $text = '';
        if ($value == 10) {
            $text = '上架';
        } else {
            $text = '下架';
        }
        return $text;
    }
}