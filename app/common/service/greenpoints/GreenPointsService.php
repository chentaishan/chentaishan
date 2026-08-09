<?php

namespace app\common\service\greenpoints;

use app\common\enum\user\digitalRights\DigitalRightsLogSceneEnum;
use app\common\enum\user\greenPoints\GreenPointsLogSceneEnum;
use app\common\enum\user\voucher\VoucherLogSceneEnum;
use app\common\library\helper;
use app\common\model\user\DigitalRightsLog as DigitalRightsLogModel;
use app\common\model\user\GreenPointsLog as GreenPointsLogModel;
use app\common\model\user\User as UserModel;
use app\common\model\user\VoucherLog as VoucherLogModel;
use think\facade\Db;

/**
 * 绿色积分 / 消费券 / 数权
 */
class GreenPointsService
{
    private static $schemaReady = null;

    /** @var array<int, string> */
    private static $cloudRatioCache = [];

    private static $cloudRatioHasStatus = null;

    public static function supportsSchema(): bool
    {
        if (self::$schemaReady !== null) {
            return self::$schemaReady;
        }
        try {
            self::$schemaReady = self::hasColumn('user', 'green_points')
                && self::hasColumn('product', 'green_points');
        } catch (\Throwable $e) {
            self::$schemaReady = false;
        }
        return self::$schemaReady;
    }

    /** 兑换比例（jjjshop_cloud_ratio ratio_id 35/36） */
    public static function getExchangeConfig(): array
    {
        $cfg      = config('wz_reward.green_points', []);
        $ratioId  = $cfg['ratio_id'] ?? [];
        $defaults = $cfg['defaults'] ?? [];
        return [
            'voucher_ratio'        => (float)self::getCloudRatioNum(
                (int)($ratioId['voucher_ratio'] ?? 35),
                $defaults['voucher_ratio'] ?? 1
            ),
            'digital_rights_ratio' => (float)self::getCloudRatioNum(
                (int)($ratioId['digital_rights_ratio'] ?? 36),
                $defaults['digital_rights_ratio'] ?? 1
            ),
        ];
    }

    /**
     * 读取 jjjshop_cloud_ratio.num（status=0 启用）
     */
    public static function getCloudRatioNum(int $ratioId, $default): float
    {
        if ($ratioId <= 0) {
            return (float)$default;
        }
        if (isset(self::$cloudRatioCache[$ratioId])) {
            return (float)self::$cloudRatioCache[$ratioId];
        }
        try {
            if (!self::hasTable('cloud_ratio')) {
                return (float)(self::$cloudRatioCache[$ratioId] = (string)$default);
            }
            $query = Db::name('cloud_ratio')->where('ratio_id', '=', $ratioId);
            if (self::cloudRatioHasStatusColumn()) {
                $query->where('status', '=', 0);
            }
            $value = $query->value('num');
            if ($value === null || $value === '') {
                return (float)(self::$cloudRatioCache[$ratioId] = (string)$default);
            }
            return (float)(self::$cloudRatioCache[$ratioId] = (string)$value);
        } catch (\Throwable $e) {
            return (float)(self::$cloudRatioCache[$ratioId] = (string)$default);
        }
    }

    private static function cloudRatioHasStatusColumn(): bool
    {
        if (self::$cloudRatioHasStatus !== null) {
            return self::$cloudRatioHasStatus;
        }
        try {
            $fullName = config('database.connections.mysql.prefix') . 'cloud_ratio';
            $rows     = Db::query("SHOW COLUMNS FROM `{$fullName}` LIKE 'status'");
            self::$cloudRatioHasStatus = !empty($rows);
        } catch (\Throwable $e) {
            self::$cloudRatioHasStatus = false;
        }
        return self::$cloudRatioHasStatus;
    }

    private static function hasTable(string $table): bool
    {
        try {
            $fullName = config('database.connections.mysql.prefix') . $table;
            Db::query('SELECT 1 FROM `' . $fullName . '` LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 待结算释放：确认收货后入账绿色积分（由 ActivityRewardService 调用）
     */
    public function creditOrderGift(int $userId, float $amount, int $orderId, string $remark, int $appId = 0): void
    {
        if (!self::supportsSchema() || $userId <= 0 || $amount <= 0) {
            return;
        }
        $amount = round($amount, 2);
        $user = Db::name('user')->where('user_id', '=', $userId)->lock(true)->find();
        if (!$user) {
            return;
        }
        if ($this->isUserRewardFrozen($user)) {
            return;
        }
        $after = (float)helper::bcadd((string)$user['green_points'], (string)$amount, 2);
        Db::name('user')->where('user_id', '=', $userId)->update([
            'green_points' => $after,
            'update_time'  => time(),
        ]);
        GreenPointsLogModel::add([
            'user_id'  => $userId,
            'scene'    => GreenPointsLogSceneEnum::ORDER_GIFT,
            'value'    => $amount,
            'describe' => $remark !== '' ? $remark : '确认收货赠送绿色积分',
            'remark'   => '订单ID：' . $orderId,
            'order_id' => $orderId,
            'app_id'   => $appId ?: (int)($user['app_id'] ?? 0),
        ]);
    }

    /**
     * 扣回已发放绿色积分（售后退货）
     */
    public function reclaimOrderGift(int $userId, float $amount, int $orderId, string $remark, int $appId = 0): float
    {
        if (!self::supportsSchema() || $userId <= 0 || $amount <= 0) {
            return 0.0;
        }
        $user = Db::name('user')->where('user_id', '=', $userId)->lock(true)->find();
        if (!$user) {
            return 0.0;
        }
        $deduct = min($amount, max(0, (float)$user['green_points']));
        if ($deduct <= 0) {
            return 0.0;
        }
        $after = (float)helper::bcsub((string)$user['green_points'], (string)$deduct, 2);
        Db::name('user')->where('user_id', '=', $userId)->update([
            'green_points' => $after,
            'update_time'  => time(),
        ]);
        GreenPointsLogModel::add([
            'user_id'  => $userId,
            'scene'    => GreenPointsLogSceneEnum::REFUND,
            'value'    => -$deduct,
            'describe' => $remark,
            'remark'   => '订单ID：' . $orderId,
            'order_id' => $orderId,
            'app_id'   => $appId ?: (int)($user['app_id'] ?? 0),
        ]);
        return $deduct;
    }

    private function isUserRewardFrozen(array $user): bool
    {
        return self::hasColumn('user', 'reward_freeze') && (int)($user['reward_freeze'] ?? 0) === 1;
    }

    /**
     * 绿色积分兑换抵扣券或数权
     *
     * @return array{green_points:float,voucher:float,digital_rights:float}
     */
    public function exchange(int $userId, string $type, float $amount): array
    {
        if (!self::supportsSchema()) {
            throw new \RuntimeException('请先执行数据库升级脚本 database/20260601_green_points.sql');
        }
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \RuntimeException('兑换数量须大于0');
        }
        $exchange = self::getExchangeConfig();

        $user = UserModel::detail($userId);
        if (!$user) {
            throw new \RuntimeException('用户不存在');
        }
        if ((float)$user['green_points'] < $amount) {
            throw new \RuntimeException('绿色积分不足');
        }

        if ($type === 'voucher') {
            $ratio = (float)$exchange['voucher_ratio'];
            if ($ratio <= 0) {
                throw new \RuntimeException('未配置抵扣券兑换比例');
            }
            $gain = round($amount * $ratio, 2);
            if ($gain <= 0) {
                throw new \RuntimeException('兑换结果无效，请调整比例或数量');
            }
            Db::transaction(function () use ($userId, $amount, $gain, $user) {
                $this->changeGreenPoints($userId, -$amount, GreenPointsLogSceneEnum::EXCHANGE_VOUCHER, '兑换抵扣券', '');
                $this->changeVoucher($userId, $gain, VoucherLogSceneEnum::EXCHANGE, '绿色积分兑换抵扣券', '');
            });
        } elseif ($type === 'digital_rights') {
            $ratio = (float)$exchange['digital_rights_ratio'];
            if ($ratio <= 0) {
                throw new \RuntimeException('未配置数权兑换比例');
            }
            $gain = round($amount * $ratio, 2);
            if ($gain <= 0) {
                throw new \RuntimeException('兑换结果无效，请调整比例或数量');
            }
            Db::transaction(function () use ($userId, $amount, $gain) {
                $this->changeGreenPoints($userId, -$amount, GreenPointsLogSceneEnum::EXCHANGE_DIGITAL, '兑换数权', '');
                $this->changeDigitalRights($userId, $gain, DigitalRightsLogSceneEnum::EXCHANGE, '绿色积分兑换数权', '');
            });
        } else {
            throw new \RuntimeException('兑换类型无效');
        }

        return $this->getUserBalances($userId);
    }

    /**
     * 未支付取消 / 超时关闭：已实扣则退回；仅预占未付则清零订单 voucher_money
     */
    public function refundVoucherForCancelledOrder(array $order): void
    {
        if (!self::supportsSchema()) {
            return;
        }
        $orderId      = (int)($order['order_id'] ?? 0);
        $userId       = (int)($order['user_id'] ?? 0);
        $voucherMoney = round((float)($order['voucher_money'] ?? 0), 2);
        if ($orderId <= 0 || $userId <= 0 || $voucherMoney <= 0) {
            return;
        }
        if (!$this->hasVoucherDeductedForOrder($orderId)) {
            Db::name('order')->where('order_id', '=', $orderId)->update([
                'voucher_money' => 0,
                'update_time'   => time(),
            ]);
            return;
        }
        $exists = (int)Db::name('user_voucher_log')
            ->where('order_id', '=', $orderId)
            ->where('scene', '=', VoucherLogSceneEnum::ORDER_REFUND)
            ->count();
        if ($exists > 0) {
            return;
        }
        $orderNo = (string)($order['order_no'] ?? $orderId);
        $this->changeVoucher(
            $userId,
            $voucherMoney,
            VoucherLogSceneEnum::ORDER_REFUND,
            '订单取消退回消费券：' . $orderNo,
            '',
            $orderId
        );
    }

    /** 订单是否已扣减过消费券（支付页重复提交幂等） */
    public function hasVoucherDeductedForOrder(int $orderId): bool
    {
        if ($orderId <= 0 || !self::supportsSchema()) {
            return false;
        }
        return (int)Db::name('user_voucher_log')
                ->where('order_id', '=', $orderId)
                ->where('scene', '=', VoucherLogSceneEnum::ORDER_DEDUCT)
                ->count() > 0;
    }

    /**
     * 支付页：计算本次使用消费券金额
     *
     * @param float|null $voucherCap 兼容旧参数；福满堂消费券按余额式混合支付，仅受订单金额和用户消费券余额限制。
     */
    public static function calcPayVoucherAmount(array $params, float $userVoucher, float $orderPayTotal, ?float $voucherCap = null): float
    {
        if ((int)($params['use_voucher'] ?? 0) !== 1 || $orderPayTotal <= 0 || $userVoucher <= 0) {
            return 0.0;
        }
        $max = min($userVoucher, $orderPayTotal);
        $request = isset($params['voucher_money']) ? (float)$params['voucher_money'] : 0;
        if ($request > 0) {
            return round(min($request, $max), 2);
        }
        return 0.0;
    }

    /** 商品表是否已升级 voucher_max_money 字段（旧抵扣券逻辑兼容） */
    public static function supportsVoucherMaxSchema(): bool
    {
        return self::supportsSchema() && self::hasColumn('product', 'voucher_max_money');
    }

    /**
     * 结算台：计算可用消费券上限。福满堂消费券按余额式混合支付，不按商品配置限制。
     */
    public static function calcCheckoutVoucherMax(int $zoneType, array $productList, float $orderPayPrice): float
    {
        $orderPayPrice = round($orderPayPrice, 2);
        if ($orderPayPrice <= 0 || !self::supportsSchema()) {
            return 0.0;
        }
        return $orderPayPrice;
    }

    /**
     * 已下单：计算单笔订单可用消费券上限。
     */
    public static function calcOrderVoucherCap(array $order): float
    {
        $payPrice = round((float)($order['pay_price'] ?? 0), 2);
        if ($payPrice <= 0 || !self::supportsSchema()) {
            return 0.0;
        }
        return $payPrice;
    }

    /** 多笔待支付订单合计可用消费券上限 */
    public static function calcOrdersVoucherCap(array $orders): float
    {
        $sum = 0.0;
        foreach ($orders as $order) {
            $sum = (float)helper::bcadd((string)$sum, (string)self::calcOrderVoucherCap($order), 2);
        }
        return round($sum, 2);
    }

    /** 订单是否允许使用消费券 */
    public static function canOrderUseVoucher(array $order): bool
    {
        return self::calcOrderVoucherCap($order) > 0;
    }

    /**
     * @param array<int, array> $productList 含 voucher_max_money、total_num
     */
    public static function sumProductVoucherMax(array $productList): float
    {
        $sum = 0.0;
        foreach ($productList as $product) {
            $per = (float)($product['voucher_max_money'] ?? 0);
            if ($per <= 0) {
                continue;
            }
            $num = max(1, (int)($product['total_num'] ?? 1));
            $sum = (float)helper::bcadd((string)$sum, (string)helper::bcmul((string)$per, (string)$num, 2), 2);
        }
        return round($sum, 2);
    }

    /** 从订单商品行汇总 voucher_max_money × 数量 */
    public static function calcOrderProductVoucherMaxFromLines(int $orderId): float
    {
        if ($orderId <= 0 || !self::supportsVoucherMaxSchema()) {
            return 0.0;
        }
        $lines = Db::name('order_product')->alias('op')
            ->join('product p', 'p.product_id = op.product_id')
            ->where('op.order_id', '=', $orderId)
            ->field('op.total_num,p.voucher_max_money')
            ->select()
            ->toArray();
        return self::sumProductVoucherMax($lines);
    }

    /**
     * 支付发起：仅写入订单 voucher_money（预占），支付成功后再实扣用户消费券
     *
     * @param array<int, array> $orders 待支付订单行（含 order_id、pay_price、order_no、zone_type）
     */
    public function reserveVoucherAtPay(array $orders, float $totalVoucher): void
    {
        if (!self::supportsSchema()) {
            return;
        }
        if ($totalVoucher <= 0) {
            $this->clearReservedVoucherOnOrders($orders);
            return;
        }

        $eligible = [];
        $eligiblePayTotal = 0.0;
        foreach ($orders as $order) {
            $orderId = (int)$order['order_id'];
            if ($this->hasVoucherDeductedForOrder($orderId)) {
                throw new \RuntimeException('订单积分已支付扣减，请勿重复操作');
            }
            $payPrice = (float)($order['pay_price'] ?? 0);
            if ($payPrice <= 0) {
                continue;
            }
            $order['voucher_cap'] = $payPrice;
            $eligible[] = $order;
            $eligiblePayTotal = (float)helper::bcadd((string)$eligiblePayTotal, (string)$payPrice, 2);
        }
        if ($eligiblePayTotal <= 0) {
            throw new \RuntimeException('订单金额异常，无法使用积分');
        }
        if ($totalVoucher > $eligiblePayTotal + 0.001) {
            throw new \RuntimeException('积分金额不能超过订单应付金额');
        }

        $eligibleIds = array_column($eligible, 'order_id');
        Db::transaction(function () use ($orders, $eligible, $eligibleIds, $eligiblePayTotal, $totalVoucher) {
            foreach ($orders as $order) {
                $orderId = (int)$order['order_id'];
                if (!in_array($orderId, $eligibleIds, true)) {
                    Db::name('order')->where('order_id', '=', $orderId)->update([
                        'voucher_money' => 0,
                        'update_time'   => time(),
                    ]);
                }
            }
            $allocated = 0.0;
            $lastIndex = count($eligible) - 1;
            foreach ($eligible as $idx => $order) {
                $orderId = (int)$order['order_id'];
                $orderCap = (float)$order['voucher_cap'];
                if ($idx === $lastIndex) {
                    $share = round($totalVoucher - $allocated, 2);
                } else {
                    $share = round($totalVoucher * $orderCap / $eligiblePayTotal, 2);
                    $allocated = (float)helper::bcadd((string)$allocated, (string)$share, 2);
                }
                $share = min($share, $orderCap);
                Db::name('order')->where('order_id', '=', $orderId)->update([
                    'voucher_money' => max(0, $share),
                    'update_time'   => time(),
                ]);
            }
        });
    }

    /** 取消预占（未支付且未实扣） */
    public function clearReservedVoucherOnOrders(array $orders): void
    {
        if (!self::supportsSchema()) {
            return;
        }
        foreach ($orders as $order) {
            $orderId = (int)($order['order_id'] ?? 0);
            if ($orderId <= 0 || $this->hasVoucherDeductedForOrder($orderId)) {
                continue;
            }
            Db::name('order')->where('order_id', '=', $orderId)->update([
                'voucher_money' => 0,
                'update_time'   => time(),
            ]);
        }
    }

    /** 支付成功：实扣订单上预占的消费券（与余额 balance 扣减时机一致） */
    public function deductVoucherOnPaySuccess(array $order): void
    {
        if (!self::supportsSchema()) {
            return;
        }
        $orderId = (int)($order['order_id'] ?? 0);
        $userId  = (int)($order['user_id'] ?? 0);
        $amount  = round((float)($order['voucher_money'] ?? 0), 2);
        if ($orderId <= 0 || $userId <= 0 || $amount <= 0 || $this->hasVoucherDeductedForOrder($orderId)) {
            return;
        }
        $this->deductVoucherOnOrder(
            $userId,
            $amount,
            $orderId,
            (string)($order['order_no'] ?? $orderId)
        );
    }

    /** 待支付订单预占消费券合计（order.voucher_money，不要求已实扣） */
    public static function sumReservedVoucherOnOrders(array $orders): float
    {
        $sum = 0.0;
        foreach ($orders as $order) {
            $sum = (float)helper::bcadd((string)$sum, (string)($order['voucher_money'] ?? 0), 2);
        }
        return round($sum, 2);
    }

    /** @deprecated 使用 sumReservedVoucherOnOrders */
    public static function sumAppliedVoucherOnOrders(array $orders): float
    {
        return self::sumReservedVoucherOnOrders($orders);
    }

    /** @deprecated 福满堂消费券不再按分区限制 */
    public static function isOrderAllowVoucher(int $zoneType): bool
    {
        return self::supportsSchema();
    }

    /**
     * 下单扣减消费券
     */
    public function deductVoucherOnOrder(int $userId, float $money, int $orderId, string $orderNo): void
    {
        if ($money <= 0 || !self::supportsSchema()) {
            return;
        }
        $this->changeVoucher(
            $userId,
            -$money,
            VoucherLogSceneEnum::ORDER_DEDUCT,
            '订单使用积分：' . $orderNo,
            '',
            $orderId
        );
    }

    public function getUserBalances(int $userId): array
    {
        if (!self::supportsSchema()) {
            return ['green_points' => 0, 'voucher' => 0, 'digital_rights' => 0];
        }
        $row = Db::name('user')
            ->where('user_id', '=', $userId)
            ->field('green_points,voucher,digital_rights')
            ->find();
        return [
            'green_points'   => round((float)($row['green_points'] ?? 0), 2),
            'voucher'        => round((float)($row['voucher'] ?? 0), 2),
            'digital_rights' => round((float)($row['digital_rights'] ?? 0), 2),
        ];
    }

    /**
     * 计算订单应赠送绿色积分（支付登记待结算 / 收货释放）
     */
    public static function calcOrderGreenPointsAmount(array $order): float
    {
        $service = new static();
        $bonus   = (float)($order['green_points_bonus'] ?? 0);
        if ($bonus > 0) {
            return $service->applyRefundDeduction($order, $bonus);
        }
        $products = $order['product'] ?? [];
        if (empty($products)) {
            $orderId = (int)($order['order_id'] ?? 0);
            if ($orderId > 0 && self::hasTable('order_product')) {
                $products = Db::name('order_product')->where('order_id', '=', $orderId)->select()->toArray();
                foreach ($products as &$line) {
                    if ((float)($line['green_points_bonus'] ?? 0) <= 0 && self::hasColumn('product', 'green_points')) {
                        $per = (float)Db::name('product')
                            ->where('product_id', '=', (int)($line['product_id'] ?? 0))
                            ->value('green_points');
                        $line['green_points_bonus'] = (float)helper::bcmul(
                            (string)$per,
                            (string)max(1, (int)($line['total_num'] ?? 1)),
                            2
                        );
                    }
                }
                unset($line);
            }
        }
        $sum = 0.0;
        foreach ($products as $product) {
            $per = (float)($product['green_points_bonus'] ?? 0);
            if ($per <= 0) {
                $perUnit = (float)($product['green_points'] ?? 0);
                $per     = $perUnit * (int)($product['total_num'] ?? 1);
            }
            if ($service->isRefundedProduct($product)) {
                continue;
            }
            $sum = helper::bcadd((string)$sum, (string)$per, 2);
        }
        return (float)$sum;
    }

    private function applyRefundDeduction(array $order, float $bonus): float
    {
        foreach ($order['product'] ?? [] as $product) {
            if ($this->isRefundedProduct($product)) {
                $deduct = (float)($product['green_points_bonus'] ?? 0);
                if ($deduct <= 0) {
                    $deduct = (float)($product['green_points'] ?? 0) * (int)($product['total_num'] ?? 1);
                }
                $bonus = (float)helper::bcsub((string)$bonus, (string)$deduct, 2);
            }
        }
        return max(0, $bonus);
    }

    private function isRefundedProduct(array $product): bool
    {
        return !empty($product['refund'])
            && ($product['refund']['type']['value'] ?? 0) == 10
            && ($product['refund']['is_agree']['value'] ?? 0) == 10;
    }

    private function changeGreenPoints(int $userId, float $delta, int $scene, string $describe, string $remark, int $orderId = 0): void
    {
        $user = Db::name('user')->where('user_id', '=', $userId)->lock(true)->find();
        if (!$user) {
            throw new \RuntimeException('用户不存在');
        }
        $after = (float)helper::bcadd((string)$user['green_points'], (string)$delta, 2);
        if ($after < 0) {
            throw new \RuntimeException('绿色积分不足');
        }
        Db::name('user')->where('user_id', '=', $userId)->update([
            'green_points' => $after,
            'update_time'  => time(),
        ]);
        GreenPointsLogModel::add([
            'user_id'  => $userId,
            'scene'    => $scene,
            'value'    => $delta,
            'describe' => $describe,
            'remark'   => $remark,
            'order_id' => $orderId,
            'app_id'   => (int)($user['app_id'] ?? 0),
        ]);
    }

    private function changeVoucher(int $userId, float $delta, int $scene, string $describe, string $remark, int $orderId = 0): void
    {
        $user = Db::name('user')->where('user_id', '=', $userId)->lock(true)->find();
        if (!$user) {
            throw new \RuntimeException('用户不存在');
        }
        $after = (float)helper::bcadd((string)$user['voucher'], (string)$delta, 2);
        if ($after < 0) {
            throw new \RuntimeException('积分余额不足');
        }
        Db::name('user')->where('user_id', '=', $userId)->update([
            'voucher'     => $after,
            'update_time' => time(),
        ]);
        VoucherLogModel::add([
            'user_id'  => $userId,
            'scene'    => $scene,
            'value'    => $delta,
            'describe' => $describe,
            'remark'   => $remark,
            'order_id' => $orderId,
            'app_id'   => (int)($user['app_id'] ?? 0),
        ]);
    }

    private function changeDigitalRights(int $userId, float $delta, int $scene, string $describe, string $remark): void
    {
        $user = Db::name('user')->where('user_id', '=', $userId)->lock(true)->find();
        if (!$user) {
            throw new \RuntimeException('用户不存在');
        }
        $after = (float)helper::bcadd((string)$user['digital_rights'], (string)$delta, 2);
        if ($after < 0) {
            throw new \RuntimeException('数权不足');
        }
        Db::name('user')->where('user_id', '=', $userId)->update([
            'digital_rights' => $after,
            'update_time'    => time(),
        ]);
        DigitalRightsLogModel::add([
            'user_id'  => $userId,
            'scene'    => $scene,
            'value'    => $delta,
            'describe' => $describe,
            'remark'   => $remark,
            'app_id'   => (int)($user['app_id'] ?? 0),
        ]);
    }

    private static function hasColumn(string $table, string $column): bool
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
