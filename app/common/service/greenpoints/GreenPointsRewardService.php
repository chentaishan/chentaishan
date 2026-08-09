<?php

namespace app\common\service\greenpoints;

use app\common\service\activity\ActivityRewardService;
use app\common\service\activity\WzNormalZoneProductService;
use app\common\service\activity\WzRewardRemarkHelper;
use think\facade\Db;

/**
 * 消费区绿色积分：支付成功登记待结算，确认收货释放入账（activity_reward_log）
 */
class GreenPointsRewardService
{
    const SCENE_GREEN_POINTS = 'green_points_gift';
    const ASSET_GREEN_POINTS = 'green_points';
    const TRIGGER_RECEIPT    = 'receipt';

    private ActivityRewardService $rewardService;

    public function __construct(?ActivityRewardService $rewardService = null)
    {
        $this->rewardService = $rewardService ?: new ActivityRewardService();
    }

    public function onPaySuccess(array $order): void
    {
        $this->registerPendingReward($order, 'pay');
    }

    public function onOrderCompleted(array $order): void
    {
        $this->registerPendingReward($order, 'receipt');
    }

    private function registerPendingReward(array $order, string $phase): void
    {
        if (!$this->isEnabled($order)) {
            return;
        }
        $orderId = (int)$order['order_id'];
        $userId  = (int)$order['user_id'];
        $appId   = (int)($order['app_id'] ?? 0);
        $amount  = GreenPointsService::calcOrderGreenPointsAmount($order);
        if ($amount <= 0) {
            $this->mlog("[绿色积分|{$phase}] order_id={$orderId} 无赠送额度");
            return;
        }

        $this->rewardService->createPendingRewardLogPublic([
            'unique_key'      => 'wz_green_points_' . $orderId . '_' . $userId,
            'user_id'         => $userId,
            'from_user_id'    => $userId,
            'order_id'        => $orderId,
            'zone_type'       => WzNormalZoneProductService::ZONE_NORMAL,
            'scene'           => self::SCENE_GREEN_POINTS,
            'asset_type'      => self::ASSET_GREEN_POINTS,
            'amount'          => $amount,
            'remark'          => WzRewardRemarkHelper::greenPointsGiftRemark(),
            'app_id'          => $appId,
            'release_trigger' => self::TRIGGER_RECEIPT,
        ]);
        $this->mlog("[绿色积分|{$phase}] order_id={$orderId} user_id={$userId} amount={$amount} 已登记待结算");
    }

    private function isEnabled(array $order): bool
    {
        if ((int)($order['zone_type'] ?? 0) !== WzNormalZoneProductService::ZONE_NORMAL) {
            return false;
        }
        return $this->rewardService->supportsSchema() && GreenPointsService::supportsSchema();
    }

    private function mlog(string $message): void
    {
        $file = app()->getRootPath() . 'wz_zone_reward_debug.log';
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
