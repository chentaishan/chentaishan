<?php

namespace app\common\model\settings;

use app\common\service\settings\WithdrawalConfigService;

/**
 * 提现配置（数据存 jjjshop_cloud_ratio）
 */
class WithdrawalConfig
{
    /**
     * @return array{min_amount:string,service_fee_percent:string}|null
     */
    public static function detail($appId = null)
    {
        return WithdrawalConfigService::get();
    }
}
