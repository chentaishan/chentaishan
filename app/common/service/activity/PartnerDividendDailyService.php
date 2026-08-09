<?php

namespace app\common\service\activity;

use app\shop\model\user\User as UserModel;
use think\facade\Db;

/**
 * 代理进货区合伙人每日分红：
 * 统计当日 0 点之前已收货、未分红订单实付合计 × cloud_ratio → 奖金池 → 合伙人均分入余额
 */
class PartnerDividendDailyService
{
    public const BATCH_OK         = 10;
    public const BATCH_NO_PARTNER = 20;
    public const BATCH_FAILED     = 30;
    public const BATCH_NO_ORDER   = 40;

    private ActivityRewardService $rewardService;

    public function __construct(?ActivityRewardService $rewardService = null)
    {
        $this->rewardService = $rewardService ?: new ActivityRewardService();
    }

    /**
     * @param bool $cutoffAtRunTime true=测试：截止=当前时刻、不校验批次跳过、可同日多次执行（每单仅分红一次）
     * @param bool $forceRun        true=正式任务忽略当日批次已成功/无订单的跳过限制
     * @return array{ok:bool,msg:string,batch_id?:int}
     */
    public function runForApp(int $appId, ?int $runTime = null, bool $cutoffAtRunTime = false, bool $forceRun = false): array
    {
        if ($appId <= 0) {
            return ['ok' => false, 'msg' => 'app_id 无效'];
        }
        if (!$this->hasTable('partner_dividend_daily_batch')) {
            return ['ok' => false, 'msg' => '请先执行 database/20260603_partner_dividend_daily.sql'];
        }
        $this->ensureBatchMultiRowSchema();
        if (!$this->hasColumn('order', 'partner_dividend_settled')) {
            return ['ok' => false, 'msg' => '订单表缺少 partner_dividend_settled 字段'];
        }
        if (!UserModel::hasColumn('user', 'is_partner')) {
            return ['ok' => false, 'msg' => '请先执行 database/20260602_user_is_partner.sql'];
        }

        $runTime   = $runTime ?? time();
        $statDate  = (int)date('Ymd', $runTime);
        $cutoff    = $this->resolveCutoff($runTime, $cutoffAtRunTime);

        if (!$cutoffAtRunTime && !$forceRun && $this->shouldSkipRun($appId, $statDate)) {
            return ['ok' => true, 'msg' => "app_id={$appId} stat_date={$statDate} 正式已执行，跳过"];
        }

        $orders = $this->fetchEligibleOrders($appId, $cutoff);
        $orderCount = count($orders);
        $orderAmount = '0.00';
        foreach ($orders as $row) {
            $orderAmount = bcadd($orderAmount, number_format((float)$row['pay_price'], 2, '.', ''), 2);
        }

        if ($orderCount === 0) {
            $batchId = $this->createBatch([
                'app_id'             => $appId,
                'stat_date'          => $statDate,
                'cutoff_time'        => $cutoff,
                'order_count'        => 0,
                'order_amount'       => 0,
                'ratio_percent'      => 0,
                'pool_amount'        => 0,
                'partner_count'      => 0,
                'amount_per_partner' => 0,
                'status'             => self::BATCH_NO_ORDER,
                'remark'             => $this->batchRemarkPrefix($cutoffAtRunTime) . '无待分红订单',
            ]);
            return ['ok' => true, 'msg' => "无待分红订单 batch_id={$batchId}", 'batch_id' => $batchId];
        }

        $ratioId   = (int)config('wz_reward.partner_dividend.ratio_id', 38);
        $ratioDef  = (float)config('wz_reward.partner_dividend.ratio_default', 0);
        $ratio     = $this->rewardService->getCloudRatioNum($ratioId, $ratioDef);
        $poolAmount = round((float)$orderAmount * $ratio / 100, 2);

        $partners = $this->fetchPartners($appId);
        $partnerCount = count($partners);

        if ($partnerCount <= 0) {
            $batchId = $this->createBatch([
                'app_id'             => $appId,
                'stat_date'          => $statDate,
                'cutoff_time'        => $cutoff,
                'order_count'        => $orderCount,
                'order_amount'       => (float)$orderAmount,
                'ratio_percent'      => $ratio,
                'pool_amount'        => $poolAmount,
                'partner_count'      => 0,
                'amount_per_partner' => 0,
                'status'             => self::BATCH_NO_PARTNER,
                'remark'             => $this->batchRemarkPrefix($cutoffAtRunTime) . '无合伙人，订单未标记已分红',
            ]);
            return ['ok' => false, 'msg' => '无合伙人，未发放', 'batch_id' => $batchId];
        }

        if ($poolAmount <= 0) {
            return $this->finalizeZeroPool($appId, $statDate, $cutoff, $orders, $orderAmount, $ratio, $partnerCount, $cutoffAtRunTime);
        }

        $amounts = $this->splitPoolAmount($poolAmount, $partnerCount);
        $remark  = WzRewardRemarkHelper::partnerDividendRemark();

        Db::startTrans();
        try {
            $batchId = $this->createBatch([
                'app_id'             => $appId,
                'stat_date'          => $statDate,
                'cutoff_time'        => $cutoff,
                'order_count'        => $orderCount,
                'order_amount'       => (float)$orderAmount,
                'ratio_percent'      => $ratio,
                'pool_amount'        => $poolAmount,
                'partner_count'      => $partnerCount,
                'amount_per_partner' => (float)$amounts[0],
                'status'             => self::BATCH_OK,
                'remark'             => $this->formatBatchRemark($cutoffAtRunTime, $cutoff),
            ]);

            foreach ($partners as $i => $partner) {
                $userId = (int)$partner['user_id'];
                $each   = (float)$amounts[$i];
                if ($each <= 0) {
                    continue;
                }
                $uniqueKey = $this->buildPartnerDividendUniqueKey($statDate, $userId, $cutoff, $cutoffAtRunTime);
                $ok = $this->rewardService->grantBalanceRewardForOrder(
                    $userId,
                    $each,
                    $remark,
                    0,
                    'partner_dividend',
                    $uniqueKey,
                    0,
                    ActivityRewardService::ZONE_AGENT_STOCK,
                    $appId
                );
                if (!$ok) {
                    $reason = $this->rewardService->explainGrantBalanceRewardFailure($userId, $each, $uniqueKey);
                    throw new \RuntimeException("user_id={$userId} 分红入账失败: {$reason}");
                }
            }

            $this->markOrdersSettled($orders, $batchId);
            Db::commit();
            $mode = $cutoffAtRunTime ? '测试|截止=执行时刻|未分红订单' : '正式|截止=当日0点';
            $this->mlog("[合伙人分红|{$mode}] app_id={$appId} stat_date={$statDate} cutoff={$cutoff} orders={$orderCount} amount={$orderAmount} pool={$poolAmount} partners={$partnerCount}");
            return [
                'ok'       => true,
                'msg'      => "分红完成 pool={$poolAmount} partners={$partnerCount}",
                'batch_id' => $batchId,
            ];
        } catch (\Throwable $e) {
            Db::rollback();
            $failData = [
                'cutoff_time'        => $cutoff,
                'order_count'        => $orderCount,
                'order_amount'       => (float)$orderAmount,
                'ratio_percent'      => $ratio,
                'pool_amount'        => $poolAmount,
                'partner_count'      => $partnerCount,
                'amount_per_partner' => 0,
                'status'             => self::BATCH_FAILED,
                'remark'             => $this->batchRemarkPrefix($cutoffAtRunTime) . mb_substr($e->getMessage(), 0, 470),
            ];
            $batchId = $this->createBatch(array_merge(['app_id' => $appId, 'stat_date' => $statDate], $failData));
            $this->mlog('[合伙人分红|失败] ' . $e->getMessage());
            return ['ok' => false, 'msg' => $e->getMessage(), 'batch_id' => $batchId];
        }
    }

    /**
     * 对所有应用执行（定时任务入口）
     *
     * @return list<array{app_id:int,ok:bool,msg:string}>
     */
    public function runAllApps(?int $runTime = null, bool $cutoffAtRunTime = false, bool $forceRun = false): array
    {
        $results = [];
        $apps = Db::name('app')->where('is_delete', '=', 0)->where('is_recycle', '=', 0)->column('app_id');
        foreach ($apps as $appId) {
            $appId = (int)$appId;
            $ret = $this->runForApp($appId, $runTime, $cutoffAtRunTime, $forceRun);
            $results[] = ['app_id' => $appId, 'ok' => $ret['ok'], 'msg' => $ret['msg']];
        }
        return $results;
    }

    /** 正式：当日0点；测试：当前执行时刻 */
    private function resolveCutoff(int $runTime, bool $cutoffAtRunTime): int
    {
        return $cutoffAtRunTime ? $runTime : strtotime(date('Y-m-d 00:00:00', $runTime));
    }

    private function batchRemarkPrefix(bool $cutoffAtRunTime): string
    {
        return $cutoffAtRunTime ? '[测试] ' : '';
    }

    private function formatBatchRemark(bool $cutoffAtRunTime, int $cutoff, string $suffix = ''): string
    {
        $prefix = $this->batchRemarkPrefix($cutoffAtRunTime);
        if ($cutoffAtRunTime) {
            $prefix .= 'cutoff=' . $cutoff . ' ';
        }
        return $prefix . $suffix;
    }

    /** 正式按日幂等；测试按执行时刻幂等，避免同日重复测试撞键 */
    private function buildPartnerDividendUniqueKey(int $statDate, int $userId, int $cutoff, bool $cutoffAtRunTime): string
    {
        if ($cutoffAtRunTime) {
            return 'wz_partner_dividend_test_' . $statDate . '_' . $cutoff . '_' . $userId;
        }
        return 'wz_partner_dividend_' . $statDate . '_' . $userId;
    }

    /**
     * @return list<array{order_id:int,pay_price:string|float}>
     */
    private function fetchEligibleOrders(int $appId, int $cutoff): array
    {
        $query = Db::name('order')
            ->where('app_id', '=', $appId)
            ->where('zone_type', '=', ActivityRewardService::ZONE_AGENT_STOCK)
            ->where('pay_status', '=', 20)
            ->where('receipt_status', '=', 20)
            ->where('partner_dividend_settled', '=', 0)
            ->where('receipt_time', '>', 0)
            ->where('receipt_time', '<', $cutoff);

        if ($this->hasColumn('order', 'is_delete')) {
            $query->where('is_delete', '=', 0);
        }
        // 排除已取消
        $query->whereNotIn('order_status', \app\common\enum\order\OrderStatusEnum::closedStatusList());

        return $query->field('order_id,pay_price')->select()->toArray();
    }

    /**
     * @return list<array{user_id:int}>
     */
    private function fetchPartners(int $appId): array
    {
        $query = Db::name('user')
            ->where('app_id', '=', $appId)
            ->where('is_partner', '=', 1)
            ->where('is_delete', '=', 0);
        if (UserModel::hasColumn('user', 'reward_freeze')) {
            $query->where('reward_freeze', '=', 0);
        }
        return $query->field('user_id')->order('user_id', 'asc')->select()->toArray();
    }

    /**
     * 均分奖金池（分位处理余数，保证合计等于 pool）
     *
     * @return list<string> 每位合伙人金额，2位小数
     */
    private function splitPoolAmount(float $poolAmount, int $count): array
    {
        $totalCents = (int)round($poolAmount * 100);
        $base       = intdiv($totalCents, $count);
        $remainder  = $totalCents % $count;
        $amounts    = [];
        for ($i = 0; $i < $count; $i++) {
            $cents = $base + ($i < $remainder ? 1 : 0);
            $amounts[] = number_format($cents / 100, 2, '.', '');
        }
        return $amounts;
    }

    /**
     * @param list<array{order_id:int}> $orders
     */
    private function markOrdersSettled(array $orders, int $batchId): void
    {
        $orderIds = array_column($orders, 'order_id');
        if (empty($orderIds)) {
            return;
        }
        $update = [
            'partner_dividend_settled' => 1,
            'update_time'              => time(),
        ];
        if ($this->hasColumn('order', 'partner_dividend_batch_id')) {
            $update['partner_dividend_batch_id'] = $batchId;
        }
        Db::name('order')->whereIn('order_id', $orderIds)->update($update);
    }

    /**
     * @param list<array{order_id:int}> $orders
     * @return array{ok:bool,msg:string,batch_id?:int}
     */
    private function finalizeZeroPool(
        int $appId,
        int $statDate,
        int $cutoff,
        array $orders,
        string $orderAmount,
        float $ratio,
        int $partnerCount,
        bool $cutoffAtRunTime = false
    ): array {
        Db::startTrans();
        try {
            $batchId = $this->createBatch([
                'app_id'             => $appId,
                'stat_date'          => $statDate,
                'cutoff_time'        => $cutoff,
                'order_count'        => count($orders),
                'order_amount'       => (float)$orderAmount,
                'ratio_percent'      => $ratio,
                'pool_amount'        => 0,
                'partner_count'      => $partnerCount,
                'amount_per_partner' => 0,
                'status'             => self::BATCH_OK,
                'remark'             => $this->formatBatchRemark($cutoffAtRunTime, $cutoff, '奖金池为0，仅标记订单已分红'),
            ]);
            $this->markOrdersSettled($orders, $batchId);
            Db::commit();
            return ['ok' => true, 'msg' => '奖金池为0，已标记订单', 'batch_id' => $batchId];
        } catch (\Throwable $e) {
            Db::rollback();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 正式任务：当日已有「成功且处理过订单」的正式批次则跳过（批次表每次执行仍新增记录）
     */
    private function shouldSkipRun(int $appId, int $statDate): bool
    {
        return (int)Db::name('partner_dividend_daily_batch')
                ->where('app_id', '=', $appId)
                ->where('stat_date', '=', $statDate)
                ->where('status', '=', self::BATCH_OK)
                ->where('order_count', '>', 0)
                ->where('remark', 'not like', '[测试]%')
                ->count() > 0;
    }

    /**
     * 取消「每 app 每天仅一条」唯一索引，保证每次执行 INSERT 新批次
     */
    private function ensureBatchMultiRowSchema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;
        try {
            $table = config('database.connections.mysql.prefix') . 'partner_dividend_daily_batch';
            $uk = Db::query("SHOW INDEX FROM `{$table}` WHERE Key_name = 'uk_app_stat_date'");
            if (!empty($uk)) {
                Db::execute("ALTER TABLE `{$table}` DROP INDEX `uk_app_stat_date`");
                $this->mlog('[合伙人分红] 已移除 uk_app_stat_date，批次改为每次新增');
            }
            $idx = Db::query("SHOW INDEX FROM `{$table}` WHERE Key_name = 'idx_app_stat_date'");
            if (empty($idx)) {
                Db::execute("ALTER TABLE `{$table}` ADD KEY `idx_app_stat_date` (`app_id`, `stat_date`)");
            }
        } catch (\Throwable $e) {
            $this->mlog('[合伙人分红] 批次表索引检查失败: ' . $e->getMessage());
        }
    }

    private function createBatch(array $data): int
    {
        $now = time();
        try {
            Db::name('partner_dividend_daily_batch')->insert(array_merge([
                'create_time' => $now,
                'update_time' => $now,
            ], $data));
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'uk_app_stat_date') || str_contains($e->getMessage(), 'Duplicate entry')) {
                throw new \RuntimeException(
                    '批次表仍存在 uk_app_stat_date 唯一索引，请执行 database/20260605_partner_dividend_batch_multi.sql：' . $e->getMessage(),
                    0,
                    $e
                );
            }
            throw $e;
        }
        return (int)Db::name('partner_dividend_daily_batch')->getLastInsID();
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
