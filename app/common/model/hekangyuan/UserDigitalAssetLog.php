<?php

namespace app\common\model\hekangyuan;

use think\Model;

/**
 * 福满堂-用户数字资产流水（每次买/卖一条，复用香韵结构）
 * 表: jjjshop_user_digital_asset_log
 */
class UserDigitalAssetLog extends Model
{
    protected $pk = 'log_id';
    protected $name = 'user_digital_asset_log';
    protected $autoWriteTimestamp = false;
    protected $append = ['action_text'];

    // 动作
    const ACTION_BUY = 10;   // 买入(消费券兑换)
    const ACTION_SELL = 20;  // 卖出(进余额)

    public function getActionTextAttr($value, $data)
    {
        $map = [
            self::ACTION_BUY  => '买入',
            self::ACTION_SELL => '卖出',
        ];
        return $map[$data['action']] ?? '';
    }
}
