<?php

namespace app\common\service\activity;

/**
 * 活动奖励展示名称（写入 remark / 待结算列表 scene_text）
 */
class WzRewardRemarkHelper
{
    public static function zoneLabel(int $zoneType): string
    {
        $map = [
            ActivityRewardService::ZONE_FIRST       => '品牌优选区',
            ActivityRewardService::ZONE_REBUY       => '惠民区',
            ActivityRewardService::ZONE_AGENT_STOCK => '代理进货区',
            ActivityRewardService::ZONE_PARTNER => '合伙人区',
        ];
        return $map[$zoneType] ?? '';
    }

    /** 省/市/区代奖励名称，如「消费区省代奖励」「优品区市代奖励」 */
    public static function regionRewardRemark(int $zoneType, string $level): string
    {
        $prefix = self::zoneLabel($zoneType);
        $levelMap = [
            'province' => '省代奖励',
            'city'     => '市代奖励',
            'district' => '区代奖励',
        ];
        $suffix = $levelMap[$level] ?? '区域奖励';
        return $prefix !== '' ? $prefix . $suffix : $suffix;
    }

    public static function directPushRemark(int $zoneType): string
    {
        $prefix = self::zoneLabel($zoneType);
        if ($zoneType === ActivityRewardService::ZONE_REBUY) {
            return $prefix !== '' ? $prefix . '拓客补贴' : '拓客补贴';
        }
        return $prefix !== '' ? $prefix . '直推奖' : '直推奖';
    }

    /** 买家确认收货后发放的绿色积分 */
    public static function greenPointsGiftRemark(): string
    {
        $prefix = self::zoneLabel(ActivityRewardService::ZONE_REBUY);
        return $prefix !== '' ? $prefix . '绿色积分' : '绿色积分';
    }

    /** 代理进货区直推奖（确认收货释放入余额） */
    public static function agentStockDirectPushRemark(): string
    {
        return '供应链拓客补贴';
    }

    /** 代理进货区合伙人每日分红 */
    public static function partnerDividendRemark(): string
    {
        return '供应链合伙人分红';
    }

    /** 优品区帕点奖（支付成功即时入余额） */
    public static function paPointRemark(): string
    {
        $prefix = self::zoneLabel(ActivityRewardService::ZONE_FIRST);
        return $prefix !== '' ? $prefix . '帕点奖' : '帕点奖';
    }
}
