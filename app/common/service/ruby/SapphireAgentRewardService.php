<?php

namespace app\common\service\ruby;

use app\common\service\activity\ActivityRewardService;
use think\facade\Db;

/**
 * 红宝石：蓝宝石极差代理奖
 *
 * 爆单区支付成功，以本单实付为业绩，沿推荐链向上逐级发放「极差」：
 *   记录已发出的最高比例 maxPaid（初始0）；遇到等级更高的上级，
 *   发放 业绩 ×(上级比例 - maxPaid)%，并把 maxPaid 提升为该上级比例；
 *   等级不更高则跳过。奖励入余额(生态积分)。
 */
class SapphireAgentRewardService
{
    /** @var ActivityRewardService */
    private $rewardService;

    /** @var RubyTeamService */
    private $teamService;

    private const MAX_DEPTH = 200;

    public function __construct(?ActivityRewardService $rewardService = null)
    {
        $this->rewardService = $rewardService ?: new ActivityRewardService();
        $this->teamService   = new RubyTeamService();
    }

    public function isEnabled(): bool
    {
        return (bool)config('wz_reward.ruby.sapphire.enabled', true);
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

        $startId = (int)Db::name('user')->where('user_id', '=', $buyerId)->value('referee_id');
        if ($startId <= 0) {
            return false;
        }

        $maxPaid   = 0.0;
        $currentId = $startId;
        $visited   = [];
        $depth     = 0;
        $granted   = 0;

        while ($currentId > 0 && !isset($visited[$currentId]) && $depth++ < self::MAX_DEPTH) {
            $visited[$currentId] = true;
            $level   = (int)Db::name('user')->where('user_id', '=', $currentId)->value('sapphire_level');
            $percent = $this->teamService->getRewardPercentByLevel($level);

            if ($percent > $maxPaid) {
                $diff   = round($percent - $maxPaid, 2);
                $reward = round($amount * $diff / 100, 2);
                if ($reward > 0) {
                    $uniqueKey = 'ruby_sapphire_' . $orderId . '_' . $currentId;
                    $remark    = '蓝宝石极差奖(' . $diff . '%)';
                    $ok = $this->rewardService->grantBalanceRewardForOrder(
                        $currentId,
                        $reward,
                        $remark,
                        $orderId,
                        'ruby_sapphire',
                        $uniqueKey,
                        $buyerId,
                        ActivityRewardService::ZONE_FIRST,
                        $appId
                    );
                    if ($ok) {
                        $granted++;
                    }
                }
                $maxPaid = $percent;
            }

            // 已达最高等级比例，无需继续向上
            if ($maxPaid >= $this->maxConfiguredPercent()) {
                break;
            }
            $currentId = (int)Db::name('user')->where('user_id', '=', $currentId)->value('referee_id');
        }

        return $granted > 0;
    }

    private function maxConfiguredPercent(): float
    {
        $max = 0.0;
        foreach ($this->teamService->getSapphireConfig() as $row) {
            $max = max($max, (float)$row['reward_percent']);
        }
        return $max;
    }
}
