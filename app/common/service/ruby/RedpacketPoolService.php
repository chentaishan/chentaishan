<?php

namespace app\common\service\ruby;

use think\facade\Db;

/**
 * 红宝石：红包加权分红池(3000封顶)
 *
 * - 进池：每笔爆单区订单实付 × 进池比例% 计入当日红包池。
 * - 分红：次日定时，按「用户昨日进池金额 / 当日进池总额」比例分配整池金额；
 *   个人累计封顶(默认3000)，超出部分不发(本次实发=min(应发, 封顶-历史))。
 * - 发放金额按「红包三桶」分发到 数字资产/消费值/余额。
 */
class RedpacketPoolService
{
    /** @var RubyAssetService */
    private $assetService;

    public function __construct(?RubyAssetService $assetService = null)
    {
        $this->assetService = $assetService ?: new RubyAssetService();
    }

    public function supportsSchema(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $prefix = config('database.connections.mysql.prefix');
            $ok = !empty(Db::query("SHOW TABLES LIKE '" . $prefix . "redpacket_pool_log'"))
                && !empty(Db::query("SHOW TABLES LIKE '" . $prefix . "redpacket_dividend_batch'"));
        } catch (\Throwable $e) {
            $ok = false;
        }
        return $ok;
    }

    private function getInRatio(): float
    {
        $cfg = config('wz_reward.ruby.redpacket_pool', []);
        return $this->cloudRatio((int)($cfg['in_ratio_id'] ?? 57), (float)($cfg['in_ratio_default'] ?? 10));
    }

    private function getCap(): float
    {
        $cfg = config('wz_reward.ruby.redpacket_pool', []);
        return $this->cloudRatio((int)($cfg['cap_ratio_id'] ?? 58), (float)($cfg['cap_default'] ?? 3000));
    }

    /**
     * 爆单区支付成功：按比例计入当日红包池(幂等)。
     */
    public function poolIn(array $order): bool
    {
        if (!$this->supportsSchema()) {
            return false;
        }
        $orderId = (int)($order['order_id'] ?? 0);
        $buyerId = (int)($order['user_id'] ?? 0);
        $appId   = (int)($order['app_id'] ?? 0);
        $amount  = round((float)($order['pay_price'] ?? 0), 2);
        if ($orderId <= 0 || $buyerId <= 0 || $amount <= 0) {
            return false;
        }
        $ratio = $this->getInRatio();
        if ($ratio <= 0) {
            return false;
        }
        $poolAmount = round($amount * $ratio / 100, 2);
        if ($poolAmount <= 0) {
            return false;
        }
        $uniqueKey = 'ruby_pool_in_' . $orderId;
        if (Db::name('redpacket_pool_log')->where('unique_key', '=', $uniqueKey)->value('log_id')) {
            return false;
        }
        $now = time();
        try {
            Db::name('redpacket_pool_log')->insert([
                'user_id'           => $buyerId,
                'order_id'          => $orderId,
                'order_amount'      => $amount,
                'pool_amount'       => $poolAmount,
                'stat_date'         => (int)date('Ymd', $now),
                'dividend_batch_id' => 0,
                'unique_key'        => $uniqueKey,
                'app_id'            => $appId,
                'create_time'       => $now,
            ]);
        } catch (\Throwable $e) {
            return false;
        }
        return true;
    }

    /**
     * 执行某一进池日的分红（默认昨日）。返回批次结果摘要。
     */
    public function runDailyDividend(int $appId = 0, ?int $statDate = null): array
    {
        if (!$this->supportsSchema()) {
            return ['status' => 30, 'remark' => '红包池表未就绪'];
        }
        $statDate = $statDate ?: (int)date('Ymd', strtotime('-1 day'));
        $now      = time();

        // 批次幂等
        $existsBatch = Db::name('redpacket_dividend_batch')
            ->where('app_id', '=', $appId)
            ->where('stat_date', '=', $statDate)
            ->find();
        if ($existsBatch && (int)$existsBatch['status'] === 10) {
            return ['status' => 10, 'remark' => '该日已分红', 'batch_id' => (int)$existsBatch['batch_id']];
        }

        // 汇总当日进池(未分红)
        $rows = Db::name('redpacket_pool_log')
            ->where('app_id', '=', $appId)
            ->where('stat_date', '=', $statDate)
            ->where('dividend_batch_id', '=', 0)
            ->field('user_id, SUM(pool_amount) AS in_pool')
            ->group('user_id')
            ->select()
            ->toArray();

        $poolTotal = 0.0;
        foreach ($rows as $r) {
            $poolTotal = round($poolTotal + (float)$r['in_pool'], 2);
        }
        if (empty($rows) || $poolTotal <= 0) {
            $batchId = $this->upsertBatch($appId, $statDate, 0, 0, 0, $this->getCap(), 20, '当日无进池');
            return ['status' => 20, 'remark' => '当日无进池', 'batch_id' => $batchId];
        }

        $cap = $this->getCap();
        $batchId = $this->upsertBatch($appId, $statDate, $poolTotal, 0, count($rows), $cap, 10, '');

        $dividendTotal = 0.0;
        $paidUsers     = 0;
        foreach ($rows as $r) {
            $userId  = (int)$r['user_id'];
            $inPool  = round((float)$r['in_pool'], 2);
            $weight  = $poolTotal > 0 ? round($inPool / $poolTotal * 100, 4) : 0.0;
            $calc    = round($poolTotal * $inPool / $poolTotal, 2); // = inPool（按进池占比分整池）

            $actual = $this->grantWithCap($batchId, $userId, $inPool, $weight, $calc, $cap, $appId, $now);
            if ($actual > 0) {
                $dividendTotal = round($dividendTotal + $actual, 2);
                $paidUsers++;
            }
        }

        // 标记进池流水已分红
        Db::name('redpacket_pool_log')
            ->where('app_id', '=', $appId)
            ->where('stat_date', '=', $statDate)
            ->where('dividend_batch_id', '=', 0)
            ->update(['dividend_batch_id' => $batchId]);

        $this->upsertBatch($appId, $statDate, $poolTotal, $dividendTotal, count($rows), $cap, 10, '完成');

        return [
            'status'          => 10,
            'batch_id'        => $batchId,
            'pool_amount'     => $poolTotal,
            'dividend_amount' => $dividendTotal,
            'user_count'      => count($rows),
            'paid_users'      => $paidUsers,
        ];
    }

    /**
     * 单用户分红入账(受3000累计封顶)，返回实发金额。
     */
    private function grantWithCap(int $batchId, int $userId, float $inPool, float $weight, float $calc, float $cap, int $appId, int $now): float
    {
        $uniqueKey = 'ruby_pool_div_' . $batchId . '_' . $userId;
        if (Db::name('redpacket_dividend_log')->where('unique_key', '=', $uniqueKey)->value('log_id')) {
            return 0.0;
        }
        // 历史累计实发（封顶判断）
        $history = round((float)Db::name('redpacket_dividend_log')->where('user_id', '=', $userId)->sum('actual_amount'), 2);
        $actual  = $calc;
        if ($cap > 0) {
            $allow = round($cap - $history, 2);
            if ($allow <= 0) {
                $actual = 0.0;
            } elseif ($actual > $allow) {
                $actual = $allow;
            }
        }

        Db::name('redpacket_dividend_log')->insert([
            'batch_id'       => $batchId,
            'user_id'        => $userId,
            'in_pool_amount' => $inPool,
            'weight_percent' => $weight,
            'calc_amount'    => $calc,
            'actual_amount'  => $actual,
            'history_before' => $history,
            'unique_key'     => $uniqueKey,
            'app_id'         => $appId,
            'create_time'    => $now,
        ]);

        if ($actual > 0) {
            $this->assetService->distribute(
                $userId,
                $actual,
                RubyAssetService::PROFILE_REDPACKET,
                '红包池加权分红',
                0,
                $appId
            );
        }
        return $actual;
    }

    private function upsertBatch(int $appId, int $statDate, float $poolAmount, float $dividendAmount, int $userCount, float $cap, int $status, string $remark): int
    {
        $now = time();
        $existing = Db::name('redpacket_dividend_batch')
            ->where('app_id', '=', $appId)
            ->where('stat_date', '=', $statDate)
            ->find();
        if ($existing) {
            Db::name('redpacket_dividend_batch')->where('batch_id', '=', (int)$existing['batch_id'])->update([
                'pool_amount'     => $poolAmount,
                'dividend_amount' => $dividendAmount,
                'user_count'      => $userCount,
                'cap_amount'      => $cap,
                'status'          => $status,
                'remark'          => $remark,
                'update_time'     => $now,
            ]);
            return (int)$existing['batch_id'];
        }
        Db::name('redpacket_dividend_batch')->insert([
            'app_id'          => $appId,
            'stat_date'       => $statDate,
            'pool_amount'     => $poolAmount,
            'dividend_amount' => $dividendAmount,
            'user_count'      => $userCount,
            'cap_amount'      => $cap,
            'status'          => $status,
            'remark'          => $remark,
            'create_time'     => $now,
            'update_time'     => $now,
        ]);
        return (int)Db::name('redpacket_dividend_batch')->getLastInsID();
    }

    private function cloudRatio(int $ratioId, float $default): float
    {
        if ($ratioId <= 0) {
            return $default;
        }
        try {
            $value = Db::name('cloud_ratio')->where('ratio_id', '=', $ratioId)->where('status', '=', 0)->value('num');
            return $value === null || $value === '' ? $default : (float)$value;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
