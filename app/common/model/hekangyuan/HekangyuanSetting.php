<?php

namespace app\common\model\hekangyuan;

use think\Model;

/**
 * 福满堂数字资产币配置（承载全局唯一数字资产币状态，按 app 隔离）
 *   init_price      币初始价（流通量为0时回退）
 *   pool_amount     币底池金额（买卖自动维护）
 *   total_asset     币流通总量（买卖自动维护）
 *   yesterday_price 币昨日价（每日00:00快照）
 *   in_pool_ratio/asset_ratio/sell_ratio/max_growth_multiple 买卖系数
 *   min_exchange/max_exchange 单笔兑换消费券数量上下限（0不限）
 */
class HekangyuanSetting extends Model
{
    protected $pk = 'setting_id';
    protected $name = 'hekangyuan_setting';
    protected $autoWriteTimestamp = false;

    /**
     * 获取配置（不存在则创建默认）
     */
    public static function getSetting($app_id)
    {
        $setting = self::where('app_id', '=', $app_id)->find();
        if (empty($setting)) {
            $now = time();
            $setting = self::create([
                'app_id'              => $app_id,
                'init_price'          => '0.10000',
                'in_pool_ratio'       => '30.00',
                'asset_ratio'         => '70.00',
                'sell_ratio'          => '70.00',
                'max_growth_multiple' => '0.00',
                'pool_amount'         => '0.00000',
                'total_asset'         => '0.00000',
                'yesterday_price'     => '0.00000',
                'min_exchange'        => '0.00',
                'max_exchange'        => '0.00',
                'is_open'             => 1,
                'create_time'         => $now,
                'update_time'         => $now,
            ]);
        }
        return $setting;
    }
}
