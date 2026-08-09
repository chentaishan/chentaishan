<?php

namespace app\common\service\activity;

use think\facade\Db;

/**
 * 甲丽华威商城分区奖励
 * - 优品区：支付成功升会员、能量与省市区代奖；代理奖见 FirstZoneAgentRewardService
 * - 消费区：支付登记直推奖+省市区代奖(待结算)；确认收货释放入账；区域奖见 NormalZoneRegionRewardService
 */
class WzZoneRewardService
{
    const ZONE_FIRST  = 1;
    const ZONE_NORMAL = 2;

    const TRIGGER_RECEIPT = 'receipt';

    private ActivityRewardService $rewardService;

    public function __construct(?ActivityRewardService $rewardService = null)
    {
        $this->rewardService = $rewardService ?: new ActivityRewardService();
    }

    public function onPaySuccess(array $order): void
    {
        if (!$this->rewardService->supportsSchema()) {
            return;
        }
        $zoneType = (int)($order['zone_type'] ?? 0);
        $orderId  = (int)$order['order_id'];

        if ($zoneType === self::ZONE_FIRST) {
            return;
        }

        if ($zoneType === self::ZONE_NORMAL) {
            $this->registerNormalZonePendingRewards($order);
        }
    }

    public function onOrderCompleted(array $order): void
    {
        if (!$this->rewardService->supportsSchema()) {
            return;
        }
        if ((int)($order['zone_type'] ?? 0) !== self::ZONE_NORMAL) {
            return;
        }
        // 支付时未登记待结算则收货时补登记，再按 receipt 释放
        $this->registerNormalZonePendingRewards($order);
        $this->rewardService->releaseOrderRewardsByTrigger($order, self::TRIGGER_RECEIPT);
    }

    /**
     * 优品区支付成功：游客升级为会员(job_grade=1)
     * 仅依赖 user.job_grade 字段，不依赖 activity_reward_log 表
     */
    public function upgradeMemberOnFirstZoneReceipt(array $order): void
    {
        if ((int)($order['zone_type'] ?? 0) !== self::ZONE_FIRST) {
            return;
        }
        if (!$this->rewardService->hasColumn('user', 'job_grade')) {
            return;
        }
        $userId      = (int)$order['user_id'];
        $memberGrade = (int)config('wz_reward.member_job_grade', 1);
        $user        = Db::name('user')->where('user_id', '=', $userId)->find();
        if (!$user) {
            return;
        }
        $grade = WzUserIdentityService::normalizeJobGrade((int)($user['job_grade'] ?? 0));
        if ($grade >= $memberGrade) {
            return;
        }
        Db::name('user')->where('user_id', '=', $userId)->update([
            'job_grade'   => WzUserIdentityService::JOB_GRADE_MEMBER,
            'update_time' => time(),
        ]);
        $this->mlog("[会员升级|支付] user_id={$userId} job_grade → {$memberGrade} order_id=" . (int)$order['order_id']);
    }

    /**
     * 消费区：支付登记直推奖（收货释放）
     */
    private function registerNormalZonePendingRewards(array $order): void
    {
        $orderId  = (int)$order['order_id'];
        $buyerId  = (int)$order['user_id'];
        $appId    = (int)($order['app_id'] ?? 0);
        $buyer    = Db::name('user')->where('user_id', '=', $buyerId)->find();
        if (!$buyer || (int)$buyer['referee_id'] <= 0) {
            return;
        }
        $refereeId = (int)$buyer['referee_id'];

        if (!$this->hasTable('order_product')) {
            return;
        }

        $lines = Db::name('order_product')->where('order_id', '=', $orderId)->select()->toArray();
        $totalDirect = '0.00';
        foreach ($lines as $line) {
            $amount = $this->getDirectPushAmountForLine($line);
            if (bccomp($amount, '0', 2) <= 0) {
                continue;
            }
            $num = max(1, (int)$line['total_num']);
            $lineTotal = bcmul($amount, (string)$num, 2);
            $totalDirect = bcadd($totalDirect, $lineTotal, 2);
        }

        if (bccomp($totalDirect, '0', 2) <= 0) {
            $this->mlog("[消费区直推] order_id={$orderId} 无直推奖配置");
            return;
        }

        $this->createPending([
            'unique_key'         => 'wz_direct_push_' . $orderId . '_' . $refereeId,
            'user_id'            => $refereeId,
            'from_user_id'       => $buyerId,
            'order_id'           => $orderId,
            'zone_type'          => self::ZONE_NORMAL,
            'scene'              => 'direct_push',
            'asset_type'         => 'balance',
            'amount'             => (float)$totalDirect,
            'remark'             => WzRewardRemarkHelper::directPushRemark(self::ZONE_NORMAL),
            'app_id'          => $appId,
            'release_trigger' => self::TRIGGER_RECEIPT,
        ]);
        $this->mlog("[消费区登记] order_id={$orderId} 直推 user_id={$refereeId} amount={$totalDirect}");
    }

    private function getDirectPushAmountForLine(array $line): string
    {
        $productId = (int)($line['product_id'] ?? 0);
        if ($productId <= 0 || !$this->hasColumn('product', 'direct_push_amount')) {
            return '0.00';
        }
        $val = Db::name('product')->where('product_id', '=', $productId)->value('direct_push_amount');
        return $val !== null && $val !== '' ? number_format((float)$val, 2, '.', '') : '0.00';
    }

    private function createPending(array $data): void
    {
        $this->rewardService->createPendingRewardLogPublic($data);
    }

    private function hasTable(string $shortName): bool
    {
        try {
            $fullName = config('database.connections.mysql.prefix') . $shortName;
            return !empty(Db::query("SHOW TABLES LIKE '" . addslashes($fullName) . "'"));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $fullName = config('database.connections.mysql.prefix') . $table;
            $rows = Db::query("SHOW COLUMNS FROM `{$fullName}` LIKE '{$column}'");
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function mlog(string $message): void
    {
        $file = app()->getRootPath() . 'wz_zone_reward_debug.log';
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
