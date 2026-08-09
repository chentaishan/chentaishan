<?php

namespace app\common\service\hekangyuan;

use app\common\enum\user\balanceLog\BalanceLogSceneEnum;
use app\common\model\hekangyuan\HekangyuanSetting as SettingModel;
use app\common\model\hekangyuan\UserDigitalAsset as AssetModel;
use app\common\model\hekangyuan\UserDigitalAssetLog as AssetLogModel;
use app\common\model\user\BalanceLog as BalanceLogModel;
use think\facade\Db;

/**
 * 福满堂数字资产核心服务（全局单一数字资产币，复用香韵逻辑，承载于 hekangyuan_setting）
 *
 * 规则:
 *  - 币当前价 = 底池 pool_amount ÷ 流通量 total_asset（流通量为0时回退 init_price）
 *  - 兑换铸币见 DigitalExchangeService（消费券扣费后剩余 a' 走入池/铸币）
 *  - 卖出(按笔, 可分笔): 有效价 = min(币当前价, 买入价×max_growth_multiple)
 *      gross = sellQty × 有效价 × sell_ratio(f%)
 *      手续费率 = cloud_ratio(69, 默认0); net = gross × (1 − 手续费率) 进【余额(余额)】
 *      底池 -= gross, 流通量 -= sellQty → 币价下跌
 *  - 精度: 价格/金额2位, 资产数量5位, 均向下截断
 */
class DigitalAssetService
{
    const ASSET_SCALE = 5;
    const MONEY_SCALE = 2;

    /** 卖出手续费率 cloud_ratio 默认配置id（默认0%；可由 wz_reward.hekangyuan.digital 覆盖） */
    const SELL_FEE_RATIO_ID = 69;

    private $error = '';

    public function getError(): string
    {
        return $this->error;
    }

    /**
     * 向下截断到指定小数位（非四舍五入），仅用于非负值
     */
    public static function truncate($value, $scale = self::ASSET_SCALE)
    {
        $factor = pow(10, $scale);
        return floor((float)$value * $factor) / $factor;
    }

    /**
     * 币当前价 = 底池 ÷ 流通量（流通量为0时回退初始价）
     */
    public static function getCoinCurrentPrice($setting)
    {
        $pool  = (float)($setting['pool_amount'] ?? 0);
        $total = (float)($setting['total_asset'] ?? 0);
        if ($total > 0) {
            return self::truncate($pool / $total, self::MONEY_SCALE);
        }
        return self::truncate((float)($setting['init_price'] ?? 0), self::MONEY_SCALE);
    }

    /**
     * 币昨日价（每日快照写入 setting.yesterday_price）
     */
    public static function getCoinYesterdayPrice($setting)
    {
        return self::truncate((float)($setting['yesterday_price'] ?? 0), self::MONEY_SCALE);
    }

    /**
     * 单笔持仓的有效价 = min(币当前价, 买入价 × max_growth_multiple)
     * max_growth_multiple<=0 不封顶
     */
    public static function effectivePrice($currentPrice, $buyPrice, $setting)
    {
        $multiple     = (float)($setting['max_growth_multiple'] ?? 0);
        $currentPrice = (float)$currentPrice;
        if ($multiple > 0 && $buyPrice > 0) {
            $cap = self::truncate((float)$buyPrice * $multiple, self::MONEY_SCALE);
            if ($currentPrice > $cap) {
                return $cap;
            }
        }
        return self::truncate($currentPrice, self::MONEY_SCALE);
    }

    /**
     * 卖出手续费率(%): 取 cloud_ratio(69).num，默认0
     */
    public static function getSellFeeRate(): float
    {
        $cfg     = config('wz_reward.hekangyuan.digital', []);
        $ratioId = (int)($cfg['sell_fee_ratio_id'] ?? self::SELL_FEE_RATIO_ID);
        $default = (float)($cfg['sell_fee_default'] ?? 0);
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

    /**
     * 按笔卖出数字资产（资金进余额）
     *
     * @param array $user 用户(含 user_id)
     * @param int   $assetId
     * @param float $sellQty
     * @param int   $appId
     * @return array|null 失败返回 null（错误见 getError）
     */
    public function sellAsset(array $user, int $assetId, float $sellQty, int $appId): ?array
    {
        $userId  = (int)($user['user_id'] ?? 0);
        $sellQty = self::truncate($sellQty, self::ASSET_SCALE);
        if ($userId <= 0 || $assetId <= 0) {
            $this->error = '参数错误';
            return null;
        }
        if ($sellQty <= 0) {
            $this->error = '卖出数量有误';
            return null;
        }
        $setting = SettingModel::getSetting($appId);
        if (!$setting['is_open']) {
            $this->error = '数字资产暂未开放';
            return null;
        }

        $now    = time();
        $nowStr = date('Y-m-d H:i:s', $now);
        $result = Db::transaction(function () use ($userId, $assetId, $sellQty, $appId, $now, $nowStr) {
            $asset = AssetModel::where('asset_id', '=', $assetId)
                ->where('user_id', '=', $userId)
                ->where('app_id', '=', $appId)
                ->lock(true)
                ->find();
            if (empty($asset)) {
                $this->error = '资产记录不存在';
                return false;
            }
            if ((int)$asset['status'] !== AssetModel::STATUS_HOLD) {
                $this->error = '该资产不可卖出';
                return false;
            }
            if ($sellQty > (float)$asset['remain_qty']) {
                $this->error = '卖出数量超过剩余持仓';
                return false;
            }

            $lockedSetting = SettingModel::where('app_id', '=', $appId)->lock(true)->find();
            if (empty($lockedSetting)) {
                $this->error = '配置不存在';
                return false;
            }

            $currentPrice = self::getCoinCurrentPrice($lockedSetting);
            $sellPrice    = self::effectivePrice($currentPrice, (float)$asset['buy_price'], $lockedSetting);
            if ($sellPrice <= 0) {
                $this->error = '当前价格无效, 暂不可卖出';
                return false;
            }

            // 用户行加锁并在任何写入前校验（避免返回 false 时已提交部分写入）
            $userRow = Db::name('user')->where('user_id', '=', $userId)->lock(true)->find();
            if (!$userRow) {
                $this->error = '用户不存在';
                return false;
            }

            $f       = (float)$lockedSetting['sell_ratio'];
            $gross   = self::truncate($sellQty * $sellPrice * $f / 100, self::MONEY_SCALE);
            $feeRate = self::getSellFeeRate();
            $fee     = self::truncate($gross * $feeRate / 100, self::MONEY_SCALE);
            $net     = self::truncate($gross - $fee, self::MONEY_SCALE);
            if ($net < 0) {
                $net = 0;
            }

            // 扣减该笔持仓
            $remainBefore = self::truncate((float)$asset['remain_qty'], self::ASSET_SCALE);
            $remain       = self::truncate((float)$asset['remain_qty'] - $sellQty, self::ASSET_SCALE);
            $asset->save([
                'remain_qty'  => $remain,
                'status'      => $remain > 0 ? AssetModel::STATUS_HOLD : AssetModel::STATUS_SOLD,
                'update_time' => $nowStr,
            ]);

            // 回扣底池/流通量 → 币价下跌
            $newPool = self::truncate((float)$lockedSetting['pool_amount'] - $gross, self::ASSET_SCALE);
            if ($newPool < 0) {
                $newPool = 0;
            }
            $newTotal = self::truncate((float)$lockedSetting['total_asset'] - $sellQty, self::ASSET_SCALE);
            if ($newTotal < 0) {
                $newTotal = 0;
            }
            $lockedSetting->save([
                'pool_amount' => $newPool,
                'total_asset' => $newTotal,
                'update_time' => $now,
            ]);

            // 净额进余额(余额) + 扣减用户数字资产持仓汇总(digital_rights)
            $update = ['update_time' => $now];
            if ($net > 0) {
                $update['balance'] = Db::raw('balance+' . $net);
            }
            $digitalAfter = self::truncate((float)($userRow['digital_rights'] ?? 0) - $sellQty, self::MONEY_SCALE);
            if ($digitalAfter < 0) {
                $digitalAfter = 0;
            }
            $update['digital_rights'] = $digitalAfter;
            Db::name('user')->where('user_id', '=', $userId)->update($update);

            if ($net > 0) {
                BalanceLogModel::add(BalanceLogSceneEnum::REWARD, [
                    'user_id' => $userId,
                    'money'   => $net,
                    'app_id'  => $appId,
                    'remark'  => '数字资产卖出',
                ], ['数字资产卖出']);
            }

            // 卖出流水
            AssetLogModel::create([
                'asset_id'      => $asset['asset_id'],
                'user_id'       => $userId,
                'product_id'    => 0,
                'sub_type'      => AssetModel::SUB_TYPE_POOL,
                'action'        => AssetLogModel::ACTION_SELL,
                'qty'           => $sellQty,
                'remain_before' => $remainBefore,
                'remain_after'  => $remain,
                'price'         => $sellPrice,
                'gross_amount'  => $gross,
                'fee_rate'      => $feeRate,
                'fee_amount'    => $fee,
                'net_amount'    => $net,
                'order_id'      => 0,
                'remark'        => '数字资产卖出(进余额)',
                'app_id'        => $appId,
                'create_time'   => $nowStr,
                'update_time'   => $nowStr,
            ]);

            return [
                'asset_id'      => (int)$asset['asset_id'],
                'sell_qty'      => self::truncate($sellQty, self::ASSET_SCALE),
                'sell_price'    => $sellPrice,
                'gross_amount'  => $gross,
                'fee_rate'      => $feeRate,
                'fee_amount'    => $fee,
                'net_amount'    => $net,
                'remain_qty'    => $remain,
                'current_price' => self::getCoinCurrentPrice($lockedSetting),
            ];
        });

        return $result === false ? null : $result;
    }

    /**
     * 每日昨日价快照（建议每日00:00后执行）
     * 昨日价取「昨日最后一条价格变动流水的成交价」，无则回退当前币价。
     *
     * @return int 已快照的配置数
     */
    public static function snapshotDailyPrice(): int
    {
        $count          = 0;
        $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $yesterdayEnd   = date('Y-m-d 23:59:59', strtotime('-1 day'));

        $settings = SettingModel::select();
        foreach ($settings as $setting) {
            $lastLog = AssetLogModel::where('app_id', '=', $setting['app_id'])
                ->where('create_time', 'between', [$yesterdayStart, $yesterdayEnd])
                ->order('log_id', 'desc')
                ->find();
            $yesterdayPrice = $lastLog
                ? self::truncate((float)$lastLog['price'], self::MONEY_SCALE)
                : self::getCoinCurrentPrice($setting);
            $setting->save([
                'yesterday_price' => $yesterdayPrice,
                'update_time'     => time(),
            ]);
            $count++;
        }
        return $count;
    }
}
