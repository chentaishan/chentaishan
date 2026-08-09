<?php

namespace app\common\service\hekangyuan;

use app\common\enum\user\digitalRights\DigitalRightsLogSceneEnum;
use app\common\enum\user\voucher\VoucherLogSceneEnum;
use app\common\library\helper;
use app\common\model\hekangyuan\HekangyuanSetting as SettingModel;
use app\common\model\hekangyuan\UserDigitalAsset as AssetModel;
use app\common\model\hekangyuan\UserDigitalAssetLog as AssetLogModel;
use app\common\model\user\DigitalRightsLog as DigitalRightsLogModel;
use app\common\model\user\VoucherLog as VoucherLogModel;
use think\facade\Db;

/**
 * 福满堂：消费券兑换数字资产（底池铸币/币价模型，复用香韵逻辑）
 *
 * 流程:
 *  1. 手续费 = 消费券 amount × 买数字资产手续费%(ratio_id=67, 默认20)，全额计入手续费(次日进消费券公共池)。
 *  2. 剩余 a' = amount − fee，按香韵铸币逻辑铸币(变动币价):
 *       币当前价 = 底池 ÷ 流通量(流通量为0回退 init_price)
 *       inPool = a' × in_pool_ratio(b%)；qty = (inPool ÷ 币当前价) × asset_ratio(c%)
 *       底池 += inPool、流通量 += qty → 币价上涨
 *  3. 按笔记 user_digital_asset 持仓 + user_digital_asset_log 买入流水；user.digital_rights 累加持仓数量。
 *  4. 单笔兑换受 hekangyuan_setting.min_exchange/max_exchange(消费券数量) 限制。
 */
class DigitalExchangeService
{
    private $error = '';

    public function getError(): string
    {
        return $this->error;
    }

    private function feePercent(): float
    {
        $cfg = config('wz_reward.hekangyuan.exchange', []);
        return $this->cloudRatio((int)($cfg['fee_ratio_id'] ?? 67), (float)($cfg['fee_default'] ?? 20));
    }

    /**
     * 执行兑换。成功返回明细数组，失败返回 null（错误见 getError）。
     */
    public function exchange(int $userId, float $amount): ?array
    {
        $amount = round($amount, 2);
        if ($userId <= 0) {
            $this->error = '参数错误';
            return null;
        }
        if ($amount <= 0) {
            $this->error = '请输入正确的消费券数量';
            return null;
        }

        $userRow = Db::name('user')->where('user_id', '=', $userId)->where('is_delete', '=', 0)->find();
        if (!$userRow) {
            $this->error = '用户不存在';
            return null;
        }
        $appId   = (int)($userRow['app_id'] ?? 0);
        $setting = SettingModel::getSetting($appId);
        if (!$setting['is_open']) {
            $this->error = '数字资产暂未开放';
            return null;
        }

        // 单笔兑换数量上下限
        $min = (float)($setting['min_exchange'] ?? 0);
        $max = (float)($setting['max_exchange'] ?? 0);
        if ($min > 0 && $amount + 0.0001 < $min) {
            $this->error = '单笔兑换不能少于 ' . rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.') . ' 消费券';
            return null;
        }
        if ($max > 0 && $amount > $max + 0.0001) {
            $this->error = '单笔兑换不能超过 ' . rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.') . ' 消费券';
            return null;
        }

        $feePercent = $this->feePercent();
        $fee        = round($amount * $feePercent / 100, 2);
        $poolIn     = round($amount - $fee, 2); // 进入铸币逻辑的金额 a'
        if ($poolIn <= 0) {
            $this->error = '兑换数量过小';
            return null;
        }

        $now    = time();
        $nowStr = date('Y-m-d H:i:s', $now);
        $result = Db::transaction(function () use ($userId, $appId, $amount, $fee, $poolIn, $feePercent, $now, $nowStr) {
            $user = Db::name('user')->where('user_id', '=', $userId)->where('is_delete', '=', 0)->lock(true)->find();
            if (!$user) {
                $this->error = '用户不存在';
                return false;
            }
            $voucher = (float)($user['voucher'] ?? 0);
            if ($voucher + 0.0001 < $amount) {
                $this->error = '消费券余额不足';
                return false;
            }

            // 加锁取最新币状态并铸币
            $lockedSetting = SettingModel::where('app_id', '=', $appId)->lock(true)->find();
            if (empty($lockedSetting)) {
                $this->error = '配置不存在';
                return false;
            }
            $price = DigitalAssetService::getCoinCurrentPrice($lockedSetting);
            if ($price <= 0) {
                $this->error = '数字资产初始价未配置';
                return false;
            }

            $b      = (float)$lockedSetting['in_pool_ratio'];
            $c      = (float)$lockedSetting['asset_ratio'];
            $inPool = DigitalAssetService::truncate($poolIn * $b / 100, DigitalAssetService::ASSET_SCALE);
            $qty    = DigitalAssetService::truncate($inPool / $price * $c / 100, DigitalAssetService::ASSET_SCALE);
            if ($qty <= 0) {
                $this->error = '兑换数量过小';
                return false;
            }

            $newPool  = DigitalAssetService::truncate((float)$lockedSetting['pool_amount'] + $inPool, DigitalAssetService::ASSET_SCALE);
            $newTotal = DigitalAssetService::truncate((float)$lockedSetting['total_asset'] + $qty, DigitalAssetService::ASSET_SCALE);
            $lockedSetting->save([
                'pool_amount' => $newPool,
                'total_asset' => $newTotal,
                'update_time' => $now,
            ]);

            // 扣消费券 + 累加数字资产持仓汇总
            $voucherAfter = (float)helper::bcsub((string)$voucher, (string)$amount, 2);
            $digitalAfter = DigitalAssetService::truncate((float)($user['digital_rights'] ?? 0) + $qty, DigitalAssetService::MONEY_SCALE);
            Db::name('user')->where('user_id', '=', $userId)->update([
                'voucher'        => $voucherAfter,
                'digital_rights' => $digitalAfter,
                'update_time'    => $now,
            ]);

            // 手续费流水（次日进消费券公共池统计 voucher_exchange_log.fee）
            Db::name('voucher_exchange_log')->insert([
                'user_id'        => $userId,
                'amount'         => $amount,
                'fee'            => $fee,
                'digital_amount' => $qty,
                'fee_percent'    => $feePercent,
                'app_id'         => $appId,
                'create_time'    => $now,
            ]);
            $exchangeLogId = (int)Db::name('voucher_exchange_log')->getLastInsID();

            // 按笔持仓 + 买入流水
            $asset = AssetModel::create([
                'user_id'        => $userId,
                'product_id'     => 0,
                'sub_type'       => AssetModel::SUB_TYPE_POOL,
                'order_id'       => $exchangeLogId,
                'pay_amount'     => DigitalAssetService::truncate($poolIn, DigitalAssetService::ASSET_SCALE),
                'in_pool_amount' => $inPool,
                'buy_price'      => DigitalAssetService::truncate($price, DigitalAssetService::MONEY_SCALE),
                'qty'            => $qty,
                'remain_qty'     => $qty,
                'status'         => AssetModel::STATUS_HOLD,
                'app_id'         => $appId,
                'create_time'    => $nowStr,
                'update_time'    => $nowStr,
            ]);
            AssetLogModel::create([
                'asset_id'      => $asset['asset_id'],
                'user_id'       => $userId,
                'product_id'    => 0,
                'sub_type'      => AssetModel::SUB_TYPE_POOL,
                'action'        => AssetLogModel::ACTION_BUY,
                'qty'           => $qty,
                'remain_before' => 0,
                'remain_after'  => $qty,
                'price'         => DigitalAssetService::truncate($price, DigitalAssetService::MONEY_SCALE),
                'gross_amount'  => 0,
                'fee_rate'      => 0,
                'fee_amount'    => 0,
                'net_amount'    => 0,
                'order_id'      => $exchangeLogId,
                'remark'        => '消费券兑换数字资产',
                'app_id'        => $appId,
                'create_time'   => $nowStr,
                'update_time'   => $nowStr,
            ]);

            VoucherLogModel::add([
                'user_id'  => $userId,
                'scene'    => VoucherLogSceneEnum::BUY_DIGITAL,
                'value'    => -$amount,
                'describe' => '消费券买数字资产(手续费' . $feePercent . '%)',
                'remark'   => '获得数字资产：' . $qty,
                'app_id'   => $appId,
            ]);
            DigitalRightsLogModel::add([
                'user_id'  => $userId,
                'scene'    => DigitalRightsLogSceneEnum::ADMIN,
                'value'    => $qty,
                'describe' => '消费券兑换数字资产',
                'remark'   => '消耗消费券：' . $amount . '，买入价：' . DigitalAssetService::truncate($price, DigitalAssetService::MONEY_SCALE),
                'app_id'   => $appId,
            ]);

            return [
                'asset_id'       => (int)$asset['asset_id'],
                'amount'         => number_format($amount, 2, '.', ''),
                'fee'            => number_format($fee, 2, '.', ''),
                'pool_in'        => number_format($poolIn, 2, '.', ''),
                'buy_price'      => number_format(DigitalAssetService::truncate($price, DigitalAssetService::MONEY_SCALE), 2, '.', ''),
                'digital_amount' => rtrim(rtrim(number_format($qty, 5, '.', ''), '0'), '.'),
                'fee_percent'    => number_format($feePercent, 2, '.', ''),
            ];
        });

        return $result === false ? null : $result;
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
