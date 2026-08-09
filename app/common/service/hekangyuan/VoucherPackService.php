<?php

namespace app\common\service\hekangyuan;

use think\facade\Db;

/**
 * 福满堂：消费券包额度（个人从公共池可领取上限）
 *
 * 用户每在优品区(zone_type=1)下单 1 件达标商品(价格==报单金额)，
 * 累计消费券包额度 +3000（ratio_id=68，后台可调）。
 *
 * 额度 = 用户贡献值记录条数(每达标件一条) × 单件额度；
 * 采用「按记录条数重算且只增不减」保证幂等。
 */
class VoucherPackService
{
    private function perUnitAmount(): float
    {
        $cfg     = config('wz_reward.hekangyuan.voucher_pack', []);
        $ratioId = (int)($cfg['ratio_id'] ?? 68);
        $default = (float)($cfg['default'] ?? 3000);
        return $this->cloudRatio($ratioId, $default);
    }

    private function hasColumn(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $prefix = config('database.connections.mysql.prefix');
            $rows = Db::query("SHOW COLUMNS FROM `{$prefix}user` LIKE 'voucher_pack_cap'");
            $ok = !empty($rows);
        } catch (\Throwable $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * 优品区支付成功：按用户贡献值记录条数同步消费券包额度上限（只增不减，幂等）。
     */
    public function syncCap(int $userId, int $appId = 0): float
    {
        if ($userId <= 0 || !$this->hasColumn()) {
            return 0.0;
        }
        $perUnit = $this->perUnitAmount();
        if ($perUnit <= 0) {
            return 0.0;
        }

        $units = (int)Db::name('user_energy_record')->where('user_id', '=', $userId)->count();
        $expected = round($units * $perUnit, 2);

        $current = (float)Db::name('user')->where('user_id', '=', $userId)->value('voucher_pack_cap');
        if ($expected <= $current) {
            return $current;
        }
        Db::name('user')->where('user_id', '=', $userId)->update([
            'voucher_pack_cap' => $expected,
            'update_time'      => time(),
        ]);
        return $expected;
    }

    /**
     * 剩余可领取额度 = cap - released。
     */
    public function remainCap(array $user): float
    {
        $cap      = (float)($user['voucher_pack_cap'] ?? 0);
        $released = (float)($user['voucher_pack_released'] ?? 0);
        return round(max(0, $cap - $released), 2);
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
