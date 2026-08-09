<?php

namespace app\common\service\hekangyuan;

use app\common\enum\user\voucher\VoucherLogSceneEnum;
use app\common\library\helper;
use app\common\model\user\VoucherLog as VoucherLogModel;
use think\facade\Db;

/**
 * 福满堂：消费券互转
 *
 * - 默认仅同一直推线可转；转出方 voucher_free_transfer=1 时可任意转出给任意用户。
 * - 手续费 = 转出额 × 互转手续费%(ratio_id=66，默认20)，全额计入手续费(次日进公共池)。
 * - 到账 = 转出额 - 手续费。
 */
class VoucherTransferService
{
    private const MAX_DEPTH = 200;

    private $error = '';

    public function getError(): string
    {
        return $this->error;
    }

    private function feePercent(): float
    {
        $cfg = config('wz_reward.hekangyuan.transfer', []);
        return $this->cloudRatio((int)($cfg['fee_ratio_id'] ?? 66), (float)($cfg['fee_default'] ?? 20));
    }

    /**
     * 执行互转。成功返回明细数组，失败返回 null（错误见 getError）。
     */
    public function transfer(int $fromUserId, int $toUserId, float $amount): ?array
    {
        $amount = round($amount, 2);
        if ($fromUserId <= 0 || $toUserId <= 0) {
            $this->error = '参数错误';
            return null;
        }
        if ($fromUserId === $toUserId) {
            $this->error = '不能转给自己';
            return null;
        }
        if ($amount <= 0) {
            $this->error = '请输入正确的转出数量';
            return null;
        }

        $from = Db::name('user')->where('user_id', '=', $fromUserId)->where('is_delete', '=', 0)->find();
        $to   = Db::name('user')->where('user_id', '=', $toUserId)->where('is_delete', '=', 0)->find();
        if (!$from || !$to) {
            $this->error = '用户不存在';
            return null;
        }

        $freeTransfer = (int)($from['voucher_free_transfer'] ?? 0) === 1;
        $sameLine     = $this->isSameLine($fromUserId, $toUserId);
        if (!$freeTransfer && !$sameLine) {
            $this->error = '仅可转给同一直推线的用户';
            return null;
        }

        $feePercent = $this->feePercent();
        $fee        = round($amount * $feePercent / 100, 2);
        $realAmount = round($amount - $fee, 2);
        if ($realAmount <= 0) {
            $this->error = '转出数量过小';
            return null;
        }

        $appId = (int)($from['app_id'] ?? 0);
        $now   = time();

        $result = Db::transaction(function () use ($fromUserId, $toUserId, $amount, $fee, $realAmount, $feePercent, $sameLine, $appId, $now) {
            $from = Db::name('user')->where('user_id', '=', $fromUserId)->lock(true)->find();
            $to   = Db::name('user')->where('user_id', '=', $toUserId)->lock(true)->find();
            if (!$from || !$to) {
                $this->error = '用户不存在';
                return false;
            }
            $fromVoucher = (float)($from['voucher'] ?? 0);
            if ($fromVoucher + 0.0001 < $amount) {
                $this->error = '消费券余额不足';
                return false;
            }

            $fromAfter = (float)helper::bcsub((string)$fromVoucher, (string)$amount, 2);
            $toAfter   = (float)helper::bcadd((string)($to['voucher'] ?? 0), (string)$realAmount, 2);
            Db::name('user')->where('user_id', '=', $fromUserId)->update(['voucher' => $fromAfter, 'update_time' => $now]);
            Db::name('user')->where('user_id', '=', $toUserId)->update(['voucher' => $toAfter, 'update_time' => $now]);

            Db::name('voucher_transfer_log')->insert([
                'from_user_id' => $fromUserId,
                'to_user_id'   => $toUserId,
                'amount'       => $amount,
                'fee'          => $fee,
                'real_amount'  => $realAmount,
                'fee_percent'  => $feePercent,
                'same_line'    => $sameLine ? 1 : 0,
                'app_id'       => $appId,
                'create_time'  => $now,
            ]);

            VoucherLogModel::add([
                'user_id'  => $fromUserId,
                'scene'    => VoucherLogSceneEnum::TRANSFER_OUT,
                'value'    => -$amount,
                'describe' => '消费券转出(手续费' . $feePercent . '%)',
                'remark'   => '转给用户ID：' . $toUserId,
                'app_id'   => $appId,
            ]);
            VoucherLogModel::add([
                'user_id'  => $toUserId,
                'scene'    => VoucherLogSceneEnum::TRANSFER_IN,
                'value'    => $realAmount,
                'describe' => '消费券转入',
                'remark'   => '来自用户ID：' . $fromUserId,
                'app_id'   => $appId,
            ]);
            return true;
        });

        if ($result !== true) {
            return null;
        }
        return [
            'amount'      => number_format($amount, 2, '.', ''),
            'fee'         => number_format($fee, 2, '.', ''),
            'real_amount' => number_format($realAmount, 2, '.', ''),
            'fee_percent' => number_format($feePercent, 2, '.', ''),
        ];
    }

    /**
     * 是否同一直推线：一方在另一方的上线链中（祖先/后代关系）。
     */
    private function isSameLine(int $a, int $b): bool
    {
        return $this->isAncestor($a, $b) || $this->isAncestor($b, $a);
    }

    /** $ancestor 是否为 $userId 的上线（沿 referee_id 向上） */
    private function isAncestor(int $ancestor, int $userId): bool
    {
        $current = (int)Db::name('user')->where('user_id', '=', $userId)->value('referee_id');
        $visited = [];
        $depth   = 0;
        while ($current > 0 && !isset($visited[$current]) && $depth++ < self::MAX_DEPTH) {
            if ($current === $ancestor) {
                return true;
            }
            $visited[$current] = true;
            $current = (int)Db::name('user')->where('user_id', '=', $current)->value('referee_id');
        }
        return false;
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
