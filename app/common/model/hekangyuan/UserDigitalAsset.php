<?php

namespace app\common\model\hekangyuan;

use think\Model;

/**
 * 福满堂-用户数字资产持仓（按笔记账，复用香韵结构）
 * 表: jjjshop_user_digital_asset
 * 每笔兑换一条记录，卖出时递减 remain_qty
 */
class UserDigitalAsset extends Model
{
    protected $pk = 'asset_id';
    protected $name = 'user_digital_asset';
    protected $autoWriteTimestamp = false;

    // 子类型（福满堂固定底池币）
    const SUB_TYPE_POOL = 1;

    // 持有状态
    const STATUS_HOLD = 10;   // 持有中
    const STATUS_SOLD = 20;   // 已全部卖出
    const STATUS_FREEZE = 30; // 已冻结
}
