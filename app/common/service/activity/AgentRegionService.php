<?php

namespace app\common\service\activity;

use app\shop\model\user\User as UserModel;
use think\facade\Db;

/**
 * 省/市/区代区域：H5 申请审核与 Shop 直接设置共用校验逻辑
 */
class AgentRegionService
{
    public const TYPE_PROVINCE = 1;
    public const TYPE_CITY     = 2;
    public const TYPE_DISTRICT = 3;

    public const STATUS_PENDING  = 10;
    public const STATUS_APPROVED = 20;
    public const STATUS_REJECTED = 30;

    private const TYPE_TEXT = [
        self::TYPE_PROVINCE => '省代',
        self::TYPE_CITY     => '市代',
        self::TYPE_DISTRICT => '区代',
    ];

    private const STATUS_TEXT = [
        self::STATUS_PENDING  => '审核中',
        self::STATUS_APPROVED => '已通过',
        self::STATUS_REJECTED => '已拒绝',
    ];

    /**
     * 根据区域 ID 推导代理类型
     */
    public static function resolveAgentType(int $provinceId, int $cityId, int $districtId): int
    {
        if ($districtId > 0) {
            return self::TYPE_DISTRICT;
        }
        if ($cityId > 0) {
            return self::TYPE_CITY;
        }
        if ($provinceId > 0) {
            return self::TYPE_PROVINCE;
        }
        return 0;
    }

    public static function getAgentTypeText(int $agentType): string
    {
        return self::TYPE_TEXT[$agentType] ?? '';
    }

    public static function getStatusText(int $status): string
    {
        return self::STATUS_TEXT[$status] ?? '未知';
    }

    /**
     * 校验区域 ID 合法性与层级关系
     *
     * @return array{agent_type:int,agent_province_id:int,agent_city_id:int,agent_district_id:int}
     */
    public static function validateRegionIds(int $provinceId, int $cityId, int $districtId): array
    {
        if ($districtId > 0 && $cityId <= 0) {
            throw new \InvalidArgumentException('设置区/县代理时必须同时指定所属地级市');
        }
        if ($cityId > 0 && $provinceId <= 0) {
            throw new \InvalidArgumentException('请选择省份');
        }
        if ($provinceId <= 0 && $cityId <= 0 && $districtId <= 0) {
            throw new \InvalidArgumentException('请选择代理区域');
        }

        $agentType = self::resolveAgentType($provinceId, $cityId, $districtId);
        if ($agentType === self::TYPE_PROVINCE) {
            $province = Db::name('region')->where('id', '=', $provinceId)->where('level', '=', 1)->find();
            if (!$province) {
                throw new \InvalidArgumentException('省份不存在');
            }
            return [
                'agent_type'         => $agentType,
                'agent_province_id'  => $provinceId,
                'agent_city_id'      => 0,
                'agent_district_id'  => 0,
            ];
        }

        if ($agentType === self::TYPE_CITY) {
            $province = Db::name('region')->where('id', '=', $provinceId)->where('level', '=', 1)->find();
            if (!$province) {
                throw new \InvalidArgumentException('省份不存在');
            }
            $city = Db::name('region')->where('id', '=', $cityId)->where('level', '=', 2)->find();
            if (!$city) {
                throw new \InvalidArgumentException('城市不存在');
            }
            if ((int)($city['pid'] ?? 0) !== $provinceId) {
                throw new \InvalidArgumentException('所选省市不匹配');
            }
            return [
                'agent_type'         => $agentType,
                'agent_province_id'  => $provinceId,
                'agent_city_id'      => $cityId,
                'agent_district_id'  => 0,
            ];
        }

        $province = Db::name('region')->where('id', '=', $provinceId)->where('level', '=', 1)->find();
        if (!$province) {
            throw new \InvalidArgumentException('省份不存在');
        }
        $city = Db::name('region')->where('id', '=', $cityId)->where('level', '=', 2)->find();
        if (!$city) {
            throw new \InvalidArgumentException('城市不存在');
        }
        if ((int)($city['pid'] ?? 0) !== $provinceId) {
            throw new \InvalidArgumentException('所选省市不匹配');
        }
        $district = Db::name('region')->where('id', '=', $districtId)->where('level', '=', 3)->find();
        if (!$district) {
            throw new \InvalidArgumentException('区县不存在');
        }
        if ((int)($district['pid'] ?? 0) !== $cityId) {
            throw new \InvalidArgumentException('所选市区不匹配');
        }

        return [
            'agent_type'         => self::TYPE_DISTRICT,
            'agent_province_id'  => $provinceId,
            'agent_city_id'      => $cityId,
            'agent_district_id'  => $districtId,
        ];
    }

    /**
     * H5 申请前：校验用户是否可提交
     *
     * @param array|object $user 用户数组或 ThinkPHP 模型（支持 ArrayAccess）
     */
    public static function assertUserCanApply(array|object $user): void
    {
        if ((int)($user['is_store'] ?? 0) === 1) {
            throw new \InvalidArgumentException('您已是门店身份，无法申请区域代理');
        }
        if (WzUserIdentityService::hasAgentRegion($user)) {
            throw new \InvalidArgumentException('您已是区域代理，无需重复申请');
        }
    }

    /**
     * Shop 设置前：校验目标用户是否可设为代理
     *
     * @param array|object $user 用户数组或 ThinkPHP 模型（支持 ArrayAccess）
     */
    public static function assertUserCanSetAgent(array|object $user): void
    {
        if (UserModel::hasColumn('user', 'is_store') && (int)($user['is_store'] ?? 0) === 1) {
            throw new \InvalidArgumentException('该用户已是门店，请先取消门店身份后再设置区域代理');
        }
    }

    /**
     * 检查区域是否已被占用（已生效代理 + 待审核申请）
     *
     * @throws \InvalidArgumentException
     */
    public static function assertRegionAvailable(array $region, int $excludeUserId = 0): void
    {
        $msg = self::getRegionOccupiedMessage($region, $excludeUserId);
        if ($msg !== '') {
            throw new \InvalidArgumentException($msg);
        }
    }

    /**
     * @return string 空字符串表示可用，否则为错误提示
     */
    public static function getRegionOccupiedMessage(array $region, int $excludeUserId = 0): string
    {
        $agentType   = (int)$region['agent_type'];
        $provinceId  = (int)$region['agent_province_id'];
        $cityId      = (int)$region['agent_city_id'];
        $districtId  = (int)$region['agent_district_id'];

        if ($agentType === self::TYPE_PROVINCE) {
            if (self::existsProvinceAgent($provinceId, $excludeUserId)) {
                return '该省已存在省代理';
            }
            if (self::existsPendingApply(self::TYPE_PROVINCE, $provinceId, 0, 0, $excludeUserId)) {
                return '该省已有待审核的省代申请';
            }
            return '';
        }

        if ($agentType === self::TYPE_CITY) {
            if (self::existsCityAgent($cityId, $excludeUserId)) {
                return '该城市已存在市代理';
            }
            if (self::existsPendingApply(self::TYPE_CITY, $provinceId, $cityId, 0, $excludeUserId)) {
                return '该城市已有待审核的市代申请';
            }
            return '';
        }

        if (self::existsDistrictAgent($districtId, $excludeUserId)) {
            return '该区县已存在区代理';
        }
        if (self::existsPendingApply(self::TYPE_DISTRICT, $provinceId, $cityId, $districtId, $excludeUserId)) {
            return '该区县已有待审核的区代申请';
        }
        return '';
    }

    /**
     * 用户是否存在待审核申请
     */
    public static function hasUserPendingApply(int $userId): bool
    {
        return (bool)Db::name('city_agent_apply')
            ->where('user_id', '=', $userId)
            ->where('status', '=', self::STATUS_PENDING)
            ->where('is_delete', '=', 0)
            ->find();
    }

    /**
     * 审核通过时写入 user 表的代理字段
     */
    public static function buildUserAgentUpdate(array $region): array
    {
        $agentType = (int)($region['agent_type'] ?? self::resolveAgentType(
            (int)$region['agent_province_id'],
            (int)$region['agent_city_id'],
            (int)$region['agent_district_id']
        ));

        $update = [
            'agent_province_id' => (int)$region['agent_province_id'],
            'agent_city_id'     => (int)$region['agent_city_id'],
            'agent_district_id' => (int)$region['agent_district_id'],
            'update_time'       => time(),
        ];
        if (UserModel::hasColumn('user', 'is_store')) {
            $update['is_store'] = 0;
        }
        // 设为市代时自动成为合伙人
        if ($agentType === self::TYPE_CITY && UserModel::hasColumn('user', 'is_partner')) {
            $update['is_partner'] = 1;
        }
        return $update;
    }

    /**
     * 格式化申请记录（含区域名称）
     */
    public static function formatApplyRow(array $row): array
    {
        $agentType = (int)($row['agent_type'] ?? self::TYPE_CITY);
        if ($agentType <= 0) {
            $agentType = self::resolveAgentType(
                (int)($row['agent_province_id'] ?? 0),
                (int)($row['agent_city_id'] ?? 0),
                (int)($row['agent_district_id'] ?? 0)
            ) ?: self::TYPE_CITY;
        }

        $row['agent_type']      = $agentType;
        $row['agent_type_text'] = self::getAgentTypeText($agentType);
        $row['status_text']     = self::getStatusText((int)($row['status'] ?? 0));
        $row['agent_province_name'] = self::getRegionName((int)($row['agent_province_id'] ?? 0));
        $row['agent_city_name']     = self::getRegionName((int)($row['agent_city_id'] ?? 0));
        $row['agent_district_name'] = self::getRegionName((int)($row['agent_district_id'] ?? 0));
        return $row;
    }

    public static function getRegionName(int $regionId): string
    {
        if ($regionId <= 0) {
            return '';
        }
        return (string)Db::name('region')->where('id', '=', $regionId)->value('name');
    }

    private static function existsProvinceAgent(int $provinceId, int $excludeUserId): bool
    {
        $query = Db::name('user')
            ->where('agent_province_id', '=', $provinceId)
            ->where('agent_city_id', '=', 0)
            ->where('agent_district_id', '=', 0)
            ->where('is_delete', '=', 0);
        if ($excludeUserId > 0) {
            $query->where('user_id', '<>', $excludeUserId);
        }
        return (bool)$query->find();
    }

    private static function existsCityAgent(int $cityId, int $excludeUserId): bool
    {
        $query = Db::name('user')
            ->where('agent_city_id', '=', $cityId)
            ->where('agent_district_id', '=', 0)
            ->where('is_delete', '=', 0);
        if ($excludeUserId > 0) {
            $query->where('user_id', '<>', $excludeUserId);
        }
        return (bool)$query->find();
    }

    private static function existsDistrictAgent(int $districtId, int $excludeUserId): bool
    {
        $query = Db::name('user')
            ->where('agent_district_id', '=', $districtId)
            ->where('is_delete', '=', 0);
        if ($excludeUserId > 0) {
            $query->where('user_id', '<>', $excludeUserId);
        }
        return (bool)$query->find();
    }

    private static function existsPendingApply(
        int $agentType,
        int $provinceId,
        int $cityId,
        int $districtId,
        int $excludeUserId
    ): bool {
        $query = Db::name('city_agent_apply')
            ->where('agent_type', '=', $agentType)
            ->where('status', '=', self::STATUS_PENDING)
            ->where('is_delete', '=', 0);

        if ($agentType === self::TYPE_PROVINCE) {
            $query->where('agent_province_id', '=', $provinceId);
        } elseif ($agentType === self::TYPE_CITY) {
            $query->where('agent_city_id', '=', $cityId)
                ->where('agent_district_id', '=', 0);
        } else {
            $query->where('agent_district_id', '=', $districtId);
        }

        if ($excludeUserId > 0) {
            $query->where('user_id', '<>', $excludeUserId);
        }
        return (bool)$query->find();
    }
}
