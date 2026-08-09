<?php

namespace app\common\service\hekangyuan;

use app\common\enum\user\balanceLog\BalanceLogSceneEnum;
use app\common\enum\user\voucher\VoucherLogSceneEnum;
use app\common\library\helper;
use app\common\model\order\CloudRatio;
use app\common\model\user\BalanceLog as BalanceLogModel;
use app\common\model\user\VoucherLog as VoucherLogModel;
use app\common\service\activity\EnergyRewardService;
use think\facade\Db;

/**
 * 福满堂：消费券公共池（次日凌晨发放）
 *
 * 底池 = 昨日互转手续费 + 昨日买数字资产手续费 + 上一批次结余结转。
 * 三档分配（比例后台可调）：
 *  - 大众(identity=0)：个人 = (本人优品区下单件数×下单权重 + 直推首单件数×直推权重) / 全体权重总和 × 底池 × 大众比例%
 *      · 直推首单：直推下级「首次优品区下单」当日，仅计 1 件。
 *  - 白名单(identity=1)：个人 = 底池 × 白名单比例% / 白名单人数（均分）。
 *  - 黑名单(identity=2)：个人 = 底池 × 黑名单比例% / 黑名单人数；若 > 大众最小发放值则封顶为该最小值。
 * 封顶：每人本次发放受【剩余消费券包额度=cap-released】限制，不足只发剩余。
 * 未发完(含黑名单封顶超出、额度不足、无人分档、舍入)全部结转下一批次。实发进【消费券】。
 */
class VoucherPoolService
{
    const STATUS_OK    = 10;
    const STATUS_EMPTY = 20;
    const STATUS_FAIL  = 30;

    /** @var EnergyRewardService */
    private $energyService;

    public function __construct()
    {
        $this->energyService = new EnergyRewardService();
    }

    public function supportsSchema(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $prefix = config('database.connections.mysql.prefix');
            $ok = !empty(Db::query("SHOW TABLES LIKE '" . $prefix . "voucher_dividend_batch'"))
                && !empty(Db::query("SHOW TABLES LIKE '" . $prefix . "voucher_dividend_log'"));
        } catch (\Throwable $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * 执行某发放日(默认昨日)的消费券池发放。
     */
    public function runDailyDividend(int $appId = 0, ?int $statDate = null): array
    {
        if (!$this->supportsSchema()) {
            return ['status' => self::STATUS_FAIL, 'remark' => '消费券池表未就绪'];
        }
        //$statDate = $statDate ?: (int)date('Ymd', strtotime('-1 day'));
        $statDate = $statDate ?: (int)date('Ymd', time());
        $now      = time();

        // 批次幂等
        $existsBatch = Db::name('voucher_dividend_batch')
            ->where('app_id', '=', $appId)
            ->where('stat_date', '=', $statDate)
            ->find();
        if ($existsBatch && (int)$existsBatch['status'] === self::STATUS_OK) {
            return ['status' => self::STATUS_OK, 'remark' => '该日已发放', 'batch_id' => (int)$existsBatch['batch_id']];
        }

        [$dayStart, $dayEnd] = $this->dayRange($statDate);

        // 1) 底池
        $fee         = $this->sumDayFee($appId, $dayStart, $dayEnd);
        $carryoverIn = $this->prevCarryover($appId, $statDate);
        $poolTotal   = round($fee + $carryoverIn, 2);

        $ratios = $this->getRatios();

        if ($poolTotal <= 0) {
            $batchId = $this->upsertBatch($appId, $statDate, [
                'pool_fee' => $fee, 'carryover_in' => $carryoverIn, 'pool_total' => 0,
                'public_ratio' => $ratios['public'], 'white_ratio' => $ratios['white'], 'black_ratio' => $ratios['black'],
                'status' => self::STATUS_EMPTY, 'remark' => '当日无底池', 'carryover_next' => 0,
            ]);
            return ['status' => self::STATUS_EMPTY, 'remark' => '当日无底池', 'batch_id' => $batchId];
        }

        $publicAmount = round($poolTotal * $ratios['public'] / 100, 2);
        $whiteAmount  = round($poolTotal * $ratios['white'] / 100, 2);
        $blackAmount  = round($poolTotal * $ratios['black'] / 100, 2);

        // 2) 大众权重（self_units + push_units）
        $weightMap   = $this->buildPublicWeightMap($appId, $statDate, $dayStart, $dayEnd, $ratios);
        $totalWeight = 0.0;
        foreach ($weightMap as $w) {
            $totalWeight = round($totalWeight + $w['weight'], 4);
        }

        // 3) 大众应发与最小值
        $publicCalc = [];
        $publicMin  = null;
        if ($totalWeight > 0 && $publicAmount > 0) {
            foreach ($weightMap as $uid => $w) {
                if ($w['weight'] <= 0) {
                    continue;
                }
                $calc = round($publicAmount * $w['weight'] / $totalWeight, 2);
                if ($calc <= 0) {
                    continue;
                }
                $publicCalc[$uid] = $calc;
                $publicMin = $publicMin === null ? $calc : min($publicMin, $calc);
            }
        }

        // 4) 白/黑名单人数
        $whiteUsers = $this->listIdentityUsers($appId, 1);
        $blackUsers = $this->listIdentityUsers($appId, 2);
        $whiteCount = count($whiteUsers);
        $blackCount = count($blackUsers);

        $whitePer = $whiteCount > 0 ? round($whiteAmount / $whiteCount, 2) : 0.0;
        $blackPer = $blackCount > 0 ? round($blackAmount / $blackCount, 2) : 0.0;
        // 黑名单封顶为大众最小发放值（有大众发放时才封顶）
        if ($publicMin !== null && $blackPer > $publicMin) {
            $blackPer = $publicMin;
        }

        // 创建批次（进行中：先置失败态，全部发放完成后再置成功，便于崩溃后安全重跑）
        $batchId = $this->upsertBatch($appId, $statDate, [
            'pool_fee' => $fee, 'carryover_in' => $carryoverIn, 'pool_total' => $poolTotal,
            'public_ratio' => $ratios['public'], 'white_ratio' => $ratios['white'], 'black_ratio' => $ratios['black'],
            'public_amount' => $publicAmount, 'white_amount' => $whiteAmount, 'black_amount' => $blackAmount,
            'public_min' => $publicMin ?? 0, 'white_count' => $whiteCount, 'black_count' => $blackCount,
            'total_weight' => $totalWeight, 'status' => self::STATUS_FAIL, 'remark' => '发放中',
        ]);

        // 5) 发放：大众
        foreach ($publicCalc as $uid => $calc) {
            $w = $weightMap[$uid] ?? ['self_units' => 0, 'push_units' => 0, 'weight' => 0];
            $this->grant($batchId, (int)$uid, 0, (int)$w['self_units'], (int)$w['push_units'], (float)$w['weight'], $calc, $appId, $now);
        }
        // 6) 发放：白名单
        if ($whitePer > 0) {
            foreach ($whiteUsers as $uid) {
                $this->grant($batchId, (int)$uid, 1, 0, 0, 0, $whitePer, $appId, $now);
            }
        }
        // 7) 发放：黑名单
        if ($blackPer > 0) {
            foreach ($blackUsers as $uid) {
                $this->grant($batchId, (int)$uid, 2, 0, 0, 0, $blackPer, $appId, $now);
            }
        }

        // 实发总额以发放明细为准（保证重跑幂等正确）
        $actualTotal = round((float)Db::name('voucher_dividend_log')->where('batch_id', '=', $batchId)->sum('actual_amount'), 2);
        $carryoverNext = round($poolTotal - $actualTotal, 2);
        if ($carryoverNext < 0) {
            $carryoverNext = 0.0;
        }

        $this->upsertBatch($appId, $statDate, [
            'pool_fee' => $fee, 'carryover_in' => $carryoverIn, 'pool_total' => $poolTotal,
            'public_ratio' => $ratios['public'], 'white_ratio' => $ratios['white'], 'black_ratio' => $ratios['black'],
            'public_amount' => $publicAmount, 'white_amount' => $whiteAmount, 'black_amount' => $blackAmount,
            'public_min' => $publicMin ?? 0, 'white_count' => $whiteCount, 'black_count' => $blackCount,
            'total_weight' => $totalWeight, 'actual_total' => $actualTotal, 'carryover_next' => $carryoverNext,
            'status' => self::STATUS_OK, 'remark' => '完成',
        ]);

        return [
            'status'         => self::STATUS_OK,
            'batch_id'       => $batchId,
            'pool_total'     => $poolTotal,
            'actual_total'   => $actualTotal,
            'carryover_next' => $carryoverNext,
            'public_users'   => count($publicCalc),
            'white_count'    => $whiteCount,
            'black_count'    => $blackCount,
        ];
    }

    /**
     * 单用户发放：受【剩余消费券包额度】限制，实发进消费券。返回实发金额。
     */
    private function grant(int $batchId, int $userId, int $identity, int $selfUnits, int $pushUnits, float $weight, float $calc, int $appId, int $now): float
    {
        if ($userId <= 0 || $calc <= 0) {
            return 0.0;
        }
        $uniqueKey = 'hky_voucher_div_' . $batchId . '_' . $userId;

        return (float)Db::transaction(function () use ($batchId, $userId, $identity, $selfUnits, $pushUnits, $weight, $calc, $appId, $now, $uniqueKey) {
            if (Db::name('voucher_dividend_log')->where('unique_key', '=', $uniqueKey)->value('log_id')) {
                return 0.0;
            }
            $user = Db::name('user')->where('user_id', '=', $userId)->lock(true)->find();
            if (!$user) {
                return 0.0;
            }
            $cap       = (float)($user['voucher_pack_cap'] ?? 0);
            $released  = (float)($user['voucher_pack_released'] ?? 0);
            $remainCap = round(max(0, $cap - $released), 2);

            $actual = $calc;
            if ($actual > $remainCap) {
                $actual = $remainCap;
            }
            $actual = round($actual, 2);

            Db::name('voucher_dividend_log')->insert([
                'batch_id'          => $batchId,
                'user_id'           => $userId,
                'identity'          => $identity,
                'self_units'        => $selfUnits,
                'push_units'        => $pushUnits,
                'weight'            => $weight,
                'calc_amount'       => $calc,
                'cap_remain_before' => $remainCap,
                'actual_amount'     => $actual,
                'unique_key'        => $uniqueKey,
                'app_id'            => $appId,
                'create_time'       => $now,
            ]);

            if ($actual <= 0) {
                return 0.0;
            }
//            $ratioData = CloudRatio::where('ratio_id','in',[93,94])->column('ratio_id,num');
//            $ratioPeArr = array_column($ratioData,'num','ratio_id');
//            $balancePe = bcdiv($ratioPeArr[93],'100',4);
//            $voucherPe = bcdiv($ratioPeArr[94],'100',4);
//            $balanceMoney = bcmul($actual, $balancePe,4);
//            $voucherMoney = bcmul($actual, $voucherPe,4);

            $voucherAfter = (float)helper::bcadd((string)($user['voucher'] ?? 0), (string)$actual, 2);
           // $balanceAfter = (float)helper::bcadd((string)($user['balance'] ?? 0), (string)$balanceMoney, 2);
            $releasedAfter = (float)helper::bcadd((string)$released, (string)$actual, 2);
            Db::name('user')->where('user_id', '=', $userId)->update([
                //'balance'               => $balanceAfter,
                'voucher'               => $voucherAfter,
                'voucher_pack_released' => $releasedAfter,
                'update_time'           => $now,
            ]);
//            BalanceLogModel::add(BalanceLogSceneEnum::REWARD, [
//                'user_id' => $userId,
//                'money'   => $balanceMoney,
//                'app_id'  => $appId,
//            ], ['消费池发放(' . $this->identityText($identity) . ')']);
            VoucherLogModel::add([
                'user_id'  => $userId,
                'scene'    => VoucherLogSceneEnum::POOL_DIVIDEND,
                'value'    => $actual,
                'describe' => '消费券池发放(' . $this->identityText($identity) . ')',
                'remark'   => '批次ID：' . $batchId,
                'app_id'   => $appId,
            ]);
            return $actual;
        });
    }

    /**
     * 构建大众权重表：[user_id => [self_units, push_units, weight]]
     */
    private function buildPublicWeightMap(int $appId, int $statDate, int $dayStart, int $dayEnd, array $ratios): array
    {
        $zoneType = (int)config('wz_reward.hekangyuan.zone_type', 1);

        // 当日优品区已支付订单
        $orders = Db::name('order')
            ->where('app_id', '=', $appId)
            ->where('zone_type', '=', $zoneType)
            ->where('pay_status', '=', 20)
            ->where('is_delete', '=', 0)
            ->where('pay_time', 'between', [$dayStart, $dayEnd])
            ->field(['order_id', 'user_id', 'pay_time'])
            ->select()
            ->toArray();

        $selfUnits = [];   // user_id => 件数
        $buyers    = [];   // user_id => true（当日优品区下单买家）
        foreach ($orders as $o) {
            $buyerId = (int)$o['user_id'];
            $buyers[$buyerId] = true;
            $units = (int)($this->energyService->getQualifyingBurstAmount((int)$o['order_id'])['units'] ?? 0);
            if ($units > 0) {
                $selfUnits[$buyerId] = ($selfUnits[$buyerId] ?? 0) + $units;
            }
        }

        // 直推首单：买家当日为「首次优品区下单」，给其直推上级 +1（每名下级仅计一次）
        $pushUnits = [];
        $countedBuyer = [];
        foreach (array_keys($buyers) as $buyerId) {
            if (isset($countedBuyer[$buyerId])) {
                continue;
            }
            $countedBuyer[$buyerId] = true;
            if (!$this->isFirstZoneOrderOnDay($appId, $buyerId, $zoneType, $dayStart, $dayEnd)) {
                continue;
            }
            $refereeId = (int)Db::name('user')->where('user_id', '=', $buyerId)->value('referee_id');
            if ($refereeId > 0) {
                $pushUnits[$refereeId] = ($pushUnits[$refereeId] ?? 0) + 1;
            }
        }

        $selfWeight = (float)$ratios['self_weight'];
        $pushWeight = (float)$ratios['push_weight'];

        $candidates = array_unique(array_merge(array_keys($selfUnits), array_keys($pushUnits)));
        $map = [];
        foreach ($candidates as $uid) {
            $uid = (int)$uid;
            if ($this->getIdentity($uid) !== 0) {
                continue; // 仅大众参与加权池
            }
            $su = (int)($selfUnits[$uid] ?? 0);
            $pu = (int)($pushUnits[$uid] ?? 0);
            $weight = round($su * $selfWeight + $pu * $pushWeight, 4);
            if ($weight <= 0) {
                continue;
            }
            $map[$uid] = ['self_units' => $su, 'push_units' => $pu, 'weight' => $weight];
        }
        return $map;
    }

    /**
     * 该买家的「首次优品区已支付订单」是否落在指定日内。
     */
    private function isFirstZoneOrderOnDay(int $appId, int $buyerId, int $zoneType, int $dayStart, int $dayEnd): bool
    {
        $firstPayTime = (int)Db::name('order')
            ->where('app_id', '=', $appId)
            ->where('user_id', '=', $buyerId)
            ->where('zone_type', '=', $zoneType)
            ->where('pay_status', '=', 20)
            ->where('is_delete', '=', 0)
            ->min('pay_time');
        return $firstPayTime >= $dayStart && $firstPayTime <= $dayEnd;
    }

    private function getIdentity(int $userId): int
    {
        return (int)Db::name('user')->where('user_id', '=', $userId)->value('hky_identity');
    }

    /** @return int[] */
    private function listIdentityUsers(int $appId, int $identity): array
    {
        return array_map('intval', Db::name('user')
            ->where('app_id', '=', $appId)
            ->where('hky_identity', '=', $identity)
            ->where('is_delete', '=', 0)
            ->column('user_id'));
    }

    private function sumDayFee(int $appId, int $dayStart, int $dayEnd): float
    {
        $transferFee = (float)Db::name('voucher_transfer_log')
            ->where('app_id', '=', $appId)
            ->where('create_time', 'between', [$dayStart, $dayEnd])
            ->sum('fee');
        $exchangeFee = (float)Db::name('voucher_exchange_log')
            ->where('app_id', '=', $appId)
            ->where('create_time', 'between', [$dayStart, $dayEnd])
            ->sum('fee');
        return round($transferFee + $exchangeFee, 2);
    }

    /**
     * 上一批次结余结转（取 stat_date < 当前 的最近一条成功批次的 carryover_next）。
     */
    private function prevCarryover(int $appId, int $statDate): float
    {
        $row = Db::name('voucher_dividend_batch')
            ->where('app_id', '=', $appId)
            ->where('stat_date', '<', $statDate)
            ->where('status', '=', self::STATUS_OK)
            ->order('stat_date', 'desc')
            ->value('carryover_next');
        return round((float)$row, 2);
    }

    private function getRatios(): array
    {
        $cfg     = config('wz_reward.hekangyuan.pool', []);
        $ratioId = $cfg['ratio_id'] ?? [];
        $def     = $cfg['defaults'] ?? [];
        return [
            'white'       => $this->cloudRatio((int)($ratioId['white'] ?? 61), (float)($def['white'] ?? 20)),
            'black'       => $this->cloudRatio((int)($ratioId['black'] ?? 62), (float)($def['black'] ?? 5)),
            'public'      => $this->cloudRatio((int)($ratioId['public'] ?? 63), (float)($def['public'] ?? 75)),
            'self_weight' => $this->cloudRatio((int)($ratioId['self_weight'] ?? 64), (float)($def['self_weight'] ?? 1)),
            'push_weight' => $this->cloudRatio((int)($ratioId['push_weight'] ?? 65), (float)($def['push_weight'] ?? 1)),
        ];
    }

    private function identityText(int $identity): string
    {
        return [0 => '大众', 1 => '白名单', 2 => '黑名单'][$identity] ?? '大众';
    }

    /** @return array{0:int,1:int} [dayStart, dayEnd] */
    private function dayRange(int $statDate): array
    {
        $start = strtotime(date('Y-m-d 00:00:00', strtotime((string)$statDate)));
        return [$start, $start + 86399];
    }

    private function upsertBatch(int $appId, int $statDate, array $data): int
    {
        $now = time();
        $existing = Db::name('voucher_dividend_batch')
            ->where('app_id', '=', $appId)
            ->where('stat_date', '=', $statDate)
            ->find();
        $payload = array_merge([
            'app_id'      => $appId,
            'stat_date'   => $statDate,
            'update_time' => $now,
        ], $data);
        if ($existing) {
            Db::name('voucher_dividend_batch')->where('batch_id', '=', (int)$existing['batch_id'])->update($payload);
            return (int)$existing['batch_id'];
        }
        $payload['create_time'] = $now;
        Db::name('voucher_dividend_batch')->insert($payload);
        return (int)Db::name('voucher_dividend_batch')->getLastInsID();
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
