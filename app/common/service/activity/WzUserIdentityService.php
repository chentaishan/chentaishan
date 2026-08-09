<?php

namespace app\common\service\activity;

/**
 * 甲丽华威商城用户身份：job_grade 仅 0游客/1会员；区域代理与门店互斥（不可同时拥有）
 */
class WzUserIdentityService
{
    public const JOB_GRADE_GUEST  = 0;
    public const JOB_GRADE_MEMBER = 1;

    /** 买家取价档位 */
    public const PRICE_TIER_DISTRICT = 'district';
    public const PRICE_TIER_CITY     = 'city';
    public const PRICE_TIER_PROVINCE = 'province';

    /**
     * @param array|object $user 用户数组或 ThinkPHP 模型（支持 ArrayAccess）
     */
    private static function userArray($user): array
    {
        if (is_array($user)) {
            return $user;
        }
        if (is_object($user) && method_exists($user, 'toArray')) {
            return $user->toArray();
        }
        return (array)$user;
    }

    /**
     * 规范 job_grade：历史 2–7 视为会员(1)
     */
    public static function normalizeJobGrade(int $jobGrade): int
    {
        return $jobGrade >= self::JOB_GRADE_MEMBER ? self::JOB_GRADE_MEMBER : self::JOB_GRADE_GUEST;
    }

    public static function isMember(array|object $user): bool
    {
        $user = self::userArray($user);
        return self::normalizeJobGrade((int)($user['job_grade'] ?? 0)) === self::JOB_GRADE_MEMBER;
    }

    public static function getJobGradeText(int $jobGrade): string
    {
        return self::normalizeJobGrade($jobGrade) === self::JOB_GRADE_MEMBER ? '会员' : '游客';
    }

    /** 消费身份展示（合伙人时拼接「合伙人」） */
    public static function getJobGradeDisplayText(array|object $user): string
    {
        $user = self::userArray($user);
        $parts = [self::getJobGradeText((int)($user['job_grade'] ?? 0))];
        if (self::isPartner($user)) {
            $parts[] = self::getPartnerText($user);
        }
        return implode('·', $parts);
    }

    /**
     * 从 agent_*_id 推导代理级别（与 setAgentRegion、区域奖匹配规则一致）
     */
    public static function resolveAgentPriceTier(array|object $user): ?string
    {
        $user = self::userArray($user);
        $provinceId = (int)($user['agent_province_id'] ?? 0);
        $cityId     = (int)($user['agent_city_id'] ?? 0);
        $districtId = (int)($user['agent_district_id'] ?? 0);

        if ($districtId > 0) {
            return self::PRICE_TIER_DISTRICT;
        }
        if ($cityId > 0) {
            return self::PRICE_TIER_CITY;
        }
        if ($provinceId > 0) {
            return self::PRICE_TIER_PROVINCE;
        }
        return null;
    }

    public static function hasAgentRegion(array|object $user): bool
    {
        return self::resolveAgentPriceTier($user) !== null;
    }

    public static function isStore(array|object $user): bool
    {
        $user = self::userArray($user);
        return (int)($user['is_store'] ?? 0) === 1;
    }

    /** 历史脏数据：同时为门店且设了代理区域 */
    public static function hasConflictingStoreAndAgent(array|object $user): bool
    {
        return self::isStore($user) && self::hasAgentRegion($user);
    }

    public static function getStoreText(array|object $user): string
    {
        return self::isStore($user) ? '门店' : '';
    }

    public static function isPartner(array|object $user): bool
    {
        $user = self::userArray($user);
        return (int)($user['is_partner'] ?? 0) === 1;
    }

    public static function getPartnerText(array|object $user): string
    {
        return self::isPartner($user) ? '合伙人' : '';
    }

    public static function getAgentLevelText(array|object $user): string
    {
        $user = self::userArray($user);
        $map = [
            self::PRICE_TIER_PROVINCE => '省代',
            self::PRICE_TIER_CITY     => '市代',
            self::PRICE_TIER_DISTRICT => '区代',
        ];
        $tier = self::resolveAgentPriceTier($user);
        return $tier ? ($map[$tier] ?? '') : '';
    }

    /** 是否可进入代理进货区（区域代理与门店二选一） */
    public static function canAccessAgentStockZone(array|object $user): bool
    {
        if (self::hasConflictingStoreAndAgent($user)) {
            return false;
        }
        return self::hasAgentRegion($user) || self::isStore($user);
    }

    /** 消费身份 + 代理或门店（互斥，不同时展示两种） */
    public static function getIdentityDisplayText(array|object $user): string
    {
        $user = self::userArray($user);
        $parts = [self::getJobGradeText((int)($user['job_grade'] ?? 0))];
        if (self::hasConflictingStoreAndAgent($user)) {
            $parts[] = '';
            return implode('·', $parts);
        }
        $agent = self::getAgentLevelText($user);
        if ($agent !== '') {
            $parts[] = $agent;
        } elseif (self::isStore($user)) {
            $parts[] = self::getStoreText($user);
        }
        return implode('·', $parts);
    }
}
