<?php

namespace app\common\service\settings;

use think\facade\Db;

/**
 * 提现配置：最低金额、手续费比例（jjjshop_cloud_ratio）
 */
class WithdrawalConfigService
{
    public static function getRatioIds(): array
    {
        return config('wz_reward.withdrawal.ratio_id', [
            'service_fee_percent' => 5,
            'min_amount'          => 34,
        ]);
    }

    public static function getDefaults(): array
    {
        return config('wz_reward.withdrawal.defaults', [
            'min_amount'          => 100.00,
            'service_fee_percent' => 6.00,
        ]);
    }

    /**
     * @return array{min_amount:string,service_fee_percent:string}
     */
    public static function get(): array
    {
        $ids      = self::getRatioIds();
        $defaults = self::getDefaults();

        return [
            'min_amount'          => self::formatMoney(
                self::getRatioNum((int)$ids['min_amount'], (float)$defaults['min_amount'])
            ),
            'service_fee_percent' => self::formatMoney(
                self::getRatioNum((int)$ids['service_fee_percent'], (float)$defaults['service_fee_percent'])
            ),
        ];
    }

    public static function update(?float $minAmount, ?float $serviceFeePercent): void
    {
        $ids = self::getRatioIds();
        $now = date('Y-m-d H:i:s');

        if ($minAmount !== null) {
            self::upsertRatio(
                (int)$ids['min_amount'],
                '最低提现金额(元)',
                $minAmount,
                $now
            );
        }
        if ($serviceFeePercent !== null) {
            self::upsertRatio(
                (int)$ids['service_fee_percent'],
                '提现手续费比例%',
                $serviceFeePercent,
                $now
            );
        }
    }

    public static function getRatioNum(int $ratioId, float $default = 0): float
    {
        if ($ratioId <= 0) {
            return $default;
        }
        try {
            if (!self::hasTable('cloud_ratio')) {
                return $default;
            }
            $query = Db::name('cloud_ratio')->where('ratio_id', '=', $ratioId);
            if (self::hasColumn('cloud_ratio', 'status')) {
                $query->where('status', '=', 0);
            }
            $value = $query->value('num');
            return $value === null || $value === '' ? $default : (float)$value;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private static function upsertRatio(int $ratioId, string $name, float $num, string $now): void
    {
        $row = Db::name('cloud_ratio')->where('ratio_id', '=', $ratioId)->find();
        $data  = ['num' => $num, 'update_time' => $now];
        if ($row) {
            Db::name('cloud_ratio')->where('ratio_id', '=', $ratioId)->update($data);
            return;
        }
        $insert = [
            'ratio_id'    => $ratioId,
            'name'        => $name,
            'num'         => $num,
            'create_time' => $now,
            'update_time' => $now,
        ];
        if (self::hasColumn('cloud_ratio', 'status')) {
            $insert['status'] = 0;
        }
        Db::name('cloud_ratio')->insert($insert);
    }

    private static function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private static function hasTable(string $shortName): bool
    {
        try {
            $fullName = config('database.connections.mysql.prefix') . $shortName;
            return !empty(Db::query("SHOW TABLES LIKE '" . addslashes($fullName) . "'"));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function hasColumn(string $table, string $column): bool
    {
        try {
            $fullName = config('database.connections.mysql.prefix') . $table;
            $rows     = Db::query("SHOW COLUMNS FROM `{$fullName}` LIKE '{$column}'");
            return !empty($rows);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
