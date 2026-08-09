<?php

namespace app\common\service\activity;

/**
 * 甲丽华威商品分区价保存校验（shop / supplier 商品 add|edit）
 */
class WzProductZonePriceValidateService
{
    /**
     * @return string|null 错误文案，通过返回 null
     */
    public static function validate(array $data): ?string
    {
        $zoneType = (int)($data['zone_type'] ?? 0);

        if ($zoneType === WzNormalZoneProductService::ZONE_NORMAL) {
            $tierErr = self::validateFields($data, [
                //'member_price'           => '会员价',
                'province_agent_price'   => '省代价',
                'city_agent_price'       => '市代价',
                'district_agent_price'   => '区代价',
            ]);
            if ($tierErr !== null) {
                return $tierErr;
            }
            return self::validateVoucherMaxMoney($data);
        }

        if ($zoneType === WzNormalZoneProductService::ZONE_AGENT_STOCK) {
            return self::validateFields($data, [
                'province_agent_price'   => '省代进货价',
                'city_agent_price'       => '市代进货价',
                'district_agent_price'   => '区代进货价',
                'store_price'            => '门店价',
            ]);
        }

        return null;
    }

    private static function validateFields(array $data, array $fields): ?string
    {
        foreach ($fields as $key => $label) {
            if (!self::isPositivePrice($data[$key] ?? null)) {
                return $label . '不能为空且须大于0';
            }
        }
        return null;
    }

    private static function isPositivePrice($value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (!is_numeric($value)) {
            return false;
        }
        return bccomp((string)$value, '0', 2) > 0;
    }

    /** 消费区：抵扣券最大可用金额（可选，0=不可用抵扣券） */
    private static function validateVoucherMaxMoney(array $data): ?string
    {
        if (!array_key_exists('voucher_max_money', $data)) {
            return null;
        }
        $value = $data['voucher_max_money'];
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return '抵扣券最大可用金额格式不正确';
        }
        if (bccomp((string)$value, '0', 2) < 0) {
            return '抵扣券最大可用金额不能小于0';
        }
        return null;
    }
}
