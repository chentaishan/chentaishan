<?php

namespace app\common\service\activity;

use app\common\enum\user\balanceLog\BalanceLogSceneEnum;
use app\common\model\user\BalanceLog as BalanceLogModel;
use app\common\model\user\PointsLog as PointsLogModel;
use app\common\service\greenpoints\GreenPointsRewardService;
use app\common\service\greenpoints\GreenPointsService;
use app\common\service\hekangyuan\VoucherPackService;
use think\facade\Db;

/**
 * 甲丽华威商城活动奖励：能量值（EnergyRewardService）、分区奖（WzZoneRewardService）、pending 释放与退款扣回
 */
class ActivityRewardService
{
    const ZONE_FIRST       = 1; // 品牌优选区
    const ZONE_REBUY       = 2; // 惠民区
    const ZONE_AGENT_STOCK = 3; // 代理进货区
    const ZONE_PARTNER = 4; // 合伙人区

    const REWARD_PENDING   = 0;
    const REWARD_RELEASED  = 1;
    const REWARD_CANCELLED = 2;

    private $schemaChecked = false;
    private $schemaReady = false;
    private $schemaError = '';
    private $columns = [];
    /** @var string 最近一次补发失败原因（供 CLI 输出） */
    private $lastRepairError = '';

    public function onPaySuccess($order)
    {
        $order    = $this->normalizeRewardOrder($order);
        $zoneType = (int)($order['zone_type'] ?? 0);
        
        if ($zoneType === self::ZONE_FIRST) {
            try {
                (new WzZoneRewardService($this))->upgradeMemberOnFirstZoneReceipt($order);
            } catch (\Throwable $e) {
                $this->mlog('[onPaySuccess|会员升级] 异常: ' . $e->getMessage());
            }
            try {
                (new EnergyRewardService())->onPaySuccess($order);
            } catch (\Throwable $e) {
                $this->mlog('[onPaySuccess|能量] 异常: ' . $e->getMessage());
            }
            // 红宝石优品区玩法（仅 zone_type=1）
            if (config('wz_reward.ruby.enabled', false)) {
                $this->dispatchRubyFirstZoneRewards($order);
            }
            if (config('wz_reward.first_zone_agent.enabled', true)) {
                try {
                    if ($this->supportsSchema()) {
                        Db::transaction(function () use ($order) {
                            (new FirstZoneAgentRewardService($this))->onPaySuccess($order);
                            $this->releaseOrderRewardsByTrigger($order, 'pay');
                        });
                    } else {
                        (new FirstZoneAgentRewardService($this))->onPaySuccess($order);
                    }
                } catch (\Throwable $e) {
                    $this->mlog('[onPaySuccess|优品区代理奖] 异常: ' . $e->getMessage());
                }
            }
            // 帕点奖：向上查找第一个开启帕点奖的人员发放（自身也算）
            if (config('wz_reward.pa_point.enabled', true)) {
                try {
                    (new PaPointRewardService($this))->onPaySuccess($order);
                } catch (\Throwable $e) {
                    $this->mlog('[onPaySuccess|帕点奖] 异常: ' . $e->getMessage());
                }
            }
            return true;
        }
        if (!in_array($zoneType, [self::ZONE_FIRST, self::ZONE_REBUY, self::ZONE_AGENT_STOCK], true)) {
            return $zoneType > 0;
        }
        if (!$this->supportsSchema()) {
            return $zoneType === self::ZONE_FIRST;
        }
        try {
            (new WzZoneRewardService($this))->onPaySuccess($order);
        } catch (\Throwable $e) {
            $this->mlog('[onPaySuccess|分区奖] 异常: ' . $e->getMessage());
        }
        if ($zoneType === self::ZONE_REBUY) {
            try {
                (new NormalZoneRegionRewardService($this))->onPaySuccess($order);
            } catch (\Throwable $e) {
                $this->mlog('[onPaySuccess|惠民区域奖] 异常: ' . $e->getMessage());
            }
            try {
                (new GreenPointsRewardService($this))->onPaySuccess($order);
            } catch (\Throwable $e) {
                $this->mlog('[onPaySuccess|绿色积分待结算] 异常: ' . $e->getMessage());
            }
        }
        if ($zoneType === self::ZONE_AGENT_STOCK) {
            try {
                (new AgentStockRegionRewardService($this))->onPaySuccess($order);
            } catch (\Throwable $e) {
                $this->mlog('[onPaySuccess|代理进货区级差奖] 异常: ' . $e->getMessage());
            }
            try {
                (new AgentStockDirectPushRewardService($this))->onPaySuccess($order);
            } catch (\Throwable $e) {
                $this->mlog('[onPaySuccess|代理进货区直推奖] 异常: ' . $e->getMessage());
            }
        }
        return true;
    }

    /**
     * 福满堂优品区(zone_type=1)支付成功玩法编排：
     *  - 贡献值(奇偶解锁)已在 EnergyRewardService 处理；
     *  - 此处按达标件数同步【消费券包额度】(每件+3000，个人从公共池可领取上限)。
     */
    private function dispatchRubyFirstZoneRewards(array $order): void
    {
        $buyerId = (int)($order['user_id'] ?? 0);
        $appId   = (int)($order['app_id'] ?? 0);
        $orderId = (int)($order['order_id'] ?? 0);

        // 仅当订单含「价格==报单金额」的达标优品区商品才触发玩法
        $qualify = (new EnergyRewardService())->getQualifyingBurstAmount($orderId);
        if (($qualify['units'] ?? 0) <= 0) {
            $this->mlog("[onPaySuccess|福满堂] order_id={$orderId} 无达标优品区商品(价格!=报单金额)，跳过消费券包额度");
            return;
        }

        try {
            (new VoucherPackService())->syncCap($buyerId, $appId);
        } catch (\Throwable $e) {
            $this->mlog('[onPaySuccess|福满堂消费券包额度] 异常: ' . $e->getMessage());
        }
    }

    public function onOrderCompleted($order)
    {
        $order    = $this->normalizeRewardOrder($order);
        $zoneType = (int)($order['zone_type'] ?? 0);
        if (!in_array($zoneType, [self::ZONE_FIRST, self::ZONE_REBUY, self::ZONE_AGENT_STOCK], true)) {
            $this->mlog("[onOrderCompleted] order_id=" . (int)($order['order_id'] ?? 0) . " zone_type={$zoneType} 跳过");
            return false;
        }
        if ($zoneType === self::ZONE_FIRST) {
            // 优品区奖励已在支付成功时发放；此处仅释放历史 release_trigger=receipt 的待结算记录
            if ($this->supportsSchema() && config('wz_reward.first_zone_agent.enabled', true)) {
                try {
                    $this->releaseOrderRewardsByTrigger($order, 'receipt');
                } catch (\Throwable $e) {
                    $this->mlog('[onOrderCompleted|优品区代理奖|历史兼容] 异常: ' . $e->getMessage());
                }
            }
            return true;
        }
        if (!$this->supportsSchema()) {
            $this->mlog('[onOrderCompleted] activity_reward_log 表未就绪，跳过');
            return true;
        }
        if ($zoneType === self::ZONE_REBUY) {
            Db::transaction(function () use ($order) {
                try {
                    (new WzZoneRewardService($this))->onOrderCompleted($order);
                } catch (\Throwable $e) {
                    $this->mlog('[onOrderCompleted|分区奖] 异常: ' . $e->getMessage());
                }
                try {
                    (new NormalZoneRegionRewardService($this))->onOrderCompleted($order);
                } catch (\Throwable $e) {
                    $this->mlog('[onOrderCompleted|消费区区域奖] 异常: ' . $e->getMessage());
                }
            });
            try {
                (new GreenPointsRewardService($this))->onOrderCompleted($order);
            } catch (\Throwable $e) {
                $this->mlog('[onOrderCompleted|绿色积分待结算] 异常: ' . $e->getMessage());
            }
        }
        if ($zoneType === self::ZONE_AGENT_STOCK) {
            Db::transaction(function () use ($order) {
                try {
                    (new AgentStockRegionRewardService($this))->onOrderCompleted($order);
                } catch (\Throwable $e) {
                    $this->mlog('[onOrderCompleted|代理进货区级差奖] 异常: ' . $e->getMessage());
                }
                try {
                    (new AgentStockDirectPushRewardService($this))->onOrderCompleted($order);
                } catch (\Throwable $e) {
                    $this->mlog('[onOrderCompleted|代理进货区直推奖] 异常: ' . $e->getMessage());
                }
                $this->releaseOrderRewardsByTrigger($order, 'receipt');
            });
        }
        return true;
    }

    public function getUserAsset($userId)
    {
        if (!$this->supportsSchema()) {
            return [];
        }
        $fields = [
            'user_id',
            'balance',
            'points',
            'green_points',
            'voucher',
            'digital_rights',
            'reward_freeze',
            'job_grade',
            'referee_id',
        ];
        $selectFields = array_values(array_filter($fields, function ($f) {
            return $this->hasColumn('user', $f);
        }));
        if (empty($selectFields)) {
            $selectFields = ['user_id', 'balance', 'points', 'referee_id'];
        }
        $user = Db::name('user')
            ->where('user_id', '=', $userId)
            ->field($selectFields)
            ->find();
        if (!$user) {
            return [];
        }
        $parentUserId = (int)($user['referee_id'] ?? 0);
        $parentBy = $parentUserId > 0 ? 'referee' : 'none';
        $parentNickName = '没有推荐人';
        if ($parentUserId > 0) {
            $parentNickName = (string)Db::name('user')
                ->where('user_id', '=', $parentUserId)
                ->value('nickName');
            if ($parentNickName === '') {
                $parentNickName = '没有推荐人';
            }
        }
        $user['parent_user_id'] = $parentUserId;
        $user['parent_by'] = $parentBy;
        $user['parent_nickName'] = $parentNickName;
        $user['referee_nickName'] = $parentNickName;
        return $user;
    }

    public function adminSetRewardFreeze($userId, $status)
    {
        if (!$this->supportsSchema() || !$this->hasColumn('user', 'reward_freeze')) {
            return false;
        }
        return Db::name('user')->where('user_id', '=', $userId)->update([
            'reward_freeze' => (int)$status,
            'update_time'   => time(),
        ]) !== false;
    }

    public function releaseOrderRewardsByTrigger($order, string $trigger = 'receipt'): void
    {
        $orderId = (int)$order['order_id'];
        $query   = Db::name('activity_reward_log')
            ->where('order_id', '=', $orderId)
            ->where('status', '=', self::REWARD_PENDING);

        if ($this->hasColumn('activity_reward_log', 'release_trigger')) {
            $query->where(function ($q) use ($trigger) {
                $q->where('release_trigger', '=', $trigger)
                    ->whereOr('release_trigger', '=', '')
                    ->whereOr(function ($sub) {
                        $sub->whereNull('release_trigger');
                    });
            });
        }

        $pendingLogs = $query->select()->toArray();
        $this->mlog("[releaseRewards] order_id={$orderId} trigger={$trigger} count=" . count($pendingLogs));
        if (!empty($pendingLogs)) {
            $this->releasePendingLogRows($pendingLogs, $order);
        }
    }

    public function releasePendingLogRows(array $pendingLogs, array $triggerOrder): void
    {
        if (empty($pendingLogs)) {
            return;
        }
        $orderId  = (int)$triggerOrder['order_id'];
        $zoneType = (int)($triggerOrder['zone_type'] ?? 0);
        $appId    = (int)($triggerOrder['app_id'] ?? 0);

        foreach ($pendingLogs as $log) {
            $this->releaseSinglePendingLog($log, $orderId, $zoneType, $appId);
        }
        $this->mlog("[releaseRewards] 完成 trigger_order_id={$orderId}");
    }

    public function createPendingRewardLogPublic(array $data): void
    {
        $this->createPendingRewardLog($data);
    }

    public function grantBalanceRewardForOrder(
        $userId,
        $amount,
        $remark,
        $orderId,
        $scene,
        $uniqueKey,
        $fromUserId = 0,
        $zoneType = 0,
        $appId = 0
    ) {
        return $this->grantBalanceReward(
            (int)$userId,
            (float)$amount,
            $remark,
            (int)$orderId,
            $scene,
            (string)$uniqueKey,
            (int)$fromUserId,
            (int)$zoneType,
            (int)$appId
        );
    }

    /** 入账失败原因（grantBalanceRewardForOrder 返回 false 时） */
    public function explainGrantBalanceRewardFailure(int $userId, float $amount, string $uniqueKey): string
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return '分红金额<=0';
        }
        if ($this->rewardLogExists($uniqueKey)) {
            return '幂等键已存在:' . $uniqueKey;
        }
        $user = Db::name('user')->where('user_id', '=', $userId)->find();
        if (!$user) {
            return '用户不存在';
        }
        if ($this->isUserRewardFrozen($user)) {
            return '用户奖励已冻结(reward_freeze=1)';
        }
        if (!$this->supportsSchema()) {
            return '奖励表结构未就绪:' . $this->getSchemaError();
        }
        return '未知原因';
    }

    public function getCloudRatioNum(int $ratioId, float $default = 0): float
    {
        try {
            if (!$this->hasTable('cloud_ratio')) {
                return $default;
            }
            $value = Db::name('cloud_ratio')
                ->where('ratio_id', '=', $ratioId)
                ->where('status', '=', 0)
                ->value('num');
            return $value === null ? $default : (float)$value;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function grantEnergyUnlockBalance($userId, $amount, $orderId, $fromUserId, $uniqueKey, $appId = 0)
    {
        return $this->grantBalanceReward(
            (int)$userId,
            (float)$amount,
            '直推能量解锁',
            (int)$orderId,
            'energy_unlock',
            (string)$uniqueKey,
            (int)$fromUserId,
            self::ZONE_FIRST,
            (int)$appId
        );
    }

    public function supportsSchema(): bool
    {
        if ($this->schemaChecked) {
            return $this->schemaReady;
        }
        $this->schemaChecked = true;
        $this->schemaError   = '';
        foreach (['user', 'order', 'activity_reward_log'] as $table) {
            if (!$this->hasTable($table)) {
                $this->schemaReady = false;
                $this->schemaError = "缺少数据表: {$table}";
                return false;
            }
        }
        $this->schemaReady = true;
        return true;
    }

    public function getSchemaError(): string
    {
        return $this->schemaError;
    }

    public function hasColumn($table, $column)
    {
        if (!isset($this->columns[$table])) {
            try {
                $rows = Db::query('SHOW COLUMNS FROM `' . config('database.connections.mysql.prefix') . $table . '`');
                $this->columns[$table] = array_column($rows, 'Field');
            } catch (\Throwable $e) {
                $this->columns[$table] = [];
            }
        }
        return in_array($column, $this->columns[$table], true);
    }

    /**
     * 补发消费区「已收货」订单奖励（幂等，用于历史漏发）
     */
    public function repairNormalZoneReceiptRewards(int $orderId): bool
    {
        if ($orderId <= 0 || !$this->supportsSchema()) {
            return false;
        }
        $order = $this->normalizeRewardOrder(['order_id' => $orderId]);
        if ((int)($order['zone_type'] ?? 0) !== self::ZONE_REBUY) {
            return false;
        }
        if ((int)($order['pay_status'] ?? 0) !== 20) {
            return false;
        }
        try {
            (new WzZoneRewardService($this))->onPaySuccess($order);
            (new NormalZoneRegionRewardService($this))->onPaySuccess($order);
        } catch (\Throwable $e) {
            $this->mlog('[repairNormalZone] 支付阶段补登记异常: ' . $e->getMessage());
        }
        if ((int)($order['receipt_status'] ?? 0) === 20) {
            $this->onOrderCompleted($order);
        }
        return true;
    }

    /**
     * 补发优品区支付成功奖励（能量 + 省市区代奖，幂等）
     */
    public function getLastRepairError(): string
    {
        return $this->lastRepairError;
    }

    /**
     * 补发前诊断（不修改数据）
     */
    public function explainRepairFailure(int $orderId): string
    {
        if ($orderId <= 0) {
            return 'order_id 无效';
        }
        if (!$this->hasTable('order')) {
            return '缺少 order 表';
        }
        $row = Db::name('order')->where('order_id', '=', $orderId)->find();
        if (!$row) {
            return '订单不存在';
        }
        if ((int)($row['is_delete'] ?? 0) === 1) {
            return '订单已删除(is_delete=1)';
        }
        if (!$this->supportsSchema()) {
            return '奖励表结构未就绪: ' . $this->getSchemaError();
        }
        $order = $this->normalizeRewardOrder(['order_id' => $orderId]);
        $zoneType = (int)($order['zone_type'] ?? 0);
        if ($zoneType <= 0) {
            return 'zone_type 为空且无法从商品推断';
        }
        if (!in_array($zoneType, [self::ZONE_FIRST, self::ZONE_REBUY, self::ZONE_AGENT_STOCK], true)) {
            return "zone_type={$zoneType}，仅支持 1优品/2消费/3进货区";
        }
        $payStatus = (int)($row['pay_status'] ?? $order['pay_status'] ?? 0);
        if ($payStatus !== 20) {
            return "pay_status={$payStatus}，非已支付(20)";
        }
        if ($this->lastRepairError !== '') {
            return $this->lastRepairError;
        }
        return '';
    }

    public function repairFirstZonePayRewards(int $orderId): bool
    {
        $this->lastRepairError = '';
        if ($orderId <= 0) {
            $this->lastRepairError = 'order_id 无效';
            return false;
        }
        if (!$this->supportsSchema()) {
            $this->lastRepairError = '奖励表结构未就绪: ' . $this->getSchemaError();
            return false;
        }
        $order = $this->normalizeRewardOrder(['order_id' => $orderId]);
        if ((int)($order['zone_type'] ?? 0) !== self::ZONE_FIRST) {
            $this->lastRepairError = 'zone_type=' . (int)($order['zone_type'] ?? 0) . '，非优品区(1)';
            return false;
        }
        if ((int)($order['pay_status'] ?? 0) !== 20) {
            $this->lastRepairError = 'pay_status=' . (int)($order['pay_status'] ?? 0) . '，非已支付(20)';
            return false;
        }
        try {
            $this->onPaySuccess($order);
            $this->mlog("[repairFirstZone] 已补跑 onPaySuccess order_id={$orderId}");
        } catch (\Throwable $e) {
            $this->lastRepairError = $e->getMessage();
            $this->mlog('[repairFirstZone] 异常: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * 补发代理进货区奖励（支付登记 + 已收货则释放，幂等）
     */
    public function repairAgentStockRewards(int $orderId): bool
    {
        if ($orderId <= 0 || !$this->supportsSchema()) {
            return false;
        }
        $order = $this->normalizeRewardOrder(['order_id' => $orderId]);
        if ((int)($order['zone_type'] ?? 0) !== self::ZONE_AGENT_STOCK) {
            return false;
        }
        if ((int)($order['pay_status'] ?? 0) !== 20) {
            return false;
        }
        try {
            $this->onPaySuccess($order);
            if ((int)($order['receipt_status'] ?? 0) === 20) {
                $this->onOrderCompleted($order);
            }
            $this->mlog("[repairAgentStock] 已补跑 order_id={$orderId}");
        } catch (\Throwable $e) {
            $this->mlog('[repairAgentStock] 异常: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * 按分区补发单订单奖励（幂等）
     */
    public function repairMissedOrderRewards(int $orderId): bool
    {
        $this->lastRepairError = '';
        $precheck = $this->explainRepairFailure($orderId);
        if ($precheck !== '') {
            $this->lastRepairError = $precheck;
            return false;
        }
        $order = $this->normalizeRewardOrder(['order_id' => $orderId]);
        $zoneType = (int)($order['zone_type'] ?? 0);
        if ($zoneType === self::ZONE_FIRST) {
            return $this->repairFirstZonePayRewards($orderId);
        }
        if ($zoneType === self::ZONE_REBUY) {
            return $this->repairNormalZoneReceiptRewards($orderId);
        }
        if ($zoneType === self::ZONE_AGENT_STOCK) {
            return $this->repairAgentStockRewards($orderId);
        }
        $this->lastRepairError = 'zone_type=' . $zoneType . ' 不在支持范围';
        return false;
    }

    /**
     * 查找「上线前支付、上线后收货」且可能漏奖的订单（默认优品区）
     *
     * @return list<array<string, mixed>>
     */
    public function findCrossVersionMissedOrders(int $deployTimestamp, int $zoneType = self::ZONE_FIRST, int $limit = 500): array
    {
        if ($deployTimestamp <= 0) {
            return [];
        }
        $query = Db::name('order')->alias('o')
            ->where('o.pay_status', '=', 20)
            ->where('o.is_delete', '=', 0)
            ->where('o.pay_time', '>', 0)
            ->where('o.pay_time', '<', $deployTimestamp)
            ->where('o.receipt_status', '=', 20)
            ->where('o.receipt_time', '>=', $deployTimestamp);
        $this->applyCrossVersionZoneFilter($query, $zoneType);
        $rows = $query
            ->field([
                'o.order_id', 'o.order_no', 'o.user_id', 'o.zone_type', 'o.pay_price',
                'o.pay_time', 'o.receipt_time', 'o.receipt_status',
            ])
            ->order('o.order_id', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();

        return $this->filterLikelyMissedRewardOrders($rows);
    }

    /**
     * 查找「上线前支付、仍未收货」且可能漏奖的订单（默认优品区）
     *
     * @return list<array<string, mixed>>
     */
    public function findCrossVersionUnreceivedOrders(int $deployTimestamp, int $zoneType = self::ZONE_FIRST, int $limit = 500): array
    {
        if ($deployTimestamp <= 0) {
            return [];
        }
        $query = Db::name('order')->alias('o')
            ->where('o.pay_status', '=', 20)
            ->where('o.is_delete', '=', 0)
            ->where('o.pay_time', '>', 0)
            ->where('o.pay_time', '<', $deployTimestamp)
            ->where('o.receipt_status', '=', 10);
        $this->applyCrossVersionZoneFilter($query, $zoneType);
        $rows = $query
            ->field([
                'o.order_id', 'o.order_no', 'o.user_id', 'o.zone_type', 'o.pay_price',
                'o.pay_time', 'o.receipt_time', 'o.receipt_status',
            ])
            ->order('o.order_id', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();

        return $this->filterLikelyMissedRewardOrders($rows);
    }

    /**
     * 合并筛查跨版本漏奖候选（默认优品区；scope: all | received | unreceived）
     *
     * @return list<array<string, mixed>>
     */
    public function findCrossVersionRepairCandidates(
        int $deployTimestamp,
        int $zoneType = self::ZONE_FIRST,
        int $limit = 500,
        string $scope = 'all'
    ): array {
        $scope = strtolower(trim($scope));
        if (!in_array($scope, ['all', 'received', 'unreceived'], true)) {
            $scope = 'all';
        }

        $merged = [];
        if ($scope === 'all' || $scope === 'received') {
            foreach ($this->findCrossVersionMissedOrders($deployTimestamp, $zoneType, $limit) as $row) {
                $row['miss_scenario'] = 'received_after_deploy';
                $merged[(int)$row['order_id']] = $row;
            }
        }
        if ($scope === 'all' || $scope === 'unreceived') {
            foreach ($this->findCrossVersionUnreceivedOrders($deployTimestamp, $zoneType, $limit) as $row) {
                $orderId = (int)$row['order_id'];
                if (!isset($merged[$orderId])) {
                    $row['miss_scenario'] = 'unreceived';
                    $merged[$orderId] = $row;
                }
            }
        }

        ksort($merged);
        return array_slice(array_values($merged), 0, $limit);
    }

    /**
     * @param \think\db\Query $query
     */
    private function applyCrossVersionZoneFilter($query, int $zoneType): void
    {
        if ($zoneType > 0) {
            $query->where('o.zone_type', '=', $zoneType);
            return;
        }
        $query->whereIn('o.zone_type', [self::ZONE_FIRST, self::ZONE_REBUY, self::ZONE_AGENT_STOCK]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function filterLikelyMissedRewardOrders(array $rows): array
    {
        $missed = [];
        foreach ($rows as $row) {
            if ($this->isLikelyMissedRewardOrder($row)) {
                $missed[] = $row;
            }
        }
        return $missed;
    }

    /**
     * 判断是否疑似漏发（仅用于筛查，补发接口本身幂等）
     */
    public function isLikelyMissedRewardOrder(array $order): bool
    {
        $orderId = (int)($order['order_id'] ?? 0);
        $zoneType = (int)($order['zone_type'] ?? 0);
        if ($orderId <= 0 || $zoneType <= 0) {
            return false;
        }
        if (!$this->supportsSchema()) {
            return true;
        }

        $rewardCnt = (int)Db::name('activity_reward_log')->where('order_id', '=', $orderId)->count();
        $energyCnt = (int)Db::name('user_energy_record')->where('source_order_id', '=', $orderId)->count();

        if ($zoneType === self::ZONE_FIRST) {
            return $energyCnt === 0 && $rewardCnt === 0;
        }
        if ($zoneType === self::ZONE_REBUY) {
            if ($rewardCnt === 0) {
                return true;
            }
            $pendingCnt = (int)Db::name('activity_reward_log')
                ->where('order_id', '=', $orderId)
                ->where('status', '=', self::REWARD_PENDING)
                ->count();
            return $pendingCnt > 0;
        }
        if ($zoneType === self::ZONE_AGENT_STOCK) {
            return $rewardCnt === 0;
        }
        return false;
    }

    /**
     * 统一订单字段（确认收货时模型可能缺 zone_type）
     */
    private function normalizeRewardOrder($order): array
    {
        $data = is_array($order) ? $order : (method_exists($order, 'toArray') ? $order->toArray() : (array)$order);
        $orderId = (int)($data['order_id'] ?? 0);
        if ($orderId <= 0) {
            return $data;
        }
        foreach (['zone_type', 'user_id', 'app_id', 'pay_price', 'pay_status', 'pay_time', 'receipt_status', 'receipt_time'] as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '' || $data[$field] === null) {
                if ($this->hasColumn('order', $field)) {
                    $val = Db::name('order')->where('order_id', '=', $orderId)->value($field);
                    if ($val !== null && $val !== '') {
                        $data[$field] = $val;
                    }
                }
            }
        }
        if ((int)($data['zone_type'] ?? 0) <= 0) {
            $inferred = $this->inferZoneTypeFromOrderProducts($orderId);
            if ($inferred > 0) {
                $data['zone_type'] = $inferred;
                if ($this->hasColumn('order', 'zone_type')) {
                    Db::name('order')->where('order_id', '=', $orderId)->update([
                        'zone_type'   => $inferred,
                        'update_time' => time(),
                    ]);
                }
            }
        }
        $data['zone_type'] = (int)($data['zone_type'] ?? 0);
        return $data;
    }

    private function inferZoneTypeFromOrderProducts(int $orderId): int
    {
        if (!$this->hasTable('order_product')) {
            return 0;
        }
        $productIds = Db::name('order_product')->where('order_id', '=', $orderId)->column('product_id');
        if (empty($productIds) || !$this->hasColumn('product', 'zone_type')) {
            return 0;
        }
        $zoneType = (int)Db::name('product')
            ->whereIn('product_id', $productIds)
            ->where('zone_type', '>', 0)
            ->order('zone_type', 'desc')
            ->value('zone_type');
        return $zoneType;
    }

    public function cancelOrderRewards(int $orderId): void
    {
        Db::name('activity_reward_log')
            ->where('order_id', '=', $orderId)
            ->where('status', '=', self::REWARD_PENDING)
            ->update([
                'status'      => self::REWARD_CANCELLED,
                'update_time' => time(),
            ]);
        $this->mlog("[cancelOrderRewards] order_id={$orderId}");
    }

    public function reclaimOrderRewards(int $orderId): void
    {
        $logs = Db::name('activity_reward_log')
            ->where('order_id', '=', $orderId)
            ->whereIn('status', [self::REWARD_PENDING, self::REWARD_RELEASED])
            ->select()->toArray();

        if (empty($logs)) {
            $this->mlog("[reclaimOrderRewards] order_id={$orderId} 无记录");
            return;
        }

        $now = time();
        foreach ($logs as $log) {
            $logId  = (int)$log['log_id'];
            $userId = (int)$log['user_id'];

            if ((int)$log['status'] === self::REWARD_PENDING) {
                Db::name('activity_reward_log')->where('log_id', '=', $logId)->update([
                    'status'      => self::REWARD_CANCELLED,
                    'update_time' => $now,
                ]);
                continue;
            }

            $assetType      = (string)($log['asset_type'] ?? '');
            $releaseBalance = (float)($log['release_balance'] ?? 0);
            $releasePoints  = (float)($log['release_points'] ?? 0);
            $remark         = (string)($log['remark'] ?? '奖励') . '（退货扣回）';
            $amount         = -((float)($log['amount'] ?? 0));
            $reclaimKey     = 'reclaim_' . $logId;
            if (Db::name('activity_reward_log')->where('unique_key', '=', $reclaimKey)->value('log_id')) {
                continue;
            }

            $user = Db::name('user')->where('user_id', '=', $userId)->lock(true)->find();
            if (!$user) {
                continue;
            }

            if ($assetType === GreenPointsRewardService::ASSET_GREEN_POINTS && GreenPointsService::supportsSchema()) {
                $deductGreen = (new GreenPointsService())->reclaimOrderGift(
                    $userId,
                    (float)($log['amount'] ?? 0),
                    $orderId,
                    $remark,
                    (int)($log['app_id'] ?? 0)
                );
                Db::name('activity_reward_log')->insert([
                    'unique_key'      => $reclaimKey,
                    'user_id'         => $userId,
                    'from_user_id'    => (int)($log['from_user_id'] ?? 0),
                    'order_id'        => $orderId,
                    'zone_type'       => (int)($log['zone_type'] ?? 0),
                    'scene'           => (string)($log['scene'] ?? ''),
                    'asset_type'      => $assetType,
                    'amount'          => $amount,
                    'reward_month'    => (string)($log['reward_month'] ?? date('Ym')),
                    'status'          => self::REWARD_RELEASED,
                    'release_balance' => 0,
                    'release_points'  => -$deductGreen,
                    'release_time'    => $now,
                    'remark'          => $remark,
                    'app_id'          => (int)($log['app_id'] ?? 0),
                    'create_time'     => $now,
                    'update_time'     => $now,
                ]);
                continue;
            }

            $updateFields  = ['update_time' => $now];
            $deductBalance = 0;
            $deductPoints  = 0;
            if ($releaseBalance > 0) {
                $deductBalance = min($releaseBalance, max(0, (float)$user['balance']));
                if ($deductBalance > 0) {
                    $updateFields['balance'] = Db::raw('balance-' . $deductBalance);
                    $this->writeBillRecord($user, -$deductBalance, 1, $remark, $orderId);
                    BalanceLogModel::add(BalanceLogSceneEnum::REWARD, [
                        'user_id' => $userId,
                        'money'   => -$deductBalance,
                        'app_id'  => (int)($log['app_id'] ?? 0),
                        'remark'  => $remark,
                    ], [$remark]);
                }
            }
            if ($releasePoints > 0) {
                $deductPoints = min($releasePoints, max(0, (float)$user['points']));
                if ($deductPoints > 0) {
                    $updateFields['points'] = Db::raw('points-' . $deductPoints);
                    $this->writeBillRecord($user, -$deductPoints, 3, $remark, $orderId);
                    PointsLogModel::add([
                        'user_id'  => $userId,
                        'value'    => -$deductPoints,
                        'describe' => $remark,
                        'app_id'   => (int)($log['app_id'] ?? 0),
                    ]);
                }
            }
            Db::name('user')->where('user_id', '=', $userId)->update($updateFields);

            Db::name('activity_reward_log')->insert([
                'unique_key'      => $reclaimKey,
                'user_id'         => $userId,
                'from_user_id'    => (int)($log['from_user_id'] ?? 0),
                'order_id'        => $orderId,
                'zone_type'       => (int)($log['zone_type'] ?? 0),
                'scene'           => (string)($log['scene'] ?? ''),
                'asset_type'      => (string)($log['asset_type'] ?? 'mixed'),
                'amount'          => $amount,
                'reward_month'    => (string)($log['reward_month'] ?? date('Ym')),
                'status'          => self::REWARD_RELEASED,
                'release_balance' => -$deductBalance,
                'release_points'  => -$deductPoints,
                'release_time'    => $now,
                'remark'          => $remark,
                'app_id'          => (int)($log['app_id'] ?? 0),
                'create_time'     => $now,
                'update_time'     => $now,
            ]);
        }
        $this->mlog("[reclaimOrderRewards] order_id={$orderId} 共" . count($logs) . '条');
    }

    private function releaseSinglePendingLog(array $log, int $orderId, int $zoneType, int $appId): void
    {
        $userId    = (int)$log['user_id'];
        $amount    = (float)$log['amount'];
        $scene     = (string)$log['scene'];
        $assetType = (string)$log['asset_type'];
        $remark    = (string)$log['remark'];
        $logId     = (int)$log['log_id'];

        if ($amount <= 0) {
            Db::name('activity_reward_log')->where('log_id', '=', $logId)->update([
                'status'       => self::REWARD_RELEASED,
                'release_time' => time(),
                'update_time'  => time(),
            ]);
            return;
        }

        $user = Db::name('user')->where('user_id', '=', $userId)->lock(true)->find();
        if (!$user) {
            return;
        }
        if ($this->isUserRewardFrozen($user)) {
            $this->mlog("[releaseRewards] log_id={$logId} user_id={$userId} 奖励已冻结，跳过");
            return;
        }

        $now           = time();
        $sourceOrderId = (int)($log['order_id'] ?? $orderId);

        if ($assetType === GreenPointsRewardService::ASSET_GREEN_POINTS) {
            if (GreenPointsService::supportsSchema()) {
                $releaseOrder  = $this->normalizeRewardOrder(['order_id' => $sourceOrderId]);
                $releaseAmount = GreenPointsService::calcOrderGreenPointsAmount($releaseOrder);
                $amount        = $releaseAmount;
                if ($releaseAmount > 0) {
                    (new GreenPointsService())->creditOrderGift($userId, $releaseAmount, $sourceOrderId, $remark, $appId);
                }
            }
            $releaseBalance = 0;
            $releasePoints  = $amount;
        } elseif ($assetType === 'points') {
            Db::name('user')->where('user_id', '=', $userId)->update([
                'points'       => Db::raw('points+' . $amount),
                'total_points' => Db::raw('total_points+' . $amount),
                'update_time'  => $now,
            ]);
            $this->writeBillRecord($user, $amount, 3, $remark, $sourceOrderId);
            PointsLogModel::add([
                'user_id'  => $userId,
                'value'    => $amount,
                'describe' => $remark,
                'app_id'   => $appId,
            ]);
            $releaseBalance = 0;
            $releasePoints  = $amount;
        } elseif ($assetType === 'balance' || $scene === 'direct_push') {
            Db::name('user')->where('user_id', '=', $userId)->update([
                'balance'     => Db::raw('balance+' . $amount),
                'update_time' => $now,
            ]);
            $this->writeBillRecord($user, $amount, 1, $remark, $sourceOrderId);
            BalanceLogModel::add(BalanceLogSceneEnum::REWARD, [
                'user_id' => $userId,
                'money'   => $amount,
                'app_id'  => $appId,
                'remark'  => $remark,
            ], [$remark]);
            $releaseBalance = $amount;
            $releasePoints  = 0;
        } else {
            $ratioConfig   = $this->getRewardRatioConfig($appId ?: 10001);
            $balanceRatio  = $ratioConfig['balance'] / 100;
            $balanceAmount = round($amount * $balanceRatio, 2);
            $pointsAmount  = round($amount - $balanceAmount, 2);
            Db::name('user')->where('user_id', '=', $userId)->update([
                'balance'      => Db::raw('balance+' . $balanceAmount),
                'points'       => Db::raw('points+' . $pointsAmount),
                'total_points' => Db::raw('total_points+' . $pointsAmount),
                'update_time'  => $now,
            ]);
            $this->writeBillRecord($user, $balanceAmount, 1, $remark, $sourceOrderId);
            $this->writeBillRecord($user, $pointsAmount, 3, $remark, $sourceOrderId);
            BalanceLogModel::add(BalanceLogSceneEnum::REWARD, [
                'user_id' => $userId,
                'money'   => $balanceAmount,
                'app_id'  => $appId,
                'remark'  => $remark,
            ], [$remark]);
            if ($pointsAmount > 0) {
                PointsLogModel::add([
                    'user_id'  => $userId,
                    'value'    => $pointsAmount,
                    'describe' => $remark,
                    'app_id'   => $appId,
                ]);
            }
            $releaseBalance = $balanceAmount;
            $releasePoints  = $pointsAmount;
        }

        Db::name('activity_reward_log')->where('log_id', '=', $logId)->update([
            'status'          => self::REWARD_RELEASED,
            'release_balance' => $releaseBalance,
            'release_points'  => $releasePoints,
            'release_time'    => $now,
            'update_time'     => $now,
        ]);
        $this->mlog("[releaseRewards] log_id={$logId} user_id={$userId} scene={$scene} amount={$amount}");
    }

    private function createPendingRewardLog(array $data): void
    {
        if (Db::name('activity_reward_log')->where('unique_key', '=', $data['unique_key'])->value('log_id')) {
            return;
        }
        $now = time();
        $defaults = [
            'unique_key'      => '',
            'user_id'         => 0,
            'from_user_id'    => 0,
            'order_id'        => 0,
            'zone_type'       => 0,
            'scene'           => '',
            'asset_type'      => 'mixed',
            'amount'          => 0,
            'reward_month'    => date('Ym'),
            'status'          => self::REWARD_PENDING,
            'release_balance' => 0,
            'release_points'  => 0,
            'release_time'    => 0,
            'remark'          => '',
            'app_id'          => 0,
            'create_time'     => $now,
            'update_time'     => $now,
        ];
        if ($this->hasColumn('activity_reward_log', 'release_trigger')) {
            $defaults['release_trigger'] = 'receipt';
        }
        Db::name('activity_reward_log')->insert(array_merge($defaults, $data, [
            'status'       => self::REWARD_PENDING,
            'release_time' => 0,
            'update_time'  => $now,
        ]));
    }

    private function isUserRewardFrozen(array $user): bool
    {
        return $this->hasColumn('user', 'reward_freeze') && (int)($user['reward_freeze'] ?? 0) === 1;
    }

    private function grantBalanceReward($userId, $amount, $remark, $orderId, $scene, $uniqueKey, $fromUserId = 0, $zoneType = 0, $appId = 0)
    {
        $amount = round((float)$amount, 2);
        if ($amount <= 0 || $this->rewardLogExists($uniqueKey)) {
            return false;
        }
        $user = Db::name('user')->where('user_id', '=', $userId)->lock(true)->find();
        if (!$user || $this->isUserRewardFrozen($user)) {
            return false;
        }
        Db::name('user')->where('user_id', '=', $userId)->update([
            'balance'     => Db::raw('balance+' . $amount),
            'update_time' => time(),
        ]);
        $this->writeBillRecord($user, $amount, 1, $remark, $orderId);
        $rewardLogId = $this->createReleasedRewardLog([
            'unique_key'        => $uniqueKey,
            'user_id'           => $userId,
            'from_user_id'      => $fromUserId,
            'order_id'          => $orderId,
            'zone_type'         => $zoneType,
            'scene'             => $scene,
            'asset_type'        => 'balance',
            'amount'            => $amount,
            'reward_month'      => date('Ym'),
            'remark'            => $remark,
            'release_balance'   => $amount,
            'release_points'    => 0,
            'app_id'            => $appId,
        ]);
        BalanceLogModel::add(BalanceLogSceneEnum::REWARD, [
            'user_id'       => $userId,
            'money'         => $amount,
            'app_id'        => $appId,
            'remark'        => $remark,
            'source_log_id' => $rewardLogId,
        ], [$remark]);
        return true;
    }

    private function createReleasedRewardLog(array $data): int
    {
        $now = time();
        Db::name('activity_reward_log')->insert(array_merge([
            'unique_key'      => '',
            'user_id'         => 0,
            'from_user_id'    => 0,
            'order_id'        => 0,
            'zone_type'       => 0,
            'scene'           => '',
            'asset_type'      => 'mixed',
            'amount'          => 0,
            'reward_month'    => date('Ym'),
            'status'          => self::REWARD_RELEASED,
            'release_balance' => 0,
            'release_points'  => 0,
            'release_time'    => $now,
            'remark'          => '',
            'app_id'          => 0,
            'create_time'     => $now,
            'update_time'     => $now,
        ], $data, [
            'status'       => self::REWARD_RELEASED,
            'release_time' => $now,
            'update_time'  => $now,
        ]));
        return (int)Db::name('activity_reward_log')->getLastInsID();
    }

    private function rewardLogExists($uniqueKey): bool
    {
        return (bool)Db::name('activity_reward_log')->where('unique_key', '=', $uniqueKey)->value('log_id');
    }

    private function getRewardRatioConfig($appId = 10001): array
    {
        static $cache = [];
        $cacheKey = 'ratio_' . $appId;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }
        $default = ['balance' => 70, 'points' => 30];
        try {
            if (!$this->hasTable('reward_config')) {
                return $cache[$cacheKey] = $default;
            }
            $rows = Db::name('reward_config')
                ->where('app_id', '=', $appId)
                ->whereIn('config_key', ['balance_ratio', 'points_ratio'])
                ->column('config_value', 'config_key');
            $b = isset($rows['balance_ratio']) ? (float)$rows['balance_ratio'] : 70;
            $p = isset($rows['points_ratio']) ? (float)$rows['points_ratio'] : 30;
            if ($b + $p != 100) {
                $b = 70;
                $p = 30;
            }
            $cache[$cacheKey] = ['balance' => $b, 'points' => $p];
        } catch (\Throwable $e) {
            $cache[$cacheKey] = $default;
        }
        return $cache[$cacheKey];
    }

    private function writeBillRecord($user, $amount, $curType, $remark, $orderId): void
    {
        if ($amount == 0 || !$this->hasTable('cloud_bill_record')) {
            return;
        }
        $oldNum = $curType === 1
            ? round((float)$user['balance'], 2)
            : round((float)$user['points'], 2);
        $newNum = round($oldNum + $amount, 2);
        Db::name('cloud_bill_record')->insert([
            'user_id'  => (int)$user['user_id'],
            'price'    => abs($amount),
            'ac_type'  => $amount > 0 ? 1 : 2,
            'cur_type' => $curType,
            'profit'   => $remark,
            'order_id' => (int)$orderId,
            'old_num'  => $oldNum,
            'new_num'  => $newNum,
        ]);
    }

    private function hasTable(string $table): bool
    {
        try {
            $fullName = config('database.connections.mysql.prefix') . $table;
            return !empty(Db::query("SHOW TABLES LIKE '" . addslashes($fullName) . "'"));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function mlog(string $message): void
    {
        $file = app()->getRootPath() . 'reward_debug.log';
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
