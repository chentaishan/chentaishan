<?php
namespace app\common\enum\order;

use MyCLabs\Enum\Enum;

/**
 * 订单状态
 */
class OrderStatusEnum extends Enum
{
    // 进行中
    const NORMAL = 10;

    // 已取消
    const CANCELLED = 20;

    // 待取消
    const APPLY_CANCEL = 21;

    // 已完成
    const COMPLETED = 30;

    // 已售后（整单退款关闭，区别于用户主动取消）
    const REFUNDED = 22;

    /**
     * 已结束、不再参与进行中流程的订单状态
     */
    public static function closedStatusList(): array
    {
        return [self::CANCELLED, self::APPLY_CANCEL, self::REFUNDED];
    }

    /**
     * 获取枚举数据
     */
    public static function data()
    {
        return [
            self::NORMAL => [
                'name' => '进行中',
                'value' => self::NORMAL,
            ],
            self::CANCELLED => [
                'name' => '已取消',
                'value' => self::CANCELLED,
            ],
            self::APPLY_CANCEL => [
                'name' => '待取消',
                'value' => self::APPLY_CANCEL,
            ],
            self::REFUNDED => [
                'name' => '已售后',
                'value' => self::REFUNDED,
            ],
            self::COMPLETED => [
                'name' => '已完成',
                'value' => self::COMPLETED,
            ],
        ];
    }

}