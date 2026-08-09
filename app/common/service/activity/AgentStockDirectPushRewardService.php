<?php

namespace app\common\service\activity;

use think\facade\Db;

/**
 * 代理进货区(zone_type=3)直推奖（供应链拓客补贴）：
 * - 支付成功：登记待结算（activity_reward_log status=0）
 * - 确认收货：释放入账（release_trigger=receipt）
 * - 金额：下单实付 × cloud_ratio(ratio_id=37)%
 */
class AgentStockDirectPushRewardService
{
    private const TRIGGER_RECEIPT = 'receipt';

    /** @var ActivityRewardService */
    private $rewardService;

    public function __construct(?ActivityRewardService $rewardService = null)
    {
        $this->rewardService = $rewardService ?: new ActivityRewardService();
    }

    public function onPaySuccess(array $order): void
    {
        if (!$this->isEnabled($order)) {
            return;
        }
        $this->registerPendingReward($order, 'pay');
    }

    public function onOrderCompleted(array $order): void
    {
        if (!$this->isEnabled($order)) {
            return;
        }
        // 幂等补登记；实际释放由 ActivityRewardService 统一处理
        $this->registerPendingReward($order, 'receipt');
    }

    private function isEnabled(array $order): bool
    {
        if ((int)($order['zone_type'] ?? 0) !== ActivityRewardService::ZONE_AGENT_STOCK) {
            return false;
        }
        if (!$this->rewardService->supportsSchema()) {
            return false;
        }
        return (bool)config('wz_reward.agent_stock_region.direct_push.enabled', true);
    }

    private function registerPendingReward(array $order, string $phase): void
    {
        $orderId  = (int)($order['order_id'] ?? 0);
        $buyerId  = (int)($order['user_id'] ?? 0);
        $appId    = (int)($order['app_id'] ?? 0);
        $payPrice = round((float)($order['pay_price'] ?? 0), 2);

        if ($orderId <= 0 || $buyerId <= 0 || $payPrice <= 0) {
            $this->mlog("[代理进货区直推|{$phase}] order_id={$orderId} pay_price=0 跳过");
            return;
        }

        $buyer = Db::name('user')->where('user_id', '=', $buyerId)->find();
        if (!$buyer || (int)($buyer['referee_id'] ?? 0) <= 0) {
            $this->mlog("[代理进货区直推|{$phase}] order_id={$orderId} 无直接上级");
            return;
        }

        $refereeId = (int)$buyer['referee_id'];
        $ratioId   = (int)config('wz_reward.agent_stock_region.direct_push.ratio_id', 37);
        $ratioDef  = (float)config('wz_reward.agent_stock_region.direct_push.ratio_default', 0);
        $ratio     = $this->rewardService->getCloudRatioNum($ratioId, $ratioDef);
        if ($ratio <= 0) {
            $this->mlog("[代理进货区直推|{$phase}] order_id={$orderId} ratio_id={$ratioId} 比例为0 跳过");
            return;
        }

        $amount = round($payPrice * $ratio / 100, 2);
        if ($amount <= 0) {
            return;
        }

        $this->rewardService->createPendingRewardLogPublic([
            'unique_key'      => 'wz_agent_stock_direct_push_' . $orderId . '_' . $refereeId,
            'user_id'         => $refereeId,
            'from_user_id'    => $buyerId,
            'order_id'        => $orderId,
            'zone_type'       => ActivityRewardService::ZONE_AGENT_STOCK,
            'scene'           => 'direct_push',
            'asset_type'      => 'balance',
            'amount'          => $amount,
            'remark'          => WzRewardRemarkHelper::agentStockDirectPushRemark(),
            'app_id'          => $appId,
            'reward_month'    => date('Ym'),
            'release_trigger' => self::TRIGGER_RECEIPT,
        ]);
        $this->mlog("[代理进货区直推|{$phase}] order_id={$orderId} 待结算 referee_id={$refereeId} pay_price={$payPrice} ratio={$ratio}% amount={$amount}");
    }

    private function mlog(string $message): void
    {
        $file = app()->getRootPath() . 'wz_zone_reward_debug.log';
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
