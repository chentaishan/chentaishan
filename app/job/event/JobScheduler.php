<?php

namespace app\job\event;

use app\common\model\app\App as AppModel;
use app\common\service\activity\PartnerDividendDailyService;

/**
 * 订单事件管理
 */
class JobScheduler
{

    /**
     * 执行函数
     */
    public function handle()
    {
        // 查找所有appid
        $appList = AppModel::getAll();
        // 涉及到应用单独配置的，循环执行
        foreach ($appList as $app){
            // 订单任务
            event('Order', $app['app_id']);
        }
        // 预售订单
        // event('AdvanceOrder');
        // // 拼团任务
        // event('AssembleBill');
        // // 砍价任务
        // event('BargainTask');
        // // 用户优惠券
        // event('UserCoupon');
        // // 分销商订单
        // event('AgentOrder');
        // // 直播间管理
        // event('LiveRoom');
        return true;
    }

    /**
     * 代理进货区合伙人每日分红（幂等由 partner_dividend_daily_batch 表判断）
     */
    private function runPartnerDividendDaily(int $appId): void
    {
        if (!config('wz_reward.partner_dividend.enabled', true)) {
            return;
        }
        try {
            (new PartnerDividendDailyService())->runForApp($appId);
        } catch (\Throwable $e) {
            log_write('PARTNER_DIVIDEND app_id=' . $appId . ' ' . $e->getMessage(), 'task');
        }
    }

    /**
     * 【测试】合伙人每日分红：收货截止为当前调度时刻（非当日0点）
     */
    private function runPartnerDividendDailyTest(int $appId): void
    {
        if (!config('wz_reward.partner_dividend.test_enabled', false)) {
            return;
        }
        if (!config('wz_reward.partner_dividend.enabled', true)) {
            return;
        }
        try {
            (new PartnerDividendDailyService())->runForApp($appId, null, true, false);
        } catch (\Throwable $e) {
            log_write('PARTNER_DIVIDEND_TEST app_id=' . $appId . ' ' . $e->getMessage(), 'task');
        }
    }

}
