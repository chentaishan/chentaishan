<?php

namespace app\common\service\activity;

/**
 * 甲丽华威分区商品价
 * - zone 1 优品区：SKU 标价（调用方跳过 grade 折扣）
 * - zone 2 消费区：省/市/区/会员价
 * - zone 3 代理进货区：仅省/市/区代理价，非代理不可购
 */
class WzNormalZoneProductService
{
    public const ZONE_FIRST       = 1;
    public const ZONE_NORMAL      = 2;
    public const ZONE_AGENT_STOCK = 3;

    public static function isFirstZone(int $zoneType): bool
    {
        return $zoneType === self::ZONE_FIRST;
    }

    /** 优品区不支持售后与评价 */
    public static function allowsAfterSaleAndComment(int $zoneType): bool
    {
        return !self::isFirstZone($zoneType);
    }

    public static function isAgentStockZone(int $zoneType): bool
    {
        return $zoneType === self::ZONE_AGENT_STOCK;
    }

    /** 按商品档位价展示（消费区 / 代理进货区） */
    public static function usesTierPricing(int $zoneType): bool
    {
        return in_array($zoneType, [self::ZONE_NORMAL, self::ZONE_AGENT_STOCK], true);
    }

    /** 甲丽华威三区均不使用原商城 grade_id 会员折扣 */
    public static function skipsMemberGradeDiscount(int $zoneType): bool
    {
        return in_array($zoneType, [self::ZONE_FIRST, self::ZONE_NORMAL, self::ZONE_AGENT_STOCK], true);
    }

    public static function canAccessAgentStockZone(array|object $user): bool
    {
        return WzUserIdentityService::canAccessAgentStockZone($user);
    }

    /**
     * @param array|object $product 商品数组或 Product 模型
     */
    private static function productField($product, string $key, $default = null)
    {
        if (is_array($product)) {
            return $product[$key] ?? $default;
        }
        if (is_object($product) && isset($product[$key])) {
            return $product[$key];
        }
        return $default;
    }

    private static function hasUser($user): bool
    {
        return !empty($user) && (is_array($user) || is_object($user));
    }

    /**
     * 写入商品字段（避免 ThinkPHP Model 嵌套赋值 product_sku[xx] 报错）
     */
    private static function setProductAttribute(array|object &$product, string $key, $value): void
    {
        if (is_object($product) && method_exists($product, 'set')) {
            $product->set($key, $value);
            return;
        }
        $product[$key] = $value;
    }

    /**
     * 结算/购物车 product_sku 统一为数组（getProductSku 可能返回 Sku 模型）
     *
     * @return array|null
     */
    private static function ensureProductSkuArray(array|object &$product): ?array
    {
        $productSku = self::productField($product, 'product_sku');
        if (empty($productSku)) {
            return null;
        }
        if (is_object($productSku) && method_exists($productSku, 'toArray')) {
            $productSku = $productSku->toArray();
        }
        if (!is_array($productSku)) {
            return null;
        }
        self::setProductAttribute($product, 'product_sku', $productSku);
        return $productSku;
    }

    /**
     * 代理进货区购买校验；非进货区返回 null
     */
    public static function validateAgentStockPurchase($user, array|object $product): ?string
    {
        if (!self::isAgentStockZone((int)self::productField($product, 'zone_type', 0))) {
            return null;
        }
        if (!self::hasUser($user)) {
            return '请先登录后再购买代理进货商品';
        }
        if (WzUserIdentityService::hasConflictingStoreAndAgent($user)) {
            return '用户门店与区域代理身份冲突，请联系平台处理';
        }
        if (!self::canAccessAgentStockZone($user)) {
            return '代理进货区仅区域代理或门店可购买';
        }
        $unit = self::resolveBuyerUnitPrice($product, $user);
        if ($unit <= 0) {
            return '当前代理身份未配置进货价，请联系平台';
        }
        return null;
    }

    /**
     * 代理进货区列表/进入校验；非进货区场景勿调用
     */
    public static function validateAgentStockZoneAccess($user): ?string
    {
        if (!self::hasUser($user)) {
            return '请先登录后再查看代理进货区';
        }
        if (WzUserIdentityService::hasConflictingStoreAndAgent($user)) {
            return '用户门店与区域代理身份冲突，请联系平台处理';
        }
        if (!self::canAccessAgentStockZone($user)) {
            return '抱歉，您暂未具备代理进货区的准入资格。';
        }
        return null;
    }

    /**
     * 结算/购物车：覆盖商品单价与 SKU 单价
     */
    public static function applyBuyerPriceToProduct(array|object &$product, array|object $user): void
    {
        $zoneType = (int)self::productField($product, 'zone_type', 0);
        if (!self::usesTierPricing($zoneType)) {
            return;
        }
        $productSku = self::ensureProductSkuArray($product);
        if ($productSku === null) {
            return;
        }
        if ($zoneType === self::ZONE_AGENT_STOCK && !self::canAccessAgentStockZone($user)) {
            return;
        }
        $unit = self::resolveBuyerUnitPrice($product, $user);
        if ($unit <= 0) {
            return;
        }
        $unitStr = number_format($unit, 2, '.', '');
        $productSku['product_price'] = $unitStr;
        self::setProductAttribute($product, 'product_sku', $productSku);
        self::setProductAttribute($product, 'product_price', $unitStr);
        $num = max(1, (int)self::productField($product, 'total_num', 1));
        self::setProductAttribute($product, 'total_price', bcmul($unitStr, (string)$num, 2));
    }

    /**
     * 商品详情/列表展示价
     */
    public static function applyBuyerPriceToProductDetail(array|object &$product, $user): void
    {
        $zoneType = (int)self::productField($product, 'zone_type', 0);
        if (!self::usesTierPricing($zoneType)) {
            return;
        }
        if ($zoneType === self::ZONE_NORMAL) {
            $product['voucher_max_money'] = round((float)($product['voucher_max_money'] ?? 0), 2);
        }
        if ($zoneType === self::ZONE_AGENT_STOCK) {
            self::applyAgentStockDetailFlags($product, $user);
            if (!self::hasUser($user) || !self::canAccessAgentStockZone($user)) {
                return;
            }
        } elseif (!self::hasUser($user)) {
            return;
        }
        $unit = self::resolveBuyerUnitPrice($product, $user);
        if ($unit <= 0) {
            return;
        }
        $unitStr = number_format($unit, 2, '.', '');
        $product['product_price'] = $unitStr;
        $sku = self::productField($product, 'sku');
        if (is_object($sku) && method_exists($sku, 'toArray')) {
            $sku = $sku->toArray();
        }
        if (!empty($sku) && is_array($sku)) {
            foreach ($sku as $index => $skuItem) {
                if (is_array($skuItem)) {
                    $sku[$index]['product_price'] = $unitStr;
                } elseif (is_object($skuItem)) {
                    $skuItem['product_price'] = $unitStr;
                }
            }
            $product['sku'] = $sku;
            $prices = array_column($sku, 'product_price');
            if ($prices) {
                rsort($prices);
                $product['product_max_price'] = $prices[0];
                sort($prices);
                $product['product_min_price'] = $prices[0];
            }
        }
        if ($zoneType === self::ZONE_AGENT_STOCK) {
            $product['agent_stock_can_buy'] = true;
            $product['agent_stock_tip']     = '';
        }
    }

    private static function applyAgentStockDetailFlags(array|object &$product, $user): void
    {
        $product['agent_stock_can_buy'] = false;
        if (!self::hasUser($user)) {
            $product['agent_stock_tip'] = '请登录后查看代理进货价';
            return;
        }
        if (!self::canAccessAgentStockZone($user)) {
            $product['agent_stock_tip'] = '代理进货区仅区域代理或门店可购买';
            return;
        }
        $product['agent_stock_tip'] = '当前身份未配置进货价';
    }

    public static function resolveBuyerUnitPrice(array|object $product, array|object $user): float
    {
        $zoneType = (int)self::productField($product, 'zone_type', 0);
        $productSku = self::productField($product, 'product_sku');
        $skuPrice = 0.0;
        if (is_array($productSku) && isset($productSku['product_price'])) {
            $skuPrice = (float)$productSku['product_price'];
        }
        if ($skuPrice <= 0) {
            $skuPrice = (float)self::productField($product, 'product_price', 0);
        }
        $skuList = self::productField($product, 'sku');
        if ($skuPrice <= 0 && !empty($skuList) && is_array($skuList)) {
            $first = $skuList[0];
            $skuPrice = (float)(is_array($first) ? ($first['product_price'] ?? 0) : ($first['product_price'] ?? 0));
        }
        $original = self::pickTierPrice($product, 'original', $skuPrice);
        $strictTier = $zoneType === self::ZONE_AGENT_STOCK;

        $agentTier = WzUserIdentityService::resolveAgentPriceTier($user);
        if ($agentTier === WzUserIdentityService::PRICE_TIER_PROVINCE) {
            return self::pickTierPrice($product, 'province', $original, $strictTier);
        }
        if ($agentTier === WzUserIdentityService::PRICE_TIER_CITY) {
            return self::pickTierPrice($product, 'city', $original, $strictTier);
        }
        if ($agentTier === WzUserIdentityService::PRICE_TIER_DISTRICT) {
            return self::pickTierPrice($product, 'district', $original, $strictTier);
        }

        if ($zoneType === self::ZONE_AGENT_STOCK) {
            if (WzUserIdentityService::isStore($user)) {
                return self::pickTierPrice($product, 'store', $original, true);
            }
            return 0;
        }
        if (WzUserIdentityService::isMember($user)) {
            return self::pickTierPrice($product, 'member', $original);
        }
        return $original;
    }

    private static function pickTierPrice(array|object $product, string $tier, float $fallback, bool $strictTier = false): float
    {
        $map = [
            'original'  => null,
            'member'    => 'member_price',
            'store'     => 'store_price',
            'province'  => 'province_agent_price',
            'city'      => 'city_agent_price',
            'district'  => 'district_agent_price',
        ];
        if ($tier === 'original' || !isset($map[$tier])) {
            return round($fallback, 2);
        }
        $col = $map[$tier];
        if (!isset($product[$col]) && !(is_object($product) && isset($product->$col))) {
            return $strictTier ? 0.0 : round($fallback, 2);
        }
        $val = (float)self::productField($product, $col, 0);
        if ($val > 0) {
            return round($val, 2);
        }
        return $strictTier ? 0.0 : round($fallback, 2);
    }
}
