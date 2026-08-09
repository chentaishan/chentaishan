<?php

namespace app\common\model\plus\lottery;

use app\common\model\BaseModel;
use app\common\model\settings\Region as RegionModel;

/**
 * Class GiftPackage
 * 记录模型
 * @package app\common\model\plus\giftpackage
 */
class Record extends BaseModel
{
    protected $name = 'lottery_record';
    protected $pk = 'record_id';
    /**
     * 追加字段
     * @var string[]
     */
    protected $append = ['status_text', 'lottery_type_text', 'region'];

    /**
     * 地区名称
     */
    public function getRegionAttr($value, $data)
    {
        return [
            'province' => RegionModel::getNameById($data['province_id']),
            'city' => RegionModel::getNameById($data['city_id']),
            'region' => $data['region_id'] == 0 ? '' : RegionModel::getNameById($data['region_id']),
        ];
    }

    /**
     * 礼包详情
     */
    public static function detail($record_id, $with = [])
    {
        return (new static())->with($with)->find($record_id);
    }

    /**
     * 状态
     */
    public function getStatusTextAttr($value, $data)
    {
        $text = '';
        if ($data['status'] == 1) {
            $text = '已使用';
        } else {
            $text = '未使用';
        }
        return $text;
    }

    /**
     * 状态
     */
    public function getLotteryTypeTextAttr($value, $data)
    {
        $type = $this->getLotteryType();
        return $type[$data['prize_type']];
    }

    /**
     * 关联会员
     */
    public function user()
    {
        return $this->belongsTo('app\\common\\model\\user\\User', 'user_id', 'user_id')->bind(['nickName', 'avatarUrl', 'mobile']);
    }

    /**
     * 关联物流公司表
     * @return \think\model\relation\BelongsTo
     */
    public function express()
    {
        return $this->belongsTo('app\\common\\model\\settings\\Express', 'express_id', 'express_id');
    }

    /**
     * 奖品类型
     */
    public function getLotteryType()
    {
        $data = [0 => '无礼品', 1 => '优惠券', 2 => '积分', 3 => '商品', 4 => '余额'];
        return $data;
    }
}