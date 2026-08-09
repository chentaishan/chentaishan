<?php

namespace app\common\library\easywechat;

use app\common\model\settings\Setting as SettingModel;
use app\common\exception\BaseException;
use app\common\model\app\AppOpen as AppOpenModel;
use app\common\model\app\App as AppModel;
use EasyWeChat\Pay\Application as payApp;

/**
 * 微信开放平台
 */
class AppOpen
{

    public static function getWxPayApp($app_id)
    {
        // 获取当前app信息
        $wxConfig = AppOpenModel::getAppOpenCache($app_id);
        // 验证appid和appsecret是否填写
        if (empty($wxConfig['openapp_id']) || empty($wxConfig['openapp_secret'])) {
            throw new BaseException(['msg' => '请到 [后台-应用-app设置] 填写appid 和 appsecret']);
        }

        $app = AppModel::detail($app_id);
        $sysConfig = SettingModel::getSysConfig();
        $is_service_pay = false;
        if ($sysConfig['weixin_service']['is_open'] == 1 && $app['weixin_service'] == 1) {
            $is_service_pay = true;
        }
        if (empty($app['cert_pem']) || empty($app['key_pem'])) {
            if (!$is_service_pay) {
                throw new BaseException(['msg' => '请先到[后台-应用-支付设置]填写微信支付证书文件']);
            }
        }
        // cert目录
        $filePath = root_path() . 'runtime/cert/app/' . $wxConfig['app_id'] . '/';
        $config = [
            'app_id' => $wxConfig['openapp_id'],
            'mch_id' => $app['mchid'],
            'secret_key' => $app['apikey'],   // API 密钥
            // 如需使用敏感接口（如退款、发送红包等）需要配置 API 证书路径(登录商户平台下载 API 证书)
            'certificate' => $filePath . 'cert.pem',
            'private_key' => $filePath . 'key.pem',
            'http' => [
                'throw' => true, // 状态码非 200、300 时是否抛出异常，默认为开启
                'timeout' => 5.0,
            ],
        ];
        if ($is_service_pay) {
            $config['sp_appid'] = $sysConfig['weixin_service']['app_id'];
            $config['sp_mchid'] = $sysConfig['weixin_service']['mch_id'];
            $config['sub_appid'] = $wxConfig['openapp_id'];
            $config['sub_mch_id'] = $app['mchid'];
            $config['secret_key'] = $sysConfig['weixin_service']['apikey'];
            $filePath = root_path() . 'runtime/cert/appwx/10000/';
            $config['certificate'] = $filePath . 'cert.pem';
            $config['private_key'] = $filePath . 'key.pem';
            $config['mch_id'] = $sysConfig['weixin_service']['mch_id'];
        }
        $payApp = new payApp($config);
        return $payApp;
    }

}
