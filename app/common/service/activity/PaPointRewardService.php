<?php

namespace app\common\service\activity;

use think\facade\Db;

/**
 * 优品区(zone_type=1)帕点奖：
 * - 支付成功：从买家「自身」开始沿推荐人(referee_id)链向上查找，命中第一个
 *   「已开启帕点奖(user.pa_point_reward=1)」的人员（自身也算），发放奖励并即时入余额。
 * - 奖励金额：订单实付 pay_price × cloud_ratio(ratio_id=70)%。
 * - 幂等：unique_key = wz_pa_point_{orderId}_{rewardUserId}（依赖 activity_reward_log）。
 */
class PaPointRewardService
{
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

        $orderId  = (int)($order['order_id'] ?? 0);
        $buyerId  = (int)($order['user_id'] ?? 0);
        $appId    = (int)($order['app_id'] ?? 0);
        $payPrice = round((float)($order['pay_price'] ?? 0), 2);

        if ($orderId <= 0 || $buyerId <= 0) {
            return;
        }
        if ($payPrice <= 0) {
            $this->mlog("[帕点奖] order_id={$orderId} pay_price=0 跳过");
            return;
        }

        $ratioId  = (int)config('wz_reward.pa_point.ratio_id', 70);
        $ratioDef = (float)config('wz_reward.pa_point.ratio_default', 0);
        $ratio    = $this->rewardService->getCloudRatioNum($ratioId, $ratioDef);
        if ($ratio <= 0) {
            $this->mlog("[帕点奖] order_id={$orderId} ratio_id={$ratioId} 比例为0 跳过");
            return;
        }

        $rewardUserId = $this->findFirstEnabledUpline($buyerId);
        if ($rewardUserId <= 0) {
            $this->mlog("[帕点奖] order_id={$orderId} buyer_id={$buyerId} 向上未找到开启帕点奖的人员");
            return;
        }

        $amount = round($payPrice * $ratio / 100, 2);
        if ($amount <= 0) {
            return;
        }

        $ok = $this->rewardService->grantBalanceRewardForOrder(
            $rewardUserId,
            $amount,
            WzRewardRemarkHelper::paPointRemark(),
            $orderId,
            'pa_point',
            'wz_pa_point_' . $orderId . '_' . $rewardUserId,
            $buyerId,
            ActivityRewardService::ZONE_FIRST,
            $appId
        );

        if ($ok) {
            $this->mlog("[帕点奖] order_id={$orderId} buyer_id={$buyerId} reward_user_id={$rewardUserId} pay_price={$payPrice} ratio={$ratio}% amount={$amount} 已发放");
        } else {
            $this->mlog("[帕点奖] order_id={$orderId} reward_user_id={$rewardUserId} amount={$amount} 发放失败: "
                . $this->rewardService->explainGrantBalanceRewardFailure($rewardUserId, $amount, 'wz_pa_point_' . $orderId . '_' . $rewardUserId));
        }
    }

    /**
     * 从买家自身开始沿推荐人链向上查找第一个开启帕点奖的用户（自身也算）
     */
    private function findFirstEnabledUpline(int $buyerId): int
    {
        $maxDepth = (int)config('wz_reward.pa_point.max_depth', 200);
        $visited  = [];
        $current  = $buyerId;
        $depth    = 0;

        while ($current > 0 && $depth <= $maxDepth) {
            if (isset($visited[$current])) {
                break;
            }
            $visited[$current] = true;

            $user = Db::name('user')
                ->field('user_id, referee_id, pa_point_reward, is_delete')
                ->where('user_id', '=', $current)
                ->find();
            if (!$user) {
                break;
            }
            if ((int)($user['is_delete'] ?? 0) === 0 && (int)($user['pa_point_reward'] ?? 0) === 1) {
                return $current;
            }
            $current = (int)($user['referee_id'] ?? 0);
            $depth++;
        }

        return 0;
    }

    private function isEnabled(array $order): bool
    {
        if ((int)($order['zone_type'] ?? 0) !== ActivityRewardService::ZONE_FIRST) {
            return false;
        }
        if (!(bool)config('wz_reward.pa_point.enabled', true)) {
            return false;
        }
        if (!$this->rewardService->supportsSchema()) {
            return false;
        }
        if (!$this->rewardService->hasColumn('user', 'pa_point_reward')) {
            $this->mlog('[帕点奖] user.pa_point_reward 字段未就绪，请执行 database/20260625_hekangyuan_pa_point.sql');
            return false;
        }
        return true;
    }

    private function mlog(string $message): void
    {
        $file = app()->getRootPath() . 'wz_zone_reward_debug.log';
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
