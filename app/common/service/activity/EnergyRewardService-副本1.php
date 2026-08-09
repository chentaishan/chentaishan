<?php

namespace app\common\service\activity;

use app\common\enum\user\balanceLog\BalanceLogSceneEnum;
use app\common\enum\user\voucher\VoucherLogSceneEnum;
use app\common\library\helper;
use app\common\model\order\CloudRatio;
use app\common\model\user\BalanceLog as BalanceLogModel;
use app\common\model\user\VoucherLog as VoucherLogModel;
use think\facade\Db;

/**
 * 优品区贡献值：按商品行×数量分记录；下级支付成功按达标件数触发上级解锁（N 件解锁 N 次）
 */
class EnergyRewardService
{
    /** @var int 待释放：已生成记录，尚未发生解锁 */
    const RECORD_PENDING   = 1;
    /** @var int 释放中：已部分解锁，remain_energy>0 */
    const RECORD_RELEASING = 2;
    /** @var int 已释放：remain 归零且各次解锁均已入消费券 */
    const RECORD_SETTLED   = 3;

    const LOG_ACTIVE    = 1;
    const LOG_CANCELLED = 2;

    /** @var array<int, string> */
    private static $cloudRatioCache = [];

    private static ?bool $cloudRatioHasStatus = null;

    public function isEnabled(): bool
    {
        return (bool)config('wz_reward.energy.enabled', false);
    }

    public function getZoneType(): int
    {
        return (int)config('wz_reward.energy.zone_type', 1);
    }

    public function getPackageAmount(): string
    {
        $ratioId = (int)config('wz_reward.energy.ratio_id.package_amount', 30);
        $default = (string)config('wz_reward.energy.defaults.package_amount', '2000.00');
        return $this->formatMoney($this->getCloudRatioNum($ratioId, $default));
    }

    public function getEnergyMultiplier(): string
    {
        // 福满堂：贡献值倍数 = Σ各次释放比例/100（如3次30/40/50=1.2，2次50/100=1.5）
        if ($this->isRubyEnabled()) {
            $sum = $this->getContributionRatioSum();
            if (bccomp($sum, '0', 4) > 0) {
                return bcdiv($sum, '100', 4);
            }
        }
        $ratioId = (int)config('wz_reward.energy.ratio_id.energy_multiplier', 31);
        $default = (string)config('wz_reward.energy.defaults.energy_multiplier', '1.5');
        $val = $this->getCloudRatioNum($ratioId, $default);
        return bccomp($val, '0', 4) > 0 ? $val : '1.5';
    }

    /** 是否启用福满堂贡献值模式（按「第几次」N档解锁） */
    private function isRubyEnabled(): bool
    {
        return (bool)config('wz_reward.ruby.enabled', false);
    }

    /** 福满堂：第 $seq 次解锁的比例%（后台可调，超出 N 返回 0） */
    public function getContributionReleaseRatio(int $seq): string
    {
        $cfg  = config('wz_reward.ruby.contribution', []);
        $base = (int)($cfg['release_ratio_base'] ?? 40);
        $max  = (int)($cfg['max_release_count'] ?? 10);
        if ($seq < 1 || $seq > $max) {
            return '0';
        }
        $defaults = $cfg['release_ratio_defaults'] ?? [];
        $default  = isset($defaults[$seq - 1]) ? (string)$defaults[$seq - 1] : '0';
        return $this->getCloudRatioNum($base + $seq, $default);
    }

    /** 福满堂：Σ前 N 次解锁比例（N 为后台配置的释放次数） */
    public function getContributionRatioSum(): string
    {
        $cfg          = config('wz_reward.ruby.contribution', []);
        $countRatioId = (int)($cfg['release_count_ratio_id'] ?? 40);
        $countDefault = (string)($cfg['release_count_default'] ?? 3);
        $max          = (int)($cfg['max_release_count'] ?? 10);
        $n            = (int)$this->getCloudRatioNum($countRatioId, $countDefault);
        if ($n < 1) {
            $n = 1;
        }
        if ($n > $max) {
            $n = $max;
        }
        $sum = '0';
        for ($i = 1; $i <= $n; $i++) {
            $sum = bcadd($sum, $this->getContributionReleaseRatio($i), 4);
        }
        return $sum;
    }

    /** 福满堂：贡献值每次解锁额全额进【消费券】(voucher) */
    private function grantContributionRelease(int $userId, string $amount, int $orderId, int $fromUserId, string $uniqueKey, int $appId): bool
    {
        $value = (float)$amount;
        if ($userId <= 0 || $value <= 0) {
            return false;
        }
        $user = Db::name('user')->where('user_id', '=', $userId)->lock(true)->find();
        if (!$user) {
            return false;
        }
        $appId = $appId ?: (int)($user['app_id'] ?? 0);

        $ratioData = CloudRatio::where('ratio_id','in',[93,94])->column('ratio_id,num');
        $ratioPeArr = array_column($ratioData,'num','ratio_id');
        $balancePe = bcdiv($ratioPeArr[93],'100',4);
        $voucherPe = bcdiv($ratioPeArr[94],'100',4);
        $balanceMoney = bcmul($value, $balancePe,4);
        $voucherMoney = bcmul($value, $voucherPe,4);


        $after = (float)helper::bcadd((string)($user['voucher'] ?? 0), (string)$voucherMoney, 2);
        $balanceAfter = (float)helper::bcadd((string)($user['balance'] ?? 0), (string)$balanceMoney, 2);
        Db::name('user')->where('user_id', '=', $userId)->update([
            'voucher'     => $after,
            'balance'     => $balanceAfter,
            'update_time' => time(),
        ]);
        BalanceLogModel::add(BalanceLogSceneEnum::REWARD, [
            'user_id' => $userId,
            'money'   => $balanceMoney,
            'app_id'  => $appId,
        ], ['贡献值解锁(直推下单)'.$orderId ? ('订单ID：' . $orderId) : '']);
        VoucherLogModel::add([
            'user_id'  => $userId,
            'scene'    => VoucherLogSceneEnum::CONTRIBUTION_UNLOCK,
            'value'    => $voucherMoney,
            'describe' => '贡献值解锁(直推下单)',
            'remark'   => $orderId ? ('订单ID：' . $orderId) : '',
            'order_id' => $orderId,
            'app_id'   => $appId,
        ]);
        return true;
    }

    /** 报单门槛：福满堂模式使用等值判定(lineQualifies) */
    private function getQualifyMinPackage(): string
    {
        return $this->getPackageAmount();
    }

    /**
     * 商品行是否达标：
     *  - 福满堂模式：实付单价 == 配置报单金额（仅等值商品触发玩法）
     *  - 普通模式：实付单价 >= 最低报单金额
     */
    private function lineQualifies(string $unitPay): bool
    {
        if ($this->isRubyEnabled()) {
            return bccomp($unitPay, $this->getPackageAmount(), 2) === 0;
        }
        return bccomp($unitPay, $this->getQualifyMinPackage(), 2) >= 0;
    }

    /** 报单基数：福满堂模式按固定报单金额；普通模式按实付单价 */
    private function resolvePackageBase(string $unitPay): string
    {
        return $this->isRubyEnabled() ? $this->getPackageAmount() : $unitPay;
    }

    /**
     * 福满堂玩法业绩口径：订单中「价格==报单金额」的达标件数与达标业绩(件数×报单金额)。
     *
     * @return array{units:int, amount:string}
     */
    public function getQualifyingBurstAmount(int $orderId): array
    {
        $units = 0;
        foreach ($this->loadQualifyingOrderProducts($orderId) as $line) {
            if (!$this->lineQualifies($this->calcUnitPayAmount($line))) {
                continue;
            }
            $units += max(1, (int)$line['total_num']);
        }
        $amount = $units > 0 ? bcmul($this->getPackageAmount(), (string)$units, 2) : '0.00';
        return ['units' => $units, 'amount' => $amount];
    }

    public function getOddReleasePercent(): string
    {
        $ratioId = (int)config('wz_reward.energy.ratio_id.odd_release_percent', 32);
        $default = (string)config('wz_reward.energy.defaults.odd_release_percent', '50');
        return $this->getCloudRatioNum($ratioId, $default);
    }

    public function getEvenReleasePercent(): string
    {
        $ratioId = (int)config('wz_reward.energy.ratio_id.even_release_percent', 33);
        $default = (string)config('wz_reward.energy.defaults.even_release_percent', '100');
        return $this->getCloudRatioNum($ratioId, $default);
    }

    public function supportsSchema(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        try {
            $ok = $this->hasTable('user_energy_record') && $this->hasTable('energy_unlock_log');
        } catch (\Throwable $e) {
            $ok = false;
        }
        return $ok;
    }

    /**
     * 优品区支付成功：先解锁上级能量，再创建买家能量记录
     */
    public function onPaySuccess(array $order): bool
    {
        $unlocked = $this->onDownlinePackagePaid($order);
        $created  = $this->onBuyerPackagePaid($order);
        return $created || $unlocked;
    }

    /**
     * @deprecated 优品区能量已改为支付成功发放
     */
    public function onOrderCompleted(array $order): bool
    {
        return false;
    }

    /**
     * 优品区支付成功：为买家按订单商品行创建能量记录
     */
    private function onBuyerPackagePaid(array $order): bool
    {
        if (!$this->isEnabled() || !$this->supportsSchema()) {
            return false;
        }
        if (!$this->isEnergyZoneOrder($order)) {
            return false;
        }

        $orderId = (int)$order['order_id'];
        $userId  = (int)$order['user_id'];
        $appId   = (int)($order['app_id'] ?? 0);
        $lines   = $this->loadQualifyingOrderProducts($orderId);
        if ($lines === []) {
            $this->mlog("[能量|支付|创建] 无符合条件的商品行 order_id={$orderId}");
            return false;
        }

        $multiplier = $this->getEnergyMultiplier();
        $now        = time();
        $created    = 0;

        foreach ($lines as $line) {
            $orderProductId = (int)$line['order_product_id'];
            $productId      = (int)$line['product_id'];
            $totalNum       = max(1, (int)$line['total_num']);
            $unitPay        = $this->calcUnitPayAmount($line);
            if (!$this->lineQualifies($unitPay)) {
                continue;
            }
            // 福满堂贡献值按固定报单金额为基数；普通模式按实付单价
            $base        = $this->resolvePackageBase($unitPay);
            $totalEnergy = bcmul($base, $multiplier, 2);

            for ($unitIndex = 1; $unitIndex <= $totalNum; $unitIndex++) {
                $exists = Db::name('user_energy_record')
                    ->where('order_product_id', '=', $orderProductId)
                    ->where('unit_index', '=', $unitIndex)
                    ->value('record_id');
                if ($exists) {
                    continue;
                }
                Db::name('user_energy_record')->insert([
                    'user_id'          => $userId,
                    'source_order_id'  => $orderId,
                    'order_product_id' => $orderProductId,
                    'product_id'       => $productId,
                    'unit_index'       => $unitIndex,
                    'package_amount'   => $base,
                    'total_energy'     => $totalEnergy,
                    'released_energy'  => 0,
                    'remain_energy'    => $totalEnergy,
                    'unlock_count'     => 0,
                    'status'           => self::RECORD_PENDING,
                    'app_id'           => $appId,
                    'create_time'      => $now,
                    'settle_time'      => 0,
                ]);
                $created++;
            }
        }

        $this->mlog("[能量|支付|创建] order_id={$orderId} user_id={$userId} created={$created}");
        return $created > 0;
    }

    /**
     * 优品区支付成功：下级报单按「达标件数」解锁上级能量（买 N 件解锁 N 次，含向上紧缩）
     */
    private function onDownlinePackagePaid(array $order): bool
    {
        if (!$this->isEnabled() || !$this->supportsSchema()) {
            return false;
        }
        if (!$this->isEnergyZoneOrder($order)) {
            return false;
        }

        $buyerUserId = (int)$order['user_id'];
        $orderId     = (int)$order['order_id'];
        $appId       = (int)($order['app_id'] ?? 0);

        $buyer = Db::name('user')->where('user_id', '=', $buyerUserId)->find();
        if (!$buyer || (int)$buyer['referee_id'] <= 0) {
            $this->mlog("[能量|支付|解锁] 买家无直推上级 order_id={$orderId} buyer={$buyerUserId} referee_id=" . (int)($buyer['referee_id'] ?? 0));
            return false;
        }

        $legacyKey = 'energy_unlock_pay_' . $orderId;
        if ($this->unlockLogExists($legacyKey)) {
            $this->mlog("[能量|支付|解锁] 旧版整单解锁已存在，跳过 order_id={$orderId}");
            return false;
        }

        $slots = $this->enumerateQualifyingUnlockSlots($orderId);
        if ($slots === []) {
            $this->mlog("[能量|支付|解锁] 无达标解锁件数 order_id={$orderId}");
            return false;
        }

        $startRefereeId = (int)$buyer['referee_id'];
        $done           = 0;
        $skipped        = 0;
        $this->mlog("[能量|支付|解锁] order_id={$orderId} buyer={$buyerUserId} referee_id={$startRefereeId} slots=" . count($slots));

        foreach ($slots as $slot) {
            $orderProductId = (int)$slot['order_product_id'];
            $unitIndex      = (int)$slot['unit_index'];
            $legacyReceiptKey = 'energy_unlock_receipt_' . $orderId . '_' . $orderProductId . '_' . $unitIndex;
            $uniqueKey      = 'energy_unlock_pay_' . $orderId . '_' . $orderProductId . '_' . $unitIndex;

            if ($this->unlockLogExists($legacyKey) || $this->unlockLogExists($uniqueKey) || $this->unlockLogExists($legacyReceiptKey)) {
                $skipped++;
                continue;
            }

            $beneficiaryId = $this->resolveCompressedBeneficiaryId($startRefereeId, $buyerUserId);
            if ($beneficiaryId <= 0) {
                $this->mlog("[能量|支付|解锁] 第{$unitIndex}件 无待释放上级 order_id={$orderId} op={$orderProductId} start_referee={$startRefereeId}");
                break;
            }

            if ($this->executeSingleUnlock($beneficiaryId, $buyerUserId, $orderId, $appId, $uniqueKey, $orderProductId, $unitIndex)) {
                $done++;
            }
        }

        $this->mlog("[能量|支付|解锁] order_id={$orderId} 应解锁=" . count($slots) . " 成功={$done} 跳过={$skipped}");
        return $done > 0;
    }

    /**
     * 推荐关系变更后补跑：买家已支付优品区订单的上级能量解锁（幂等）
     */
    public function retryUnlockForBuyerPaidOrders(int $buyerUserId): int
    {
        if (!$this->isEnabled() || !$this->supportsSchema() || $buyerUserId <= 0) {
            return 0;
        }

        $refereeId = (int)Db::name('user')->where('user_id', '=', $buyerUserId)->value('referee_id');
        if ($refereeId <= 0) {
            $this->mlog("[能量|补跑|解锁] 买家无直推上级 user_id={$buyerUserId}");
            return 0;
        }

        $zoneType  = $this->getZoneType();
        $orderRows = Db::name('order')
            ->where('user_id', '=', $buyerUserId)
            ->where('pay_status', '=', 20)
            ->where('is_delete', '=', 0)
            ->whereIn('zone_type', [$zoneType, 0])
            ->order('order_id', 'asc')
            ->select()
            ->toArray();

        $unlocked = 0;
        foreach ($orderRows as $order) {
            $order = $this->hydrateUnlockOrder($order);
            if (!$this->isEnergyZoneOrder($order)) {
                continue;
            }
            if ($this->onDownlinePackagePaid($order)) {
                $unlocked++;
            }
        }

        $this->mlog("[能量|补跑|解锁] user_id={$buyerUserId} referee_id={$refereeId} scanned=" . count($orderRows) . " unlocked_orders={$unlocked}");
        return $unlocked;
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function hydrateUnlockOrder(array $order): array
    {
        $orderId = (int)($order['order_id'] ?? 0);
        if ($orderId <= 0) {
            return $order;
        }
        foreach (['zone_type', 'user_id', 'app_id', 'pay_price', 'pay_status'] as $field) {
            if (!array_key_exists($field, $order) || $order[$field] === '' || $order[$field] === null) {
                $val = Db::name('order')->where('order_id', '=', $orderId)->value($field);
                if ($val !== null && $val !== '') {
                    $order[$field] = $val;
                }
            }
        }
        if ((int)($order['zone_type'] ?? 0) <= 0) {
            $inferred = $this->inferZoneTypeFromOrderProducts($orderId);
            if ($inferred > 0) {
                $order['zone_type'] = $inferred;
            }
        }
        return $order;
    }

    private function inferZoneTypeFromOrderProducts(int $orderId): int
    {
        if (!$this->hasTable('order_product')) {
            return 0;
        }
        $productIds = Db::name('order_product')->where('order_id', '=', $orderId)->column('product_id');
        if (empty($productIds) || !$this->hasTable('product')) {
            return 0;
        }
        return (int)Db::name('product')
            ->whereIn('product_id', $productIds)
            ->where('zone_type', '>', 0)
            ->order('zone_type', 'desc')
            ->value('zone_type');
    }

    /**
     * 与买家能量创建口径一致：达标商品行 × 件数 = 解锁次数
     *
     * @return list<array{order_product_id:int, unit_index:int}>
     */
    private function enumerateQualifyingUnlockSlots(int $orderId): array
    {
        $slots = [];
        foreach ($this->loadQualifyingOrderProducts($orderId) as $line) {
            $unitPay = $this->calcUnitPayAmount($line);
            if (!$this->lineQualifies($unitPay)) {
                continue;
            }
            $orderProductId = (int)$line['order_product_id'];
            $totalNum       = max(1, (int)$line['total_num']);
            for ($unitIndex = 1; $unitIndex <= $totalNum; $unitIndex++) {
                $slots[] = [
                    'order_product_id' => $orderProductId,
                    'unit_index'       => $unitIndex,
                ];
            }
        }
        return $slots;
    }

    /**
     * 单次解锁（事务 + 行锁）
     */
    private function executeSingleUnlock(
        int $beneficiaryId,
        int $buyerUserId,
        int $orderId,
        int $appId,
        string $uniqueKey,
        int $orderProductId,
        int $unitIndex
    ): bool {
        return (bool)Db::transaction(function () use (
            $beneficiaryId,
            $buyerUserId,
            $orderId,
            $appId,
            $uniqueKey,
            $orderProductId,
            $unitIndex
        ) {
            if ($this->unlockLogExists($uniqueKey)) {
                return false;
            }

            $record = $this->pickHeadRecord($beneficiaryId, true);
            if (!$record) {
                $this->mlog("[能量|支付|解锁] beneficiary={$beneficiaryId} 无待释放记录 op={$orderProductId} u={$unitIndex}");
                return false;
            }

            $recordId = (int)$record['record_id'];
            $record   = Db::name('user_energy_record')->where('record_id', '=', $recordId)->lock(true)->find();
            if (!$record || !$this->isActiveRecordStatus((int)$record['status'])) {
                $this->mlog("[能量|支付|解锁] record_id={$recordId} 状态无效 beneficiary={$beneficiaryId} op={$orderProductId} u={$unitIndex}");
                return false;
            }

            $remain = $this->formatMoney((string)$this->calcUnreleasedEnergy($record));
            if (bccomp($remain, '0', 2) <= 0) {
                $this->mlog("[能量|支付|解锁] record_id={$recordId} remain=0 beneficiary={$beneficiaryId} op={$orderProductId} u={$unitIndex}");
                return false;
            }

            $seq          = (int)$record['unlock_count'] + 1;
            $ratioPercent = $this->isRubyEnabled()
                ? $this->getContributionReleaseRatio($seq)
                : (($seq % 2) === 1 ? $this->getOddReleasePercent() : $this->getEvenReleasePercent());
            $packageBase  = (string)$record['package_amount'];
            $planned      = bcmul($packageBase, bcdiv($ratioPercent, '100', 4), 2);
            $releaseAmount = bccomp($planned, $remain, 2) > 0 ? $remain : $planned;
            if (bccomp($releaseAmount, '0', 2) <= 0) {
                return false;
            }

            $newReleased = bcadd((string)$record['released_energy'], $releaseAmount, 2);
            $newRemain   = bcsub($remain, $releaseAmount, 2);
            if (bccomp($newRemain, '0', 2) < 0) {
                $newRemain = '0.00';
            }

            $now          = time();
            $isSettled    = bccomp($newRemain, '0', 2) <= 0;
            $settleAmount = '0.00';

            $recordUpdate = [
                'released_energy' => $newReleased,
                'remain_energy'   => $newRemain,
                'unlock_count'    => $seq,
            ];
            if ($isSettled) {
                $recordUpdate['status']      = self::RECORD_SETTLED;
                $recordUpdate['settle_time'] = $now;
            } else {
                $recordUpdate['status'] = self::RECORD_RELEASING;
            }
            Db::name('user_energy_record')->where('record_id', '=', $recordId)->update($recordUpdate);

            $grantKey = 'energy_unlock_grant_' . $recordId . '_' . $seq;
            if ($this->isRubyEnabled()) {
                $grantOk = $this->grantContributionRelease(
                    $beneficiaryId,
                    $releaseAmount,
                    $orderId,
                    $buyerUserId,
                    $grantKey,
                    $appId
                );
            } else {
                $rewardService = new ActivityRewardService();
                $grantOk       = $rewardService->grantEnergyUnlockBalance(
                    $beneficiaryId,
                    (float)$releaseAmount,
                    $orderId,
                    $buyerUserId,
                    $grantKey,
                    $appId
                );
            }
            if (!$grantOk) {
                throw new \RuntimeException("能量解锁入账失败 record_id={$recordId} seq={$seq}");
            }

            if ($isSettled) {
                $settleAmount = $releaseAmount;
            }

            Db::name('energy_unlock_log')->insert([
                'record_id'       => $recordId,
                'user_id'         => $beneficiaryId,
                'from_user_id'    => $buyerUserId,
                'from_order_id'   => $orderId,
                'unlock_seq'      => $seq,
                'release_ratio'   => $ratioPercent,
                'release_amount'  => $releaseAmount,
                'settle_amount'   => $isSettled ? $settleAmount : 0,
                'status'          => self::LOG_ACTIVE,
                'unique_key'      => $uniqueKey,
                'app_id'          => $appId,
                'create_time'     => $now,
            ]);

            $this->mlog(
                "[能量|支付|解锁] order_id={$orderId} op={$orderProductId} u={$unitIndex}"
                . " beneficiary={$beneficiaryId} record_id={$recordId} seq={$seq}"
                . " ratio={$ratioPercent}% unlock={$releaseAmount} remain={$newRemain}"
                . ($isSettled ? " settled={$settleAmount}" : '')
            );
            return true;
        });
    }

    /**
     * 资产页能量汇总（不含列表，列表见 getEnergyRecordList）
     */
    public function getEnergyAssetSummary(int $userId): array
    {
        $default = [
            'energy_enabled'          => $this->isEnabled(),
            'energy_pending_total'    => '0.00',
            'energy_releasing_total'  => '0.00',
            'energy_settled_total'    => '0.00',
            // 进度条口径：每条进行中能量记录按 150% 计算总进度（不含已释放记录）
            'energy_progress_total_percent'    => '0',
            // 已释放进度：释放中记录在 energy_unlock_log 的 release_ratio 合计（不含已释放记录）
            'energy_progress_released_percent' => '0',
            'energy_package_amount'   => $this->getPackageAmount(),
            'energy_multiplier'       => $this->getEnergyMultiplier(),
            'energy_odd_percent'      => $this->getOddReleasePercent(),
            'energy_even_percent'     => $this->getEvenReleasePercent(),
        ];
        if (!$this->isEnabled() || !$this->supportsSchema()) {
            return $default;
        }

        // 未解锁总额 = 待释放记录总值 + 释放中记录未释放值。
        $default['energy_pending_total']   = $this->sumUnreleasedEnergyByStatuses(
            $userId,
            [self::RECORD_PENDING, self::RECORD_RELEASING]
        );
        $default['energy_releasing_total'] = $this->sumUnreleasedEnergyByStatuses($userId, [self::RECORD_RELEASING]);
        $default['energy_settled_total']   = $this->sumEnergyFieldByStatus($userId, self::RECORD_SETTLED, 'total_energy', true);
        $progress = $this->calcEnergyProgressPercent($userId);
        $default['energy_progress_total_percent'] = $progress['total_percent'];
        $default['energy_progress_released_percent'] = $progress['released_percent'];

        return $default;
    }

    /**
     * 进度条口径（均不含 status=已释放 的记录）：
     * - 总进度条 = 进行中记录总条数 * 150
     * - 已释放进度条 = 释放中记录在 energy_unlock_log 的 release_ratio 合计
     */
    private function calcEnergyProgressPercent(int $userId): array
    {
        $rows = Db::name('user_energy_record')
            ->where('user_id', '=', $userId)
            ->field(['record_id', 'status', 'remain_energy', 'unlock_count'])
            ->order('record_id', 'asc')
            ->select()
            ->toArray();

        if (empty($rows)) {
            return ['total_percent' => '0', 'released_percent' => '0'];
        }

        $activeCount = 0;
        $releasingRecordIds = [];

        foreach ($rows as $row) {
            $status = $this->normalizeRecordStatus((int)($row['status'] ?? 0), $row);
            if ($status === self::RECORD_SETTLED) {
                continue;
            }
            $activeCount++;
            if ($status === self::RECORD_RELEASING) {
                $releasingRecordIds[] = (int)$row['record_id'];
            }
        }

        if ($activeCount <= 0) {
            return ['total_percent' => '0', 'released_percent' => '0'];
        }

        $releasingRatioSum = 0.0;
        if (!empty($releasingRecordIds) && $this->hasTable('energy_unlock_log')) {
            $releasingRatioSum = (float)Db::name('energy_unlock_log')
                ->where('user_id', '=', $userId)
                ->where('status', '=', self::LOG_ACTIVE)
                ->whereIn('record_id', $releasingRecordIds)
                ->sum('release_ratio');
            if ($releasingRatioSum < 0) {
                $releasingRatioSum = 0.0;
            }
        }

        $progressUnitPercent = $this->getEnergyProgressUnitPercent();
        $totalPercent = (float)($activeCount * $progressUnitPercent);
        $releasedPercent = $releasingRatioSum;
        if ($releasedPercent > $totalPercent) {
            $releasedPercent = $totalPercent;
        }

        return [
            'total_percent' => $this->formatPercentText($totalPercent),
            'released_percent' => $this->formatPercentText($releasedPercent),
        ];
    }

    private function formatPercentText(float $value): string
    {
        if (abs($value - round($value)) < 0.000001) {
            return (string)((int)round($value));
        }
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function getEnergyProgressUnitPercent(): float
    {
        if ($this->isRubyEnabled()) {
            $sum = (float)$this->getContributionRatioSum();
            if ($sum > 0) {
                return $sum;
            }
        }
        return (float)bcmul($this->getEnergyMultiplier(), '100', 4);
    }

    /**
     * 能量记录分页列表（可按 status 筛选）
     *
     * @param int $status 0=全部 1=待释放 2=释放中 3=已释放
     */
    public function getEnergyRecordList(int $userId, int $status, int $page, int $pageSize): array
    {
        if (!$this->isEnabled() || !$this->supportsSchema()) {
            return [
                'list'         => [],
                'total'        => 0,
                'energy_total' => '0.00',
            ];
        }

        $query = Db::name('user_energy_record')
            ->alias('r')
            ->leftJoin('order o', 'r.source_order_id = o.order_id')
            ->where('r.user_id', '=', $userId);
        $this->applyRecordStatusFilter($query, $status, 'r');

        $countQuery = Db::name('user_energy_record')
            ->alias('r')
            ->where('r.user_id', '=', $userId);
        $this->applyRecordStatusFilter($countQuery, $status, 'r');
        $total = (int)$countQuery->count();

        $rows = $query
            ->field([
                'r.record_id',
                'r.user_id',
                'r.source_order_id',
                'r.order_product_id',
                'r.product_id',
                'r.unit_index',
                'r.package_amount',
                'r.total_energy',
                'r.released_energy',
                'r.remain_energy',
                'r.unlock_count',
                'r.status',
                'r.create_time',
                'r.settle_time',
                'o.order_no',
            ])
            ->order('r.record_id', 'desc')
            ->page($page, $pageSize)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $row) {
            $list[] = $this->formatEnergyRecordRow($row);
        }

        return [
            'list'         => $list,
            'total'        => $total,
            'energy_total' => $this->calcEnergyTotalByStatus($userId, $status),
        ];
    }

    /**
     * 列表接口外层能量汇总，随请求 status 变化
     */
    private function calcEnergyTotalByStatus(int $userId, int $status): string
    {
        if ($status === self::RECORD_PENDING) {
            return $this->sumUnreleasedEnergyByStatuses($userId, [self::RECORD_PENDING]);
        }
        if ($status === self::RECORD_RELEASING) {
            return $this->sumUnreleasedEnergyByStatuses($userId, [self::RECORD_RELEASING]);
        }
        if ($status === self::RECORD_SETTLED) {
            return $this->sumEnergyFieldByStatus($userId, self::RECORD_SETTLED, 'total_energy', true);
        }
        return $this->sumEnergyFieldForUser($userId, 'total_energy');
    }

    private function sumEnergyFieldForUser(int $userId, string $field): string
    {
        $allowed = ['remain_energy', 'total_energy', 'released_energy'];
        if (!in_array($field, $allowed, true)) {
            return '0.00';
        }
        $sum = Db::name('user_energy_record')->where('user_id', '=', $userId)->sum($field);
        return $this->formatMoney((string)($sum ?? '0'));
    }

    private function sumEnergyFieldByStatus(int $userId, int $status, string $field, bool $includeLegacySettled = false): string
    {
        $statuses = [$status];
        if ($includeLegacySettled && $status === self::RECORD_SETTLED) {
            $statuses = [0, self::RECORD_SETTLED];
        }
        return $this->sumEnergyFieldByStatuses($userId, $statuses, $field);
    }

    /**
     * @param int[] $statuses
     */
    private function sumEnergyFieldByStatuses(int $userId, array $statuses, string $field): string
    {
        $allowed = ['remain_energy', 'total_energy', 'released_energy'];
        if (!in_array($field, $allowed, true) || empty($statuses)) {
            return '0.00';
        }
        $sum = Db::name('user_energy_record')
            ->where('user_id', '=', $userId)
            ->whereIn('status', $statuses)
            ->sum($field);
        return $this->formatMoney((string)($sum ?? '0'));
    }

    /**
     * @param int[] $statuses
     */
    private function sumUnreleasedEnergyByStatuses(int $userId, array $statuses): string
    {
        if (empty($statuses)) {
            return '0.00';
        }
        $rows = Db::name('user_energy_record')
            ->where('user_id', '=', $userId)
            ->whereIn('status', $statuses)
            ->field(['total_energy', 'released_energy', 'remain_energy'])
            ->select()
            ->toArray();

        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += $this->calcUnreleasedEnergy($row);
        }
        return $this->formatMoney((string)$sum);
    }

    private function calcUnreleasedEnergy(array $row): float
    {
        $calculated = round(max(0, (float)($row['total_energy'] ?? 0) - (float)($row['released_energy'] ?? 0)), 2);
        $remain = round(max(0, (float)($row['remain_energy'] ?? 0)), 2);
        return $remain > 0 ? min($remain, $calculated) : $calculated;
    }

    private function applyRecordStatusFilter($query, int $status, string $alias = 'r'): void
    {
        if ($status <= 0) {
            return;
        }
        $col = $alias . '.status';
        if ($status === self::RECORD_SETTLED) {
            $query->whereIn($col, [0, self::RECORD_SETTLED]);
            return;
        }
        $query->where($col, '=', $status);
    }

    private function formatEnergyRecordRow(array $row): array
    {
        $status = $this->normalizeRecordStatus((int)$row['status'], $row);
        $createTime = (int)($row['create_time'] ?? 0);
        $settleTime = (int)($row['settle_time'] ?? 0);
        $unreleased = $this->formatMoney((string)$this->calcUnreleasedEnergy($row));
        return [
            'record_id'         => (int)$row['record_id'],
            'product_id'        => (int)$row['product_id'],
            'source_order_id'   => (int)$row['source_order_id'],
            'order_no'          => (string)($row['order_no'] ?? ''),
            'order_product_id'  => (int)$row['order_product_id'],
            'unit_index'        => (int)$row['unit_index'],
            'package_amount'    => (string)$row['package_amount'],
            'total_energy'      => (string)$row['total_energy'],
            'released_energy'   => (string)$row['released_energy'],
            'remain_energy'     => $unreleased,
            'unlock_count'      => (int)$row['unlock_count'],
            'status'            => $status,
            'status_text'       => $this->getRecordStatusText($status),
            'create_time'       => $createTime,
            'create_time_text'  => $createTime ? date('Y-m-d H:i:s', $createTime) : '',
            'settle_time'       => $settleTime,
            'settle_time_text'  => $settleTime ? date('Y-m-d H:i:s', $settleTime) : '',
        ];
    }

    /**
     * 后台充值待释放贡献值/能量：写入 user_energy_record，可供直推下级达标下单解锁。
     *
     * @param array{mode?:string,money?:float|string,value?:float|string,remark?:string} $data
     * @return array{ok:bool,message:string,record_id?:int,total_energy?:string,package_amount?:string}
     */
    public function adminRecharge(int $userId, int $appId, array $data, string $operator = ''): array
    {
        if (!$this->supportsSchema()) {
            return ['ok' => false, 'message' => '能量记录表未就绪'];
        }
        if ($userId <= 0) {
            return ['ok' => false, 'message' => '用户无效'];
        }

        $mode = (string)($data['mode'] ?? 'inc');
        if ($mode !== 'inc') {
            return ['ok' => false, 'message' => '能量值仅支持增加'];
        }

        $raw = $data['money'] ?? ($data['value'] ?? '');
        if ($raw === '' || $raw === null || (float)$raw <= 0) {
            return ['ok' => false, 'message' => '请输入正确的能量值'];
        }
        $totalEnergy = $this->formatMoney((string)$raw);
        if (bccomp($totalEnergy, '99999999.99', 2) > 0) {
            return ['ok' => false, 'message' => '充值能量将超出系统限制(99999999.99)'];
        }

        $multiplier = $this->getEnergyMultiplier();
        if (bccomp($multiplier, '0', 4) <= 0) {
            $multiplier = '1';
        }
        // 解锁额 = package_amount × 各次比例%；令总量=基数×倍数，保证可按比例完整释放
        $packageAmount = $this->formatMoney(bcdiv($totalEnergy, $multiplier, 4));
        if (bccomp($packageAmount, '0', 2) <= 0) {
            return ['ok' => false, 'message' => '计算出的报单基数无效'];
        }

        $user = Db::name('user')->where('user_id', '=', $userId)->where('is_delete', '=', 0)->find();
        if (!$user) {
            return ['ok' => false, 'message' => '用户不存在'];
        }
        $appId = $appId > 0 ? $appId : (int)($user['app_id'] ?? 0);

        try {
            $recordId = (int)Db::transaction(function () use ($userId, $appId, $totalEnergy, $packageAmount, $operator, $data) {
                // uk_order_product_unit=(order_product_id, unit_index) 全局唯一，不含 user_id。
                // order_product_id 为无符号整型，不能用负数；后台充值用高位正数段占位。
                $orderProductId = $this->nextAdminOrderProductId();
                $now = time();

                Db::name('user_energy_record')->insert([
                    'user_id'          => $userId,
                    'source_order_id'  => 0,
                    'order_product_id' => $orderProductId,
                    'product_id'       => 0,
                    'unit_index'       => 1,
                    'package_amount'   => $packageAmount,
                    'total_energy'     => $totalEnergy,
                    'released_energy'  => 0,
                    'remain_energy'    => $totalEnergy,
                    'unlock_count'     => 0,
                    'status'           => self::RECORD_PENDING,
                    'app_id'           => $appId,
                    'create_time'      => $now,
                    'settle_time'      => 0,
                ]);
                $recordId = (int)Db::name('user_energy_record')->getLastInsID();

                // 同步消费券包额度（按贡献值记录条数重算，只增不减）
                try {
                    (new \app\common\service\hekangyuan\VoucherPackService())->syncCap($userId, $appId);
                } catch (\Throwable $e) {
                    $this->mlog('[能量|后台充值|消费包额度] 异常: ' . $e->getMessage());
                }

                $remark = trim((string)($data['remark'] ?? ''));
                $this->mlog(
                    "[能量|后台充值] user_id={$userId} record_id={$recordId}"
                    . " op_id={$orderProductId} total={$totalEnergy} package={$packageAmount}"
                    . " op=" . ($operator !== '' ? $operator : '-')
                    . ($remark !== '' ? " remark={$remark}" : '')
                );
                return $recordId;
            });
        } catch (\Throwable $e) {
            $this->mlog('[能量|后台充值] 失败: ' . $e->getMessage());
            return ['ok' => false, 'message' => '充值失败：' . $e->getMessage()];
        }

        return [
            'ok'             => true,
            'message'        => '充值成功',
            'record_id'      => $recordId,
            'total_energy'   => $totalEnergy,
            'package_amount' => $packageAmount,
        ];
    }

    /**
     * 后台充值专用 order_product_id（高位正数递增）。
     * 字段多为无符号整型，不能用负数；与真实订单商品行 id 错开，并避开 uk_order_product_unit 冲突。
     */
    private function nextAdminOrderProductId(): int
    {
        // 预留段起点（远大于正常自增 order_product_id）
        $base = 0;
        $max = Db::name('user_energy_record')
            ->where('order_product_id', '>=', $base)
            ->max('order_product_id');
        if ($max === null || $max === '') {
            return $base;
        }
        $next = (int)$max + 1;
        // INT 有符号上限约 2147483647；无符号可更大，这里保守卡在有符号上限内
        if ($next > 2147483647) {
            throw new \RuntimeException('后台充值 order_product_id 号段已耗尽');
        }
        return $next;
    }

    /**
     * 向上紧缩：找第一个存在待释放能量记录的上级（跳过买家本人）
     */
    public function resolveCompressedBeneficiaryId(int $startUserId, int $excludeUserId = 0): int
    {
        $currentId = $startUserId;
        $visited   = [];
        $maxDepth  = 100;
        while ($currentId > 0 && !isset($visited[$currentId]) && $maxDepth-- > 0) {
            $visited[$currentId] = true;
            if ($excludeUserId > 0 && $currentId === $excludeUserId) {
                $currentId = (int)Db::name('user')->where('user_id', '=', $currentId)->value('referee_id');
                continue;
            }
            if ($this->hasPendingRecord($currentId)) {
                return $currentId;
            }
            $currentId = (int)Db::name('user')->where('user_id', '=', $currentId)->value('referee_id');
        }
        return 0;
    }

    private function hasPendingRecord(int $userId): bool
    {
        return (bool)Db::name('user_energy_record')
            ->where('user_id', '=', $userId)
            ->whereIn('status', $this->activeRecordStatusValues())
            ->where('remain_energy', '>', 0)
            ->value('record_id');
    }

    private function pickHeadRecord(int $userId, bool $lock): ?array
    {
        $query = Db::name('user_energy_record')
            ->where('user_id', '=', $userId)
            ->whereIn('status', $this->activeRecordStatusValues())
            ->where('remain_energy', '>', 0)
            ->order('record_id', 'asc');
        if ($lock) {
            $query->lock(true);
        }
        $row = $query->find();
        return $row ?: null;
    }

    /** 进行中（待释放 + 释放中），兼容旧库 status=1 且未迁移为 2 的记录 */
    private function activeRecordStatusValues(): array
    {
        return [self::RECORD_PENDING, self::RECORD_RELEASING];
    }

    private function isActiveRecordStatus(int $status): bool
    {
        return in_array($status, $this->activeRecordStatusValues(), true);
    }

    /**
     * 规范状态：旧库 0=已释放；旧库 1 且已有解锁视为释放中
     */
    public function normalizeRecordStatus(int $status, array $record = []): int
    {
        if ($status === 0) {
            return self::RECORD_SETTLED;
        }
        if ($status === self::RECORD_SETTLED) {
            return self::RECORD_SETTLED;
        }
        if ($status === self::RECORD_RELEASING) {
            return self::RECORD_RELEASING;
        }
        if ($status === self::RECORD_PENDING) {
            $released = (string)($record['released_energy'] ?? '0');
            $unlock   = (int)($record['unlock_count'] ?? 0);
            if ($unlock > 0 || bccomp($released, '0', 2) > 0) {
                return self::RECORD_RELEASING;
            }
            return self::RECORD_PENDING;
        }
        return $status;
    }

    public function getRecordStatusText(int $status): string
    {
        $map = [
            self::RECORD_PENDING   => '待释放',
            self::RECORD_RELEASING => '释放中',
            self::RECORD_SETTLED   => '已释放',
        ];
        $normalized = $this->normalizeRecordStatus($status);
        return $map[$normalized] ?? '未知';
    }

    private function loadQualifyingOrderProducts(int $orderId): array
    {
        if (!$this->hasTable('order_product')) {
            return [];
        }
        return Db::name('order_product')
            ->where('order_id', '=', $orderId)
            ->order('order_product_id', 'asc')
            ->select()
            ->toArray();
    }

    private function calcUnitPayAmount(array $line): string
    {
        $totalNum = max(1, (int)$line['total_num']);
        $linePay  = (string)round((float)($line['total_pay_price'] ?? 0), 2);
        if (bccomp($linePay, '0', 2) > 0) {
            return $this->formatMoney(bcdiv($linePay, (string)$totalNum, 2));
        }
        $productPrice = (string)round((float)($line['product_price'] ?? 0), 2);
        return $this->formatMoney($productPrice);
    }

    private function isEnergyZoneOrder(array $order): bool
    {
        return (int)($order['zone_type'] ?? 0) === $this->getZoneType();
    }

    private function unlockLogExists(string $uniqueKey): bool
    {
        return (bool)Db::name('energy_unlock_log')->where('unique_key', '=', $uniqueKey)->value('log_id');
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

    private function getCloudRatioNum(int $ratioId, $default): string
    {
        if ($ratioId <= 0) {
            return (string)$default;
        }
        if (isset(self::$cloudRatioCache[$ratioId])) {
            return self::$cloudRatioCache[$ratioId];
        }
        try {
            if (!$this->hasTable('cloud_ratio')) {
                return self::$cloudRatioCache[$ratioId] = (string)$default;
            }
            $query = Db::name('cloud_ratio')->where('ratio_id', '=', $ratioId);
            if ($this->cloudRatioHasStatusColumn()) {
                $query->where('status', '=', 0);
            }
            $value = $query->order('id', 'desc')->value('num');
            if ($value === null || $value === '') {
                return self::$cloudRatioCache[$ratioId] = (string)$default;
            }
            return self::$cloudRatioCache[$ratioId] = (string)$value;
        } catch (\Throwable $e) {
            return self::$cloudRatioCache[$ratioId] = (string)$default;
        }
    }

    private function cloudRatioHasStatusColumn(): bool
    {
        if (self::$cloudRatioHasStatus !== null) {
            return self::$cloudRatioHasStatus;
        }
        try {
            $fullName = config('database.connections.mysql.prefix') . 'cloud_ratio';
            $rows = Db::query("SHOW COLUMNS FROM `{$fullName}` LIKE 'status'");
            self::$cloudRatioHasStatus = !empty($rows);
        } catch (\Throwable $e) {
            self::$cloudRatioHasStatus = false;
        }
        return self::$cloudRatioHasStatus;
    }

    private function formatMoney(string $amount): string
    {
        return number_format((float)$amount, 2, '.', '');
    }

    private function mlog(string $message): void
    {
        $file = app()->getRootPath() . 'energy_reward_debug.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
