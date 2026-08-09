<?php

namespace app\common\library\easywechat;

use app\api\service\order\paysuccess\type\PayTypeSuccessFactory;
use app\common\enum\order\OrderPayTypeEnum;
use app\common\exception\BaseException;
use app\common\model\app\App as AppModel;

/**
 * 微信支付
 */
class WxPay
{
    // 微信支付配置
    private $app;

    /**
     * 构造函数
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * 统一下单API
     */
    public function unifiedorder($order_no, $openid, $totalFee, $orderType, $pay_source, $multiple)
    {
        $data = [
            "mchid" => $this->app->getConfig()['mch_id'],
            "out_trade_no" => $order_no,
            "appid" => $this->app->getConfig()['app_id'],
            "description" => $order_no,
            "notify_url" => base_url() . 'index.php/job/notify/wxpay',
            'attach' => json_encode(['order_type' => $orderType, 'pay_source' => $pay_source, 'multiple' => $multiple]),
            "amount" => [
                "total" => intval($totalFee * 100),
                "currency" => "CNY"
            ],
            "payer" => [
                "openid" => $openid
            ]
        ];
        $url = "v3/pay/transactions/jsapi";
        //h5支付差异
        if ($pay_source == 'h5') {
            $url = "v3/pay/transactions/h5";
            unset($data['payer']);
            $data['scene_info'] = [
                "payer_client_ip" => request()->ip(),
                "h5_info" => [
                    "type" => "Wap"
                ]
            ];
        }
        if ($pay_source == 'android' || $pay_source == 'ios') {
            unset($data['payer']);
            $url = "v3/pay/transactions/app";
        }
        // 是否开启服务商支付
        if (isset($this->app->getConfig()['sub_appid']) && $this->app->getConfig()['sub_appid']) {
            $url = "v3/pay/partner/transactions/jsapi";
            unset($data['mchid']);
            unset($data['appid']);
            unset($data['payer']['openid']);
            $data['sp_appid'] = $this->app->getConfig()['sp_appid'];
            $data['sp_mchid'] = $this->app->getConfig()['sp_mchid'];
            $data['sub_appid'] = $this->app->getConfig()['sub_appid'];
            $data['sub_mchid'] = $this->app->getConfig()['sub_mch_id'];
            $data['payer']['sub_openid'] = $openid;
        }
        // 统一下单
        $payApp = $this->app->getClient();
        $response = $payApp->postJson($url, $data);
        $result = $response->toArray(false);

        //如果是微信小程序
        if ($pay_source == 'wx' || $pay_source == 'android' || $pay_source == 'ios' || $pay_source == 'mp') {
            // 请求失败
            if (!isset($result['prepay_id'])) {
                throw new BaseException(['msg' => "微信支付api：{$result['message']}", 'code' => 0]);
            }
            if ($pay_source == 'wx' || $pay_source == 'mp') {
                $prepayId = $result['prepay_id'];
                $utils = $this->app->getUtils();
                $appId = $this->app->getConfig()['app_id'];
                $signType = 'RSA';
                $config = $utils->buildMiniAppConfig($prepayId, $appId, $signType);
                return [
                    'appId' => $appId,
                    'nonceStr' => $config['nonceStr'],
                    'timeStamp' => $config['timeStamp'],
                    'paySign' => $config['paySign'],
                    "signType" => $config['signType'],
                    'package' => $config['package'],
                ];
            } else if ($pay_source == 'android' || $pay_source == 'ios') {
                $prepayId = $result['prepay_id'];
                $utils = $this->app->getUtils();
                $appId = $this->app->getConfig()['app_id'];
                $signType = 'RSA';
                $config = $utils->buildAppConfig($prepayId, $appId, $signType);
                return $config;
            }
        }
        // 请求失败
        if (!isset($result['h5_url'])) {
            throw new BaseException(['msg' => "微信支付api：{$result['message']}", 'code' => 0]);
        }
        return $result;
    }

    /**
     * 支付成功异步通知
     */
    public function notify()
    {
        if (!$json = file_get_contents('php://input')) {
            log_write('Not found DATA');
            $this->returnCode(false, 'Not found DATA');
        }
        log_write($json);
        $wechatpay_serial = request()->header('wechatpay-serial');
        $json = json_decode($json, true);
        $apikey = AppModel::getBySerial($wechatpay_serial);
        $AesUtil = new AesUtil($apikey);
        $data = $AesUtil->decryptToString($json['resource']['associated_data'], $json['resource']['nonce'], $json['resource']['ciphertext']);
        $data = json_decode($data, true);
        $attach = json_decode($data['attach'], true);
        // 实例化订单模型
        $PaySuccess = PayTypeSuccessFactory::getFactory($data['out_trade_no'], $attach);
        $app_id = $PaySuccess->isExist();
        $app_id == 0 && $this->returnCode(false, '订单不存在');
        if ($data['trade_state'] != 'SUCCESS') {
            $this->returnCode(false, $data['trade_state_desc']);
        }
        // 订单支付成功业务处理
        $status = $PaySuccess->onPaySuccess(OrderPayTypeEnum::WECHAT, $data);
        if ($status == false) {
            $this->returnCode(false, $PaySuccess->error);
        }
        // 返回状态
        $this->returnCode(true, 'OK');
    }

    /**
     * 申请退款API
     */
    public function refund($transaction_id, $total_fee, $refund_fee)
    {
        $out_refund_no = time();
        $data = [
            "transaction_id" => $transaction_id,
            "out_refund_no" => "{$out_refund_no}",
            "notify_url" => base_url(),
            "amount" => [
                "refund" => intval($refund_fee * 100),
                "total" => intval($total_fee * 100),
                "currency" => "CNY"
            ],
        ];
        $url = 'v3/refund/domestic/refunds';
        // 是否开启服务商支付
        if (isset($this->app->getConfig()['sub_appid']) && $this->app->getConfig()['sub_appid']) {
            $url = "v3/ecommerce/refunds/apply";
            $data['sp_appid'] = $this->app->getConfig()['sp_appid'];
            $data['sp_mchid'] = $this->app->getConfig()['sp_mchid'];
            //$data['sub_appid'] = $this->app->getConfig()['sub_appid'];
            $data['sub_mchid'] = $this->app->getConfig()['sub_mch_id'];
        }
        $payApp = $this->app->getClient();
        $result = $payApp->postJson($url, $data);
        $result = $result->toArray(false);
        // 请求失败
        if (!isset($result['refund_id'])) {
            throw new BaseException(['msg' => isset($result['message']) ? $result['message'] : '退款失败']);
        }
        return true;
    }

    /**
     * 企业付款到零钱API
     */
    public function transfers($order_no, $openid, $amount, $desc)
    {
        $api = $this->app->getClient();
        $result = $api->post('/mmpaymkttransfers/promotion/transfers', [
            'body' => [
                'mch_appid' => $this->app->getConfig()['app_id'],     //注意在配置文件中加上app_id
                'mchid' => $this->app->getConfig()['mch_id'],         //商户号
                'partner_trade_no' => $order_no,  // 商户订单号，需保持唯一性(只能是字母或者数字，不能包含有符号)
                'openid' => $openid,     //用户openid
                'check_name' => 'NO_CHECK',                  // NO_CHECK：不校验真实姓名, FORCE_CHECK：强校验真实姓名
                're_user_name' => '用户真实姓名',                  // 如果 check_name 设置为 FORCE_CHECK 则必填用户真实姓名
                'amount' => $amount * 100,                              //金额
                'desc' => $desc,                                // 企业付款操作说明信息。必填
            ],
            'local_cert' => $this->app->getConfig()['certificate'], //v2证书绝对路径
            'local_pk' => $this->app->getConfig()['private_key'],   //v2证书密钥绝对路径
        ]);
        // 请求失败
        if (empty($result)) {
            throw new BaseException(['msg' => '微信提现到零钱api请求失败']);
        }
        // 请求失败
        if ($result['return_code'] === 'FAIL') {
            throw new BaseException(['msg' => 'return_msg: ' . $result['return_msg']]);
        }
        if ($result['result_code'] === 'FAIL') {
            throw new BaseException(['msg' => 'err_code_des: ' . $result['err_code_des']]);
        }
        return true;
    }

    /**
     * 返回状态给微信服务器
     */
    private function returnCode($returnCode, $msg = null)
    {
        // 返回状态
        $return = [
            'return_code' => $returnCode ? 'SUCCESS' : 'FAIL',
            'return_msg' => $msg ?: 'OK',
        ];
        // 记录日志
        log_write([
            'describe' => '返回微信支付状态',
            'data' => $return
        ]);
        die($this->toXml($return));
    }

    /**
     * 输出xml字符
     * @param $values
     * @return bool|string
     */
    private function toXml($values)
    {
        if (!is_array($values)
            || count($values) <= 0
        ) {
            return false;
        }

        $xml = "<xml>";
        foreach ($values as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    public function transfer($data, $app_id)
    {
        $app = AppModel::detail($app_id);
        if ($app['wx_cash_type'] == 1) {
            $url = 'https://api.mch.weixin.qq.com/v3/transfer/batches';
            $user_name = "";
            if ($data['real_money'] >= 2000) {
                $user_name = $this->getEncrypt($data['real_name'], $app_id);
            }
            $pars = [];
            $pars['appid'] = $data['appid'];//直连商户的appid
            $pars['out_batch_no'] = 'sjzz' . time() . mt_rand(1000, 9999);//商户系统内部的商家批次单号，要求此参数只能由数字、大小写字母组成，在商户系统内部唯一
            $pars['batch_name'] = $data['desc'];//该笔批量转账的名称
            $pars['batch_remark'] = $data['desc'];//转账说明，UTF8编码，最多允许32个字符
            $pars['total_amount'] = $data['real_money'] * 100;//转账总金额 单位为“分”
            $pars['total_num'] = 1;//转账总笔数
            $pars['transfer_detail_list'][0] = [
                'out_detail_no' => $data['order_no'],
                'transfer_amount' => $pars['total_amount'],
                'transfer_remark' => $data['desc'],
                'openid' => $data['open_id'],
                'user_name' => $user_name
            ];//转账明细列表
            $res = $this->wechatTrans($pars, $app, $url);
            $resArr = json_decode($res, true);
            if (isset($resArr['batch_id'])) {
                $result['wx_cash_type'] = 1;
                $result['batch_id'] = $resArr['batch_id'];
                $result['code'] = 1;
                return $result;
            } else {
                $result['code'] = 0;
                $result['message'] = $resArr['message'];
                return $result;
            }
        } else {
            $url = 'https://api.mch.weixin.qq.com/v3/fund-app/mch-transfer/transfer-bills';
            $pars = [];
            $pars['appid'] = $data['appid'];//直连商户的appid
            $pars['out_bill_no'] = 'sjzz' . time() . mt_rand(1000, 9999);//【商户单号】 商户系统内部的商家单号，要求此参数只能由数字、大小写字母组成，在商户系统内部唯一
            $pars['transfer_scene_id'] = $data['scene_id'];//【转账场景ID】 该笔转账使用的转账场景，可前往“商户平台-产品中心-商家转账”中申请。如：1001-现金营销
            $pars['openid'] = $data['open_id'];//【收款用户OpenID】 商户AppID下，某用户的OpenID
            $pars['transfer_amount'] = $data['real_money'] * 100;//【转账金额】 转账金额单位为“分”。
            if ($data['real_money'] >= 2000) {
                $user_name = $this->getEncrypt($data['real_name'], $app_id);
                $pars['user_name'] = $user_name;
            }
            $pars['transfer_remark'] = $data['desc'];
            $pars['transfer_scene_report_infos'] = [
                [
                    'info_type' => '岗位类型',
                    'info_content' => '用户',
                ],
                [
                    'info_type' => '报酬说明',
                    'info_content' => '提现',

                ],
            ];//转账明细列表
            $res = $this->wechatTrans($pars, $app, $url);
            $resArr = json_decode($res, true);
            if (!empty($resArr['code'])) {
                $result['code'] = 0;
                $result['message'] = $resArr['message'];
                return $result;
            } else {
                $result['wx_cash_type'] = 2;
                $result['out_bill_no'] = $resArr['out_bill_no'];
                $result['package_info'] = $resArr['package_info'];
                $result['code'] = 1;
                return $result;
            }
        }
    }

    public function wechatTrans($pars, $app, $url)
    {
        $http_method = 'POST';//请求方法（GET,POST,PUT）
        $timestamp = time();//请求时间戳
        $url_parts = parse_url($url);//获取请求的绝对URL
        $nonce = $timestamp . rand('10000', '99999');//请求随机串
        $body = json_encode((object)$pars);//请求报文主体

        $apiclient_cert_arr = openssl_x509_parse($app['cert_pem']);
        $serial_no = $apiclient_cert_arr['serialNumberHex'];//证书序列号
        $mch_private_key = $app['key_pem'];//密钥
        $merchant_id = $app['mchid'];//商户id
        $canonical_url = ($url_parts['path'] . (!empty($url_parts['query']) ? "?{$url_parts['query']}" : ""));
        $message = $http_method . "\n" .
            $canonical_url . "\n" .
            $timestamp . "\n" .
            $nonce . "\n" .
            $body . "\n";
        openssl_sign($message, $raw_sign, $mch_private_key, 'sha256WithRSAEncryption');
        $sign = base64_encode($raw_sign);//签名
        $token = sprintf('mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $merchant_id, $nonce, $timestamp, $serial_no, $sign);//微信返回token
        return $this->https_request(json_encode($pars), $token, $app['serial_no'], $url);
    }


    public function https_request($data, $token, $serial_no, $url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, (string)$url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //添加请求头
        $headers = [
            'Authorization:WECHATPAY2-SHA256-RSA2048 ' . $token,
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
            'Wechatpay-Serial:' . $serial_no,
            'User-Agent:Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.132 Safari/537.36',
        ];
        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    public function getEncrypt($str, $app_id)
    {
        //$str是待加密字符串
        $public_key_path = root_path() . 'runtime/cert/app/' . $app_id . '/' . 'platform.pem';
        $public_key = file_get_contents($public_key_path);
        $encrypted = '';
        if (openssl_public_encrypt($str, $encrypted, $public_key, OPENSSL_PKCS1_OAEP_PADDING)) {
            //base64编码
            $sign = base64_encode($encrypted);
        } else {
            throw new Exception('encrypt failed');
        }
        return $sign;
    }
}
