<?php

namespace app\common\service\activity;

use think\facade\Db;

/**
 * 代理进货区(zone_type=3)级差代理奖：
 * - 支付成功：登记待结算（activity_reward_log status=0）
 * - 确认收货：释放入账（release_trigger=receipt）
 * - 级差规则：按门店/区代/市代/省代价格阶梯，逐级计算差额，小于等于0不发
 */
class AgentStockRegionRewardService
{
    private const TRIGGER_RECEIPT = 'receipt';

    /** @var ActivityRewardService */
    private $rewardService;

    public function __construct(?ActivityRewardService $rewardService = null)
    {
        $this->rewardService = $rewardService ?: new ActivityRewardService();
    }

    public function onPaySuccess(array $order): void
    {
        if (!$this->isAgentStockEnabled($order)) {
            return;
        }
        $this->registerPendingRewards($order, 'pay');
    }

    public function onOrderCompleted(array $order): void
    {
        if (!$this->isAgentStockEnabled($order)) {
            return;
        }
        // 幂等补登记；实际释放由 ActivityRewardService 统一处理
        $this->registerPendingRewards($order, 'receipt');
    }

    private function isAgentStockEnabled(array $order): bool
    {
        if ((int)($order['zone_type'] ?? 0) !== ActivityRewardService::ZONE_AGENT_STOCK) {
            return false;
        }
        if (!$this->rewardService->supportsSchema()) {
            return false;
        }
        return (bool)config('wz_reward.agent_stock_region.enabled', true);
    }

    private function registerPendingRewards(array $order, string $phase): void
    {
        $orderId = (int)($order['order_id'] ?? 0);
        $items   = $this->buildPendingItems($order);
        if (empty($items)) {
            $this->mlog("[代理进货区级差奖|{$phase}] order_id={$orderId} 无待登记奖励");
            return;
        }
        foreach ($items as $item) {
            $this->rewardService->createPendingRewardLogPublic(array_merge($item, [
                'zone_type'       => ActivityRewardService::ZONE_AGENT_STOCK,
                'asset_type'      => 'balance',
                'reward_month'    => date('Ym'),
                'release_trigger' => self::TRIGGER_RECEIPT,
            ]));
            $this->mlog("[代理进货区级差奖|{$phase}] order_id={$orderId} user_id={$item['user_id']} scene={$item['scene']} amount={$item['amount']}");
        }
    }

    /**
     * @return list<array{unique_key:string,user_id:int,from_user_id:int,order_id:int,scene:string,amount:float,remark:string,app_id:int}>
     */
    private function buildPendingItems(array $order): array
    {
        $orderId    = (int)($order['order_id'] ?? 0);
        $buyerId    = (int)($order['user_id'] ?? 0);
        $appId      = (int)($order['app_id'] ?? 0);
        $buyer      = Db::name('user')->where('user_id', '=', $buyerId)->find();
        if (!$buyer || $orderId <= 0) {
            return [];
        }
        if (!$this->hasTable('order_product') || !$this->hasTable('product')) {
            return [];
        }

        $upline = $this->resolveUplineAgentUsers($buyer);
        if (!$upline['district'] && !$upline['city'] && !$upline['province']) {
            return [];
        }

        $buyerTier = $this->resolveBuyerTier($buyer);
        if ($buyerTier === null) {
            return [];
        }

        $totals = [
            'district' => '0.00',
            'city'     => '0.00',
            'province' => '0.00',
        ];
        $lines = Db::name('order_product')->where('order_id', '=', $orderId)->select()->toArray();
        foreach ($lines as $line) {
            $lineReward = $this->calcLineRewards($line, $buyerTier, $upline);
            $totals['district'] = bcadd($totals['district'], $lineReward['district'], 2);
            $totals['city'] = bcadd($totals['city'], $lineReward['city'], 2);
            $totals['province'] = bcadd($totals['province'], $lineReward['province'], 2);
        }

        $items = [];
        foreach (['district', 'city', 'province'] as $level) {
            $agent = $upline[$level];
            if (!$agent) {
                continue;
            }
            $amount = $totals[$level];
            if (bccomp($amount, '0', 2) <= 0) {
                continue;
            }
            $items[] = [
                'unique_key'   => 'wz_agent_stock_region_' . $level . '_' . $orderId . '_' . (int)$agent['user_id'],
                'user_id'      => (int)$agent['user_id'],
                'from_user_id' => $buyerId,
                'order_id'     => $orderId,
                'scene'        => 'region_' . $level,
                'amount'       => (float)$amount,
                'remark'       => WzRewardRemarkHelper::regionRewardRemark(ActivityRewardService::ZONE_AGENT_STOCK, $level),
                'app_id'       => $appId,
            ];
        }
        return $items;
    }

    /**
     * 单商品行级差奖励（按件累计）
     *
     * @param array{district:?array,city:?array,province:?array} $upline
     * @return array{district:string,city:string,province:string}
     */
    private function calcLineRewards(array $line, string $buyerTier, array $upline): array
    {
        $ret = ['district' => '0.00', 'city' => '0.00', 'province' => '0.00'];
        $productId = (int)($line['product_id'] ?? 0);
        if ($productId <= 0) {
            return $ret;
        }
        $product = Db::name('product')->where('product_id', '=', $productId)->find();
        if (!$product) {
            return $ret;
        }
        $num = max(1, (int)($line['total_num'] ?? 1));

        $priceStore    = $this->money($product['store_price'] ?? 0);
        $priceDistrict = $this->money($product['district_agent_price'] ?? 0);
        $priceCity     = $this->money($product['city_agent_price'] ?? 0);
        $priceProvince = $this->money($product['province_agent_price'] ?? 0);

        $current = $this->resolveTierPrice($buyerTier, $priceStore, $priceDistrict, $priceCity, $priceProvince);
        if (bccomp($current, '0', 2) <= 0) {
            return $ret;
        }

        if ($upline['district']) {
            $gap = $this->positiveDiff($current, $priceDistrict);
            if (bccomp($gap, '0', 2) > 0) {
                $ret['district'] = bcmul($gap, (string)$num, 2);
                // 仅当区代实际可发（>0）时，才把级差基准推进到区代价
                $current = $priceDistrict;
            }
        }
        if ($upline['city']) {
            $gap = $this->positiveDiff($current, $priceCity);
            if (bccomp($gap, '0', 2) > 0) {
                $ret['city'] = bcmul($gap, (string)$num, 2);
                // 仅当市代实际可发（>0）时，才把级差基准推进到市代价
                $current = $priceCity;
            }
        }
        if ($upline['province']) {
            $gap = $this->positiveDiff($current, $priceProvince);
            if (bccomp($gap, '0', 2) > 0) {
                $ret['province'] = bcmul($gap, (string)$num, 2);
            }
        }
        return $ret;
    }

    /**
     * 找买家推荐链上的最近 区/市/省 代理（紧缩）
     *
     * @return array{district:?array,city:?array,province:?array}
     */
    private function resolveUplineAgentUsers(array $buyer): array
    {
        $result = ['district' => null, 'city' => null, 'province' => null];
        $currentId = (int)($buyer['referee_id'] ?? 0);
        $visited = [];
        $maxDepth = 200;
        while ($currentId > 0 && !isset($visited[$currentId]) && $maxDepth-- > 0) {
            $visited[$currentId] = true;
            $user = Db::name('user')->where('user_id', '=', $currentId)->find();
            if (!$user) {
                break;
            }
            $tier = WzUserIdentityService::resolveAgentPriceTier($user);
            if ($tier === WzUserIdentityService::PRICE_TIER_DISTRICT && !$result['district']) {
                $result['district'] = $user;
            } elseif ($tier === WzUserIdentityService::PRICE_TIER_CITY && !$result['city']) {
                $result['city'] = $user;
            } elseif ($tier === WzUserIdentityService::PRICE_TIER_PROVINCE && !$result['province']) {
                $result['province'] = $user;
            }
            if ($result['district'] && $result['city'] && $result['province']) {
                break;
            }
            $currentId = (int)($user['referee_id'] ?? 0);
        }
        return $result;
    }

    private function resolveBuyerTier(array $buyer): ?string
    {
        $agentTier = WzUserIdentityService::resolveAgentPriceTier($buyer);
        if ($agentTier !== null) {
            return $agentTier;
        }
        if (WzUserIdentityService::isStore($buyer)) {
            return 'store';
        }
        return null;
    }

    private function resolveTierPrice(string $tier, string $store, string $district, string $city, string $province): string
    {
        if ($tier === 'store') {
            return $store;
        }
        if ($tier === WzUserIdentityService::PRICE_TIER_DISTRICT) {
            return $district;
        }
        if ($tier === WzUserIdentityService::PRICE_TIER_CITY) {
            return $city;
        }
        if ($tier === WzUserIdentityService::PRICE_TIER_PROVINCE) {
            return $province;
        }
        return '0.00';
    }

    private function positiveDiff(string $high, string $low): string
    {
        $diff = bcsub($high, $low, 2);
        return bccomp($diff, '0', 2) > 0 ? $diff : '0.00';
    }

    private function money($val): string
    {
        return number_format((float)$val, 2, '.', '');
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

    private function mlog(string $message): void
    {
        $file = app()->getRootPath() . 'wz_zone_reward_debug.log';
        @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
