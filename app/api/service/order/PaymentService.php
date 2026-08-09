<?php

namespace app\api\service\order;

use app\common\enum\order\OrderPayTypeEnum;
use app\common\enum\order\OrderTypeEnum;
use app\common\library\alipay\AliPay;
use app\common\library\easywechat\AppMp;
use app\common\library\easywechat\AppOpen;
use app\common\library\easywechat\AppWx;
use app\common\library\easywechat\WxPay;
use app\common\library\huifu\HuiFuPay;

class PaymentService
{
    public static function wechat(
        $user,
        $order_no,
        $orderType,
        $pay_source,
        $online_money,
        $multiple = 0
    ) {
        $app = null;
        if ($pay_source == 'wx') {
            $app = AppWx::getWxPayApp($user['app_id']);
            $open_id = $user['open_id'];
        } elseif ($pay_source == 'mp') {
            $app = AppMp::getWxPayApp($user['app_id']);
            $open_id = $user['mpopen_id'];
        } elseif ($pay_source == 'h5') {
            $app = AppMp::getWxPayApp($user['app_id']);
            $open_id = '';
        } elseif ($pay_source == 'android' || $pay_source == 'ios') {
            $open_id = '';
            $app = AppOpen::getWxPayApp($user['app_id']);
        }
        $WxPay = new WxPay($app);
        return $WxPay->unifiedorder($order_no, $open_id, $online_money, $orderType, $pay_source, $multiple);
    }

    public static function alipay(
        $user,
        $order_no,
        $orderType,
        $pay_source,
        $online_money,
        $multiple = 0
    ) {
        $AliPay = new AliPay();
        return $AliPay->unifiedorder($order_no, $online_money, $orderType, $pay_source, $multiple);
    }

    /**
     * 汇付支付宝（关闭时回退原生支付宝）
     */
    public static function huifuAlipay($user, $order_no, $pay_source, $online_money, $multiple, $tradeType)
    {
        if (!config('pay.huifu_enabled', false)) {
            return self::alipay($user, $order_no, OrderTypeEnum::MASTER, $pay_source, $online_money, $multiple);
        }
        $hfPay = new HuiFuPay();
        return $hfPay->huifuPay($user, $order_no, $pay_source, $online_money, $multiple, $tradeType);
    }
}
