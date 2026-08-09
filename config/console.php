<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        // 定时任务
        'job' => \app\job\command\Job::class,
        // 代理进货区合伙人每日分红
        'partner_dividend_daily' => \app\job\command\PartnerDividendDaily::class,
        // 代理进货区合伙人每日分红（测试：截止时间为当前执行时刻）
        'partner_dividend_daily_test' => \app\job\command\PartnerDividendDailyTest::class,
        // 跨版本漏奖订单筛查/补发
        'repair_missed_rewards' => \app\job\command\RepairMissedRewards::class,
        // 福满堂消费券公共池每日发放
        'hekangyuan_voucher_dividend_daily' => \app\job\command\HekangyuanVoucherDividendDaily::class,
        //团队分红
        'team_dividend_daily' => \app\job\command\TeamDividendDaily::class,
    ],
];
