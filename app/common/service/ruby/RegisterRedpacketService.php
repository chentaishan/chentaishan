<?php

namespace app\common\service\ruby;

use think\facade\Db;

/**
 * 红宝石：注册红包(1000元，直推解锁)
 *
 * 会员注册发 1000 元红包(写死)，需直推解锁：每有 1 名直推下级爆单支付成功解锁 200 元，
 * 封顶 5 人(5×200=1000)。解锁金额按「红包三桶」分发到 数字资产/消费值/余额。
 */
class RegisterRedpacketService
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
            $ok = !empty(Db::query("SHOW TABLES LIKE '" . config('database.connections.mysql.prefix') . "redpacket_account'"));
        } catch (\Throwable $e) {
            $ok = false;
        }
        return $ok;
    }

    private function cfg(): array
    {
        $cfg = config('wz_reward.ruby.register_redpacket', []);
        return [
            'total'      => (float)($cfg['total_amount'] ?? 1000),
            'per_unlock' => (float)($cfg['per_unlock'] ?? 200),
            'max_unlock' => (int)($cfg['max_unlock'] ?? 5),
        ];
    }

    /**
     * 注册时初始化注册红包账户(幂等)。
     */
    public function ensureAccount(int $userId, int $appId = 0): void
    {
        if (!$this->supportsSchema() || $userId <= 0) {
            return;
        }
        $exists = Db::name('redpacket_account')->where('user_id', '=', $userId)->value('account_id');
        if ($exists) {
            return;
        }
        $cfg = $this->cfg();
        $now = time();
        try {
            Db::name('redpacket_account')->insert([
                'user_id'         => $userId,
                'total_amount'    => $cfg['total'],
                'unlocked_amount' => 0,
                'unlock_count'    => 0,
                'app_id'          => $appId,
                'create_time'     => $now,
                'update_time'     => $now,
            ]);
        } catch (\Throwable $e) {
            // 并发下唯一键冲突可忽略
        }
    }

    /**
     * 下级爆单支付成功：为其直推上级解锁 200 元(每名下级一次，封顶5人)。
     */
    public function onDownlineBurstPaid(array $order): bool
    {
        if (!$this->supportsSchema()) {
            return false;
        }
        $orderId = (int)($order['order_id'] ?? 0);
        $buyerId = (int)($order['user_id'] ?? 0);
        $appId   = (int)($order['app_id'] ?? 0);
        if ($orderId <= 0 || $buyerId <= 0) {
            return false;
        }
        $refereeId = (int)Db::name('user')->where('user_id', '=', $buyerId)->value('referee_id');
        if ($refereeId <= 0) {
            return false;
        }

        $this->ensureAccount($refereeId, $appId);
        $cfg       = $this->cfg();
        $uniqueKey = 'ruby_reg_unlock_' . $refereeId . '_' . $buyerId;

        return (bool)Db::transaction(function () use ($refereeId, $buyerId, $orderId, $appId, $cfg, $uniqueKey) {
            if (Db::name('redpacket_unlock_log')->where('unique_key', '=', $uniqueKey)->value('log_id')) {
                return false;
            }
            $account = Db::name('redpacket_account')->where('user_id', '=', $refereeId)->lock(true)->find();
            if (!$account) {
                return false;
            }
            if ((int)$account['unlock_count'] >= $cfg['max_unlock']) {
                return false;
            }
            $remain = round((float)$account['total_amount'] - (float)$account['unlocked_amount'], 2);
            if ($remain <= 0) {
                return false;
            }
            $amount = min($cfg['per_unlock'], $remain);
            if ($amount <= 0) {
                return false;
            }
            $seq = (int)$account['unlock_count'] + 1;
            $now = time();

            Db::name('redpacket_unlock_log')->insert([
                'user_id'        => $refereeId,
                'from_user_id'   => $buyerId,
                'from_order_id'  => $orderId,
                'unlock_seq'     => $seq,
                'release_amount' => $amount,
                'unique_key'     => $uniqueKey,
                'app_id'         => $appId,
                'create_time'    => $now,
            ]);
            Db::name('redpacket_account')->where('account_id', '=', (int)$account['account_id'])->update([
                'unlocked_amount' => round((float)$account['unlocked_amount'] + $amount, 2),
                'unlock_count'    => $seq,
                'update_time'     => $now,
            ]);

            $this->assetService->distribute(
                $refereeId,
                $amount,
                RubyAssetService::PROFILE_REDPACKET,
                '注册红包解锁(直推爆单)',
                $orderId,
                $appId
            );
            return true;
        });
    }
}
