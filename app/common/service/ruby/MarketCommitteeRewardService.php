<?php

namespace app\common\service\ruby;

use app\common\service\activity\ActivityRewardService;
use think\facade\Db;

/**
 * 红宝石：趴点(市场委) 奖
 *
 * 爆单区支付成功，团队新增业绩(=本单实付) × 趴点比例%，只发放一次：
 *   买家自身是趴点 → 发给自身，不再向上查；
 *   买家自身不是 → 沿推荐链向上找到最近的一个趴点发放。
 * 奖励入余额(生态积分)。
 */
class MarketCommitteeRewardService
{
    /** @var ActivityRewardService */
    private $rewardService;

    private const MAX_DEPTH = 200;

    public function __construct(?ActivityRewardService $rewardService = null)
    {
        $this->rewardService = $rewardService ?: new ActivityRewardService();
    }

    public function isEnabled(): bool
    {
        return (bool)config('wz_reward.ruby.market_committee.enabled', true);
    }

    public function onPaySuccess(array $order): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }
        $orderId = (int)($order['order_id'] ?? 0);
        $buyerId = (int)($order['user_id'] ?? 0);
        $appId   = (int)($order['app_id'] ?? 0);
        $amount  = round((float)($order['pay_price'] ?? 0), 2);
        if ($orderId <= 0 || $buyerId <= 0 || $amount <= 0) {
            return false;
        }

        $percent = $this->getPercent();
        if ($percent <= 0) {
            return false;
        }

        $beneficiaryId = $this->resolveCommittee($buyerId);
        if ($beneficiaryId <= 0) {
            return false;
        }

        $reward = round($amount * $percent / 100, 2);
        if ($reward <= 0) {
            return false;
        }

        $uniqueKey = 'ruby_committee_' . $orderId;
        $remark    = '趴点(市场委)奖(' . $percent . '%)';
        return (bool)$this->rewardService->grantBalanceRewardForOrder(
            $beneficiaryId,
            $reward,
            $remark,
            $orderId,
            'ruby_committee',
            $uniqueKey,
            $buyerId,
            ActivityRewardService::ZONE_FIRST,
            $appId
        );
    }

    /**
     * 自身是趴点则返回自身，否则向上找最近趴点。
     */
    private function resolveCommittee(int $buyerId): int
    {
        $currentId = $buyerId;
        $visited   = [];
        $depth     = 0;
        while ($currentId > 0 && !isset($visited[$currentId]) && $depth++ < self::MAX_DEPTH) {
            $visited[$currentId] = true;
            $row = Db::name('user')
                ->where('user_id', '=', $currentId)
                ->field(['user_id', 'is_market_committee', 'referee_id'])
                ->find();
            if (!$row) {
                return 0;
            }
            if ((int)($row['is_market_committee'] ?? 0) === 1) {
                return $currentId;
            }
            $currentId = (int)($row['referee_id'] ?? 0);
        }
        return 0;
    }

    private function getPercent(): float
    {
        $cfg = config('wz_reward.ruby.market_committee', []);
        $ratioId = (int)($cfg['ratio_id'] ?? 59);
        $default = (float)($cfg['ratio_default'] ?? 5);
        return $this->rewardService->getCloudRatioNum($ratioId, $default);
    }
}
