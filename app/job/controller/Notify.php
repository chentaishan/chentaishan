<?php

namespace app\job\controller;


use app\common\library\alipay\AliPay;
use app\common\library\easywechat\AppWx;
use app\common\library\easywechat\WxPay;
use app\common\library\huifu\HuiFuPay;
use think\facade\Log;
use WeChatPay\Util\PemUtil;
use WeChatPay\Builder;
use WeChatPay\Crypto\Rsa;

/**
 * 微信支付回调
 */
class Notify
{
    /**
     * 微信支付回调
     */
    public function wxpay()
    {
        // 微信支付组件：验证异步通知
        $WxPay = new WxPay(false);
        $WxPay->notify();
    }

    /**
     * 支付宝支付回调（同步）
     */
    public function alipay_return()
    {
        $AliPay = new AliPay();
        $url = $AliPay->return();
        if($url){
            return redirect($url);
        }
    }

    /**
     * 支付宝支付回调（异步）
     */
    public function alipay_notify()
    {
        $AliPay = new AliPay();
        $AliPay->notify();
    }

    public function test()
    {
    // 设置参数
    // 商户号
    //  $merchantId = '1604029837';

    // 从本地文件中加载「商户API私钥」，「商户API私钥」会用来生成请求的签名
            $merchantPrivateKeyFilePath = 'file://D:\phpstudy_pro\www3\jjj_shop_enterprises\jjj_shop_enterprise\runtime\cert\app\10001\key.pem';
    //        $merchantPrivateKeyInstance = Rsa::from($merchantPrivateKeyFilePath, Rsa::KEY_TYPE_PRIVATE);

    // 「商户API证书」的「证书序列号」
    //        $merchantCertificateSerial = '513EAAB58E75023D4D338DB783736DB135AFDAE8';

    // 从本地文件中加载「微信支付平台证书」，用来验证微信支付应答的签名
            $platformCertificateFilePath = 'file://D:\phpstudy_pro\www3\jjj_shop_enterprises\jjj_shop_enterprise\runtime\cert\app\wechatpay\cert.pem';
    //        $platformPublicKeyInstance = Rsa::from($platformCertificateFilePath, Rsa::KEY_TYPE_PUBLIC);

    // 从「微信支付平台证书」中获取「证书序列号」
            $platformCertificateSerial = PemUtil::parseCertificateSerialNo($platformCertificateFilePath);
        print_r($platformCertificateSerial);die;
    }


    public function huiFuNotify()
    {
        if (!config('pay.huifu_enabled', false)) {
            echo 'SUCCESS';
            return;
        }
        Log::write('回调第一步');
        $huifu = new HuiFuPay();
        $huifu->notify();
    }

}
