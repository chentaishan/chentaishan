<?php

namespace app\common\library\huifu;

use app\api\service\order\paysuccess\type\PayTypeSuccessFactory;
use app\common\enum\order\OrderPayTypeEnum;
use app\common\enum\order\OrderTypeEnum;
use app\common\exception\BaseException;
use app\common\model\user\BalanceOrder;
use BsPaySdk\core\BsPay;
use BsPaySdk\core\BsPayClient;
use BsPaySdk\core\BsPayTools;
use BsPaySdk\request\V2TradePaymentJspayRequest;
use BsPaySdk\request\V2TradePaymentScanpayQueryRequest;
use BsPaySdk\request\V2TradePaymentScanpayRefundRequest;
use think\facade\Log;
use app\common\model\order\Order as OrderModel;
class HuiFuPay
{
    public static function isEnabled(): bool
    {
        return (bool)config('pay.huifu_enabled', false);
    }

    private function assertEnabled(): void
    {
        if (!self::isEnabled()) {
            throw new BaseException(['msg' => '汇付支付已关闭']);
        }
    }

    public function huifuPay($user, $order_no, $pay_source, $online_money, $multiple,$tradeType){
        $this->assertEnabled();
        Log::write('发起支付');
        $config = config('pay'.'.'.'huifu');
        $pay = new V2TradePaymentJspayRequest();
        // 请求日期
        $pay->setReqDate(date("Ymd")); //请求日期
        // 请求流水号
        $pay->setReqSeqId($order_no); //请求流水
        $pay->setGoodsDesc('商品描述');     //商品描述
        $pay->setTradeType($tradeType);              //交易类型  T_MINIAPP: 微信小程序
        $pay->setTransAmt($online_money);            //交易金额
        $pay->setHuifuId($config['subid']);          // 要改
        $notify_url = base_url().$config['notify_url'].'?order_type=' . OrderTypeEnum::MASTER . '&pay_source=' . $pay_source . '&ple=' . $multiple;
        $data = [
            'notify_url'=>$notify_url
        ];
        $pay->setExtendInfo($data);
        $client = new BsPayClient();
        $result = $client->postRequest($pay);
        return $result->getRspDatas();
    }

    //balanceHuifuPay
    public function balanceHuifuPay($user, $order_no, $pay_source, $online_money, $multiple,$tradeType){
        $this->assertEnabled();
        $config = config('pay'.'.'.'huifu');
        $pay = new V2TradePaymentJspayRequest();
        // 请求日期
        $pay->setReqDate(date("Ymd"));      //请求日期
        // 请求流水号
        $pay->setReqSeqId($order_no); //请求流水
        $pay->setGoodsDesc('充值');                //商品描述
        $pay->setTradeType($tradeType);              //交易类型  T_MINIAPP: 微信小程序
        $pay->setTransAmt($online_money);          //交易金额

        $pay->setHuifuId($config['subid']);// 要改
        $notify_url = base_url().$config['notify_url'].'?order_type=' . OrderTypeEnum::BALANCE . '&pay_source=' . $pay_source . '&ple=' . $multiple;
        $data = [
            'notify_url'=>$notify_url
        ];
        $pay->setExtendInfo($data);
        $client = new BsPayClient();
        $result = $client->postRequest($pay);
        return $result->getRspDatas();
    }


    public function refund($order, $money, $out_refund_no){
        $this->assertEnabled();
        $config = config('pay'.'.'.'huifu');
        $pay = new V2TradePaymentScanpayRefundRequest();
        // 请求日期
        $pay->setReqDate(date("Ymd"));      //请求日期
        // 请求流水号
        $pay->setReqSeqId($order['order_no']); //请求流水
        $pay->setOrdAmt($money);
        $pay->setOrgReqDate(date("Ymd",$order['pay_time']));
        $pay->setHuifuId($config['subid']);// 要改
        $notify_url = base_url().$config['refund_notify_url'];
        $data = [
            'org_hf_seq_id'=>$order['hf_seq_id'],
            'notify_url'=>$notify_url
        ];
        $pay->setExtendInfo($data);
        $client = new BsPayClient();
        $result = $client->postRequest($pay);
        $resultData =  $result->getRspDatas();
        if(isset($resultData['data']) && !empty($resultData['data']) && $resultData['data']['trans_stat'] == "S"){
            return true;
        }
        else{
            throw new BaseException(['msg' => 'return_msg: ' . $resultData['msg'] . ',' . $result['sub_msg']]);
        }
    }

    public function notify(){
        if (!self::isEnabled()) {
            echo 'SUCCESS';
            return;
        }
        Log::write('多余的参数');
        $params = $_GET;
        $order_type = $_GET['order_type'];
        $pay_source = $_GET['pay_source'];
        $multiple = $_GET['ple'];
        unset($params['order_type']);
        unset($params['pay_source']);
        unset($params['ple']);
        // 订单支付成功业务处理,兼容微信参数
        $attach = '{"order_type": "' . $order_type . '","pay_source":"' . $pay_source . '","multiple":"' . $multiple . '","trade_type":"hf"}';
        // 1. 接收回调数据
        Log::write('接收回调数据');
        $postData = file_get_contents('php://input'); // 获取原始 POST 数据
        Log::write('attach'.$attach);
        Log::write($postData);
        // 1. 解析URL查询字符串为数组
        parse_str($postData, $resultArray);
        Log::write('99999999999999');
        Log::record($resultArray);
        // 2. 解码resp_data中的JSON
        //$data = $resultArray['resp_data'];
        // 去除外层引号
        $data = $jsonStr = trim($resultArray['resp_data'], '"');

        if (empty($data)) {
            die('Invalid request');
        }
        // 2. 提取签名和回调数据
        $sign = $resultArray['sign']; // 回调数据中的签名
        //unset($data['sign']);  // 移除签名字段，保留原始数据

        Log::write('提取签名');
        Log::record($sign);

        // 3. 获取汇付支付的公钥
        $merConfig = BsPay::getConfig();
        $rsaHuifuPublicKey = $merConfig->rsa_huifu_public_key;

        // 4. 验证签名
        $result = BsPayTools::verifySign($sign, $data, $rsaHuifuPublicKey);
        Log::write('验证签名');
        Log::record($result);

        // 5. 解析 resp_data
        $data = json_decode($jsonStr, true);
        //$data = json_decode($postData, true); // 解析 JSON 数据
        Log::write('接收参数解析json格式');
        Log::record($data);
        $respData = $data;//json_decode($data['resp_data'], true); // 解析 resp_data
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::write('Invalid resp_data');
            die('Invalid resp_data');
        }

        // 6. 处理业务逻辑
        $respCode = $data['resp_code']; // 整体响应码
        $transStat = $respData['trans_stat']; // 交易状态
        $notify_type = $respData['notify_type'];
        // 实例化订单模型
        if($order_type == OrderTypeEnum::BALANCE){
            $orderInfo = BalanceOrder::where('req_seq_id',$respData['req_seq_id'])->where('pay_status',10)->find();
        }
        else{
            Log::write('查询订单信息'.$respData['req_seq_id'] . 'order_type:' . $order_type);
            $orderInfo = OrderModel::where('trade_no',$respData['req_seq_id'])->where('pay_status',10)->find();
        }
        if(!$orderInfo){
            Log::write('订单不存在：'.$respData['req_seq_id']);
        }
        Log::write('查到订单信息'.$orderInfo['order_no']);
        $PaySuccess = PayTypeSuccessFactory::getFactory($orderInfo['trade_no'], json_decode($attach, true));
        $app_id = $PaySuccess->isExist(10);
        if ($app_id == 0) {
            Log::write('未查到appid信息'.$app_id);
            echo 'error';
            exit();
        }
        Log::write('app_id:'.$app_id);
        if ($respCode === '00000000' && $transStat === 'S' && $notify_type == 1) {
            $data['attach'] = $attach;
            $data['transaction_id'] = $respData['req_seq_id'];

            Log::write('处理业务逻辑');
            $status = $PaySuccess->onPaySuccess(OrderPayTypeEnum::ALIPAY, $data);
            if ($status == true) {
                Log::write('回调成功了了了了了了了了了了');
                echo 'SUCCESS';
                exit();
            }
            else{
                Log::write('回调失败了了了了了了了了了了');
                echo 'FAIL';
                exit();
            }
        } else {
            // 交易失败，处理失败逻辑
            Log::write('回调失败');
            echo 'FAIL'; // 返回失败响应
        }
    }


    public function query($hfSeqId){
        $this->assertEnabled();
        $config = config('pay'.'.'.'huifu');
        $pay = new V2TradePaymentScanpayQueryRequest();
        $pay->setHuifuId($config['subid']);// 要改
        $pay->setOrgHfSeqId($hfSeqId);
        $client = new BsPayClient();
        $result = $client->postRequest($pay);
        return $result->getRspDatas();
    }

    /**
     * 返回状态给汇付
     */
    private function returnCode($returnCode, $msg = null)
    {
        // 返回状态
        $return_code = $returnCode ? 'SUCCESS' : 'FAIL';
        $return_msg = $msg ?: 'OK';
        // 记录日志
        log_write([
            'describe' => '返回汇付支付状态',
            'data' => $return_code,
            'msg' => $return_msg,
        ]);
        echo $return_code;
        die;
    }
}