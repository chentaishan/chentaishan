<?php

namespace app\common\service\activity;

use think\facade\Db;

/**
 * 消费区：按收货地址 + 商品省/市/区代理奖励金额（×数量）
 * - 支付成功：登记待结算（activity_reward_log status=0）
 * - 确认收货：释放入账（与直推奖同一 release_trigger=receipt）
 */
class NormalZoneRegionRewardService
{
    const TRIGGER_RECEIPT = 'receipt';

    private ActivityRewardService $rewardService;

    public function __construct(?ActivityRewardService $rewardService = null)
    {
        $this->rewardService = $rewardService ?: new ActivityRewardService();
    }

    public function onPaySuccess(array $order): void
    {
        if (!$this->isNormalZoneEnabled($order)) {
            return;
        }
        $this->registerRegionAgentPendingRewards($order, 'pay');
    }

    public function onOrderCompleted(array $order): void
    {
        if (!$this->isNormalZoneEnabled($order)) {
            return;
        }
        // 幂等补登记，实际发放入账由 WzZoneRewardService::releaseOrderRewardsByTrigger 统一释放
        $this->registerRegionAgentPendingRewards($order, 'receipt');
    }

    private function isNormalZoneEnabled(array $order): bool
    {
        if ((int)($order['zone_type'] ?? 0) !== WzNormalZoneProductService::ZONE_NORMAL) {
            return false;
        }
        if (!$this->rewardService->supportsSchema()) {
            return false;
        }
        if (!config('wz_reward.normal_zone_region.enabled', true)) {
            return false;
        }
        return true;
    }

    /**
     * 登记省/市/区代待结算奖励
     */
    private function registerRegionAgentPendingRewards(array $order, string $phase): void
    {
        $orderId = (int)$order['order_id'];
        $items   = $this->buildRegionRewardItems($order);
        if (empty($items)) {
            $this->mlog("[惠民区域奖|{$phase}] order_id={$orderId} 无待登记代理奖");
            return;
        }
        foreach ($items as $item) {
            $this->rewardService->createPendingRewardLogPublic(array_merge($item, [
                'zone_type'       => WzNormalZoneProductService::ZONE_NORMAL,
                'asset_type'      => 'balance',
                'reward_month'    => date('Ym'),
                'release_trigger' => self::TRIGGER_RECEIPT,
            ]));
            $this->mlog("[惠民区域奖|{$phase}] order_id={$orderId} user_id={$item['user_id']} scene={$item['scene']} amount={$item['amount']}");
        }
    }

    /**
     * @return list<array{unique_key:string,user_id:int,from_user_id:int,order_id:int,scene:string,amount:float,remark:string,app_id:int}>
     */
    private function buildRegionRewardItems(array $order): array
    {
        $orderId    = (int)$order['order_id'];
        $fromUserId = (int)$order['user_id'];
        $appId      = (int)($order['app_id'] ?? 0);

        if (!$this->hasTable('order_address') || !$this->hasTable('order_product')) {
            $this->mlog("[惠民区域奖] order_id={$orderId} 缺少 order_address/order_product");
            return [];
        }

        $address = Db::name('order_address')->where('order_id', '=', $orderId)->find();
        if (!$address) {
            $this->mlog("[惠民区域奖] order_id={$orderId} 无收货地址");
            return [];
        }

        $provinceId = (int)($address['province_id'] ?? 0);
        $cityId     = (int)($address['city_id'] ?? 0);
        $regionId   = (int)($address['region_id'] ?? 0);

        $totals = $this->sumRegionRewardsFromOrderLines($orderId);
        $this->mlog("[惠民区域奖] order_id={$orderId} 区={$totals['district']} 市={$totals['city']} 省={$totals['province']} addr={$provinceId}/{$cityId}/{$regionId}");

        $items = [];

        if ($regionId > 0 && bccomp($totals['district'], '0', 2) > 0) {
            $agents = $this->findDistrictAgents($provinceId, $cityId, $regionId, $orderId);
            $items  = array_merge($items, $this->splitAgentPendingItems(
                $agents,
                $totals['district'],
                'region_district',
                WzRewardRemarkHelper::regionRewardRemark(WzNormalZoneProductService::ZONE_NORMAL, 'district'),
                $orderId,
                $fromUserId,
                $appId
            ));
        }

        if ($regionId <= 0 && bccomp($totals['district'], '0', 2) > 0) {
            $this->mlog("[惠民区域奖] order_id={$orderId} 区代奖金额>0 但收货地址 region_id=0，无法匹配区代（请检查下单是否选了区县）");
        }

        if ($cityId > 0 && bccomp($totals['city'], '0', 2) > 0) {
            $agents = $this->findCityAgents($provinceId, $cityId, $orderId);
            $items  = array_merge($items, $this->splitAgentPendingItems(
                $agents,
                $totals['city'],
                'region_city',
                WzRewardRemarkHelper::regionRewardRemark(WzNormalZoneProductService::ZONE_NORMAL, 'city'),
                $orderId,
                $fromUserId,
                $appId
            ));
        }

        if ($provinceId > 0 && bccomp($totals['province'], '0', 2) > 0) {
            $agents = $this->findProvinceAgents($provinceId, $orderId);
            $items  = array_merge($items, $this->splitAgentPendingItems(
                $agents,
                $totals['province'],
                'region_province',
                WzRewardRemarkHelper::regionRewardRemark(WzNormalZoneProductService::ZONE_NORMAL, 'province'),
                $orderId,
                $fromUserId,
                $appId
            ));
        }

        return $items;
    }

    /**
     * 区代：收货地址 region_id 匹配 agent_district_id；省/市 ID 已配置时需与地址一致
     */
    private function findDistrictAgents(int $provinceId, int $cityId, int $regionId, int $orderId = 0): array
    {
        if ($regionId <= 0) {
            return [];
        }
        $list = Db::name('user')
            ->where('agent_district_id', '=', $regionId)
            ->where('is_delete', '=', 0)
            ->select()
            ->toArray();

        $matched = array_values(array_filter($list, function ($user) use ($provinceId, $cityId) {
            return $this->agentRegionMatchesAddress($user, $provinceId, $cityId);
        }));
        if (empty($matched) && $orderId > 0) {
            $this->mlog("[惠民区域奖|区代] order_id={$orderId} addr={$provinceId}/{$cityId}/{$regionId} 候选=" . count($list)
                . " 匹配=0（检查 user.agent_district_id 是否=地址 region_id，且 agent_province_id/agent_city_id 与地址一致）");
        }
        return $matched;
    }

    /**
     * 市代：agent_city_id 匹配且未配置区代身份
     */
    private function findCityAgents(int $provinceId, int $cityId, int $orderId = 0): array
    {
        if ($cityId <= 0) {
            return [];
        }
        $list = Db::name('user')
            ->where('agent_city_id', '=', $cityId)
            ->where('agent_district_id', '=', 0)
            ->where('is_delete', '=', 0)
            ->select()
            ->toArray();

        $matched = array_values(array_filter($list, function ($user) use ($provinceId, $cityId) {
            return $this->agentRegionMatchesAddress($user, $provinceId, $cityId);
        }));
        if (empty($matched) && $orderId > 0) {
            $this->mlog("[惠民区域奖|市代] order_id={$orderId} addr={$provinceId}/{$cityId} 候选=" . count($list)
                . " 匹配=0（需 agent_city_id=地址 city_id 且 agent_district_id=0）");
        }
        return $matched;
    }

    /**
     * 省代：agent_province_id 匹配且未配置市/区代身份
     */
    private function findProvinceAgents(int $provinceId, int $orderId = 0): array
    {
        if ($provinceId <= 0) {
            return [];
        }
        $list = Db::name('user')
            ->where('agent_province_id', '=', $provinceId)
            ->where('agent_city_id', '=', 0)
            ->where('agent_district_id', '=', 0)
            ->where('is_delete', '=', 0)
            ->select()
            ->toArray();
        if (empty($list) && $orderId > 0) {
            $this->mlog("[惠民区域奖|省代] order_id={$orderId} province_id={$provinceId} 无省代账号");
        }
        return $list;
    }

    /** 代理账号已填省/市 ID 时须与收货地址一致；仅填区/县 ID 时按区/县匹配 */
    private function agentRegionMatchesAddress(array $user, int $provinceId, int $cityId): bool
    {
        $userProvince = (int)($user['agent_province_id'] ?? 0);
        $userCity     = (int)($user['agent_city_id'] ?? 0);
        if ($userProvince > 0 && $provinceId > 0 && $userProvince !== $provinceId) {
            return false;
        }
        if ($userCity > 0 && $cityId > 0 && $userCity !== $cityId) {
            return false;
        }
        return true;
    }

    /**
     * @return list<array>
     */
    private function splitAgentPendingItems(
        array $agents,
        string $poolAmount,
        string $scene,
        string $remark,
        int $orderId,
        int $fromUserId,
        int $appId
    ): array {
        if (empty($agents) || bccomp($poolAmount, '0', 2) <= 0) {
            return [];
        }
        $count = count($agents);
        $each  = $count === 1
            ? $poolAmount
            : bcdiv($poolAmount, (string)$count, 2);
        $items = [];
        foreach ($agents as $agent) {
            $agentUserId = (int)$agent['user_id'];
            if ($agentUserId <= 0 || bccomp($each, '0', 2) <= 0) {
                continue;
            }
            $items[] = [
                'unique_key'   => 'wz_normal_' . $scene . '_' . $orderId . '_' . $agentUserId,
                'user_id'      => $agentUserId,
                'from_user_id' => $fromUserId,
                'order_id'     => $orderId,
                'scene'        => $scene,
                'amount'       => (float)$each,
                'remark'       => $remark,
                'app_id'       => $appId,
            ];
        }
        return $items;
    }

    /**
     * @return array{district:string,city:string,province:string}
     */
    private function sumRegionRewardsFromOrderLines(int $orderId): array
    {
        $sum = ['district' => '0.00', 'city' => '0.00', 'province' => '0.00'];
        if (!$this->hasProductRewardColumns()) {
            return $sum;
        }

        $lines = Db::name('order_product')->where('order_id', '=', $orderId)->select()->toArray();
        foreach ($lines as $line) {
            $productId = (int)($line['product_id'] ?? 0);
            $num       = max(1, (int)($line['total_num'] ?? 1));
            if ($productId <= 0) {
                continue;
            }
            $row = Db::name('product')->where('product_id', '=', $productId)->find();
            if (!$row) {
                continue;
            }
            foreach (['district' => 'district_agent_reward', 'city' => 'city_agent_reward', 'province' => 'province_agent_reward'] as $key => $col) {
                if (!$this->rewardService->hasColumn('product', $col)) {
                    continue;
                }
                $per = isset($row[$col]) ? number_format((float)$row[$col], 2, '.', '') : '0.00';
                if (bccomp($per, '0', 2) <= 0) {
                    continue;
                }
                $sum[$key] = bcadd($sum[$key], bcmul($per, (string)$num, 2), 2);
            }
        }
        return $sum;
    }

    private function hasProductRewardColumns(): bool
    {
        return $this->rewardService->hasColumn('product', 'district_agent_reward');
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
