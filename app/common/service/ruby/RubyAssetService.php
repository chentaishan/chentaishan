<?php

namespace app\common\service\ruby;

use app\common\enum\user\balanceLog\BalanceLogSceneEnum;
use app\common\enum\user\digitalRights\DigitalRightsLogSceneEnum;
use app\common\enum\user\voucher\VoucherLogSceneEnum;
use app\common\library\helper;
use app\common\model\user\BalanceLog as BalanceLogModel;
use app\common\model\user\DigitalRightsLog as DigitalRightsLogModel;
use app\common\model\user\VoucherLog as VoucherLogModel;
use think\facade\Db;

/**
 * 红宝石：三桶资产分发（数字资产 digital_rights / 消费值 voucher / 余额 balance）
 *
 * 红包与贡献值各有独立的三桶比例配置（cloud_ratio），通过 $profile 区分。
 */
class RubyAssetService
{
    /** 红包三桶 */
    const PROFILE_REDPACKET = 'redpacket';
    /** 贡献值三桶 */
    const PROFILE_CONTRIBUTION = 'contribution';

    /**
     * 按指定 profile 的三桶比例，把 $amount 分发到用户的 数字资产/消费值/余额。
     * 余额桶兜底承接四舍五入误差，确保三桶合计==amount。
     *
     * @return array{digital_rights:float,voucher:float,balance:float}
     */
    public function distribute(int $userId, float $amount, string $profile, string $remark, int $orderId = 0, int $appId = 0): array
    {
        $result = ['digital_rights' => 0.0, 'voucher' => 0.0, 'balance' => 0.0];
        $amount = round($amount, 2);
        if ($userId <= 0 || $amount <= 0) {
            return $result;
        }

        $split = $this->getSplitConfig($profile);
        $digital = round($amount * $split['digital_rights'] / 100, 2);
        $voucher = round($amount * $split['voucher'] / 100, 2);
        // 余额桶兜底，吸收精度误差
        $balance = round($amount - $digital - $voucher, 2);
        if ($balance < 0) {
            $balance = 0.0;
        }

        $user = Db::name('user')->where('user_id', '=', $userId)->lock(true)->find();
        if (!$user) {
            return $result;
        }
        $appId = $appId ?: (int)($user['app_id'] ?? 0);

        if ($digital > 0 && $this->hasColumn('user', 'digital_rights')) {
            $this->incUserColumn($userId, 'digital_rights', $digital, $user);
            DigitalRightsLogModel::add([
                'user_id'  => $userId,
                'scene'    => DigitalRightsLogSceneEnum::ADMIN,
                'value'    => $digital,
                'describe' => $remark,
                'remark'   => $orderId ? ('订单ID：' . $orderId) : '',
                'app_id'   => $appId,
            ]);
            $result['digital_rights'] = $digital;
        }

        if ($voucher > 0 && $this->hasColumn('user', 'voucher')) {
            $this->incUserColumn($userId, 'voucher', $voucher, $user);
            VoucherLogModel::add([
                'user_id'  => $userId,
                'scene'    => VoucherLogSceneEnum::ADMIN,
                'value'    => $voucher,
                'describe' => $remark,
                'remark'   => $orderId ? ('订单ID：' . $orderId) : '',
                'order_id' => $orderId,
                'app_id'   => $appId,
            ]);
            $result['voucher'] = $voucher;
        }

        if ($balance > 0) {
            $this->incUserColumn($userId, 'balance', $balance, $user);
            BalanceLogModel::add(BalanceLogSceneEnum::REWARD, [
                'user_id' => $userId,
                'money'   => $balance,
                'app_id'  => $appId,
                'remark'  => $remark,
            ], [$remark]);
            $result['balance'] = $balance;
        }

        return $result;
    }

    private function incUserColumn(int $userId, string $column, float $delta, array $user): void
    {
        $after = (float)helper::bcadd((string)($user[$column] ?? 0), (string)$delta, 2);
        Db::name('user')->where('user_id', '=', $userId)->update([
            $column       => $after,
            'update_time' => time(),
        ]);
        $user[$column] = $after;
    }

    /**
     * 读取三桶比例配置；若合计<=0 则视为全部入余额。
     *
     * @return array{digital_rights:float,voucher:float,balance:float}
     */
    public function getSplitConfig(string $profile): array
    {
        $key = $profile === self::PROFILE_REDPACKET ? 'redpacket_split' : 'contribution_split';
        $cfg = config('wz_reward.ruby.' . $key, []);
        $ratioId  = $cfg['ratio_id'] ?? [];
        $defaults = $cfg['defaults'] ?? ['digital_rights' => 0, 'voucher' => 0, 'balance' => 100];

        $digital = $this->cloudRatio((int)($ratioId['digital_rights'] ?? 0), (float)($defaults['digital_rights'] ?? 0));
        $voucher = $this->cloudRatio((int)($ratioId['voucher'] ?? 0), (float)($defaults['voucher'] ?? 0));
        $balance = $this->cloudRatio((int)($ratioId['balance'] ?? 0), (float)($defaults['balance'] ?? 100));

        $sum = $digital + $voucher + $balance;
        if ($sum <= 0) {
            return ['digital_rights' => 0.0, 'voucher' => 0.0, 'balance' => 100.0];
        }
        return [
            'digital_rights' => $digital,
            'voucher'        => $voucher,
            'balance'        => $balance,
        ];
    }

    private function cloudRatio(int $ratioId, float $default): float
    {
        if ($ratioId <= 0) {
            return $default;
        }
        try {
            $value = Db::name('cloud_ratio')
                ->where('ratio_id', '=', $ratioId)
                ->where('status', '=', 0)
                ->value('num');
            return $value === null || $value === '' ? $default : (float)$value;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        if (!isset($cache[$table])) {
            try {
                $rows = Db::query('SHOW COLUMNS FROM `' . config('database.connections.mysql.prefix') . $table . '`');
                $cache[$table] = array_column($rows, 'Field');
            } catch (\Throwable $e) {
                $cache[$table] = [];
            }
        }
        return in_array($column, $cache[$table], true);
    }
}
