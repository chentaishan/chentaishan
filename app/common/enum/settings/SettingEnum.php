<?php

namespace app\common\enum\settings;

use MyCLabs\Enum\Enum;

/**
 * 商城设置枚举类
 */
class SettingEnum extends Enum
{
    // 商城设置
    const STORE = 'store';
    // 商城设置
    const MP_SERVICE = 'mp_service';
    // 交易设置
    const TRADE = 'trade';
    // 短信通知
    const SMS = 'sms';
    // 模板消息
    const TPL_MSG = 'tplMsg';
    // 上传设置
    const STORAGE = 'storage';
    // 小票打印
    const PRINTER = 'printer';
    // 满额包邮设置
    const FULL_FREE = 'full_free';
    // 充值设置
    const RECHARGE = 'recharge';
    // 积分设置
    const POINTS = 'points';
    // 公众号设置
    const OFFICIA = 'officia';
    // 商品推荐
    const RECOMMEND = 'recommend';
    // 签到有礼
    const SIGN = 'sign';
    // 首页推送
    const HOMEPUSH = 'homepush';
    // 引导收藏
    const COLLECTION = 'collection';
    // 好物圈
    const BASIC = 'basic';
    // 积分商城
    const POINTSMALL = 'pointsmall';
    // 限时秒杀
    const SECKILL = 'seckill';
    // 限时拼团
    const ASSEMBLE = 'assemble';
    // 限时砍价
    const BARGAIN = 'bargain';
    // 直播设置
    const LIVE = 'live';
    // 底部导航设置
    const NAV = 'nav';
    // 邀请好友设置
    const APPSHARE = 'appshare';
    // 系统配置
    const SYS_CONFIG = 'sys_config';
    // 充值设置
    const BALANCE = 'balance';
    // h5支付宝支付设置
    const H5ALIPAY = 'h5Alipay';
    // 主题设置
    const THEME = 'theme';
    // 预售设置
    const ADVANCE = 'advance';
    // 余额提现设置
    const BALANCE_CASH = 'balance_cash';
    // 日常任务
    const TASK = 'task';
    // 用户协议
    const SERVICE = 'service';
    // 隐私协议
    const PRIVACY = 'privacy';

    /**
     * 获取订单类型值
     */
    public static function data()
    {
        return [
            self::STORE => [
                'value' => self::STORE,
                'describe' => '商城设置',
            ],
            self::MP_SERVICE => [
                'value' => self::MP_SERVICE,
                'describe' => '客服设置',
            ],
            self::TRADE => [
                'value' => self::TRADE,
                'describe' => '交易设置',
            ],
            self::SMS => [
                'value' => self::SMS,
                'describe' => '短信通知',
            ],
            self::TPL_MSG => [
                'value' => self::TPL_MSG,
                'describe' => '模板消息',
            ],
            self::STORAGE => [
                'value' => self::STORAGE,
                'describe' => '上传设置',
            ],
            self::PRINTER => [
                'value' => self::PRINTER,
                'describe' => '小票打印',
            ],
            self::FULL_FREE => [
                'value' => self::FULL_FREE,
                'describe' => '满额包邮设置',
            ],
            self::RECHARGE => [
                'value' => self::RECHARGE,
                'describe' => '充值设置',
            ],
            self::POINTS => [
                'value' => self::POINTS,
                'describe' => '积分设置',
            ],
            self::OFFICIA => [
                'value' => self::OFFICIA,
                'describe' => '公众号设置',
            ],
            self::RECOMMEND => [
                'value' => self::RECOMMEND,
                'describe' => '商品推荐',
            ],
            self::SIGN => [
                'value' => self::SIGN,
                'describe' => '签到有礼',
            ],
            self::HOMEPUSH => [
                'value' => self::HOMEPUSH,
                'describe' => '首页推送',
            ],
            self::COLLECTION => [
                'value' => self::COLLECTION,
                'describe' => '引导收藏',
            ],
            self::BASIC => [
                'value' => self::BASIC,
                'describe' => '好物圈',
            ],
            self::POINTSMALL => [
                'value' => self::POINTSMALL,
                'describe' => '积分商城',
            ],
            self::SECKILL => [
                'value' => self::SECKILL,
                'describe' => '限时秒杀',
            ],
            self::ASSEMBLE => [
                'value' => self::ASSEMBLE,
                'describe' => '限时拼团',
            ],
            self::BARGAIN => [
                'value' => self::BARGAIN,
                'describe' => '限时砍价',
            ],
            self::LIVE => [
                'value' => self::LIVE,
                'describe' => '直播设置',
            ],
            self::NAV => [
                'value' => self::NAV,
                'describe' => '底部导航设置',
            ],
            self::APPSHARE => [
                'value' => self::APPSHARE,
                'describe' => 'app分享',
            ],
            self::SYS_CONFIG => [
                'value' => self::SYS_CONFIG,
                'describe' => '系统设置',
            ],
            self::BALANCE => [
                'value' => self::BALANCE,
                'describe' => '充值设置',
            ],
            self::H5ALIPAY => [
                'value' => self::H5ALIPAY,
                'describe' => 'h5支付宝支付',
            ],
            self::THEME => [
                'value' => self::THEME,
                'describe' => '主题设置',
            ],
            self::ADVANCE => [
                'value' => self::ADVANCE,
                'describe' => '预售设置',
            ],
            self::BALANCE_CASH => [
                'value' => self::BALANCE_CASH,
                'describe' => '余额提现设置',
            ],
            self::TASK => [
                'value' => self::TASK,
                'describe' => '任务中心',
            ],
            self::SERVICE => [
                'value' => self::SERVICE,
                'describe' => '用户协议',
            ],
            self::PRIVACY => [
                'value' => self::PRIVACY,
                'describe' => '隐私协议',
            ],
        ];
    }

}