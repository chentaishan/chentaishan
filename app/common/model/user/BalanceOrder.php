<?php

namespace app\common\model\user;

use app\common\library\easywechat\AppWx;
use app\common\library\easywechat\wx\WxOrder;
use app\common\model\BaseModel;
use app\common\enum\order\OrderPayStatusEnum;
use app\common\enum\order\OrderPayTypeEnum;
use app\common\model\settings\Setting as SettingModel;
use app\common\service\order\OrderService;
/**
 * 充值订单模型
 */
class BalanceOrder extends BaseModel
{
    protected $name = 'balance_order';
    protected $pk = 'order_id';

    /**
     * 关联用户表
     */
    public function user()
    {
        return $this->belongsTo('app\\common\\model\\user\\User', 'user_id', 'user_id');
    }

    /**
     * 付款状态
     */
    public function getPayTypeAttr($value)
    {
        return ['text' => OrderPayTypeEnum::data()[$value]['name'], 'value' => $value];
    }
    /**
     * 付款状态
     */
    public function getPayStatusAttr($value)
    {
        return ['text' => OrderPayStatusEnum::data()[$value]['name'], 'value' => $value];
    }

    /**
     * 订单详情
     */
    public static function detail($where, $with = ['user'])
    {
        is_array($where) ? $filter = $where : $filter['order_id'] = (int)$where;
        return (new static())->with($with)->find($where);
    }

    /**
     * 生成订单号
     */
    public function orderNo()
    {
        return OrderService::createOrderNo();
    }

    public function sendWxExpress($order, $user)
    {
        //判断是否开启小程序发货
        $setting = SettingModel::getItem('store', $order['app_id']);
        if (!$setting['is_send_wx']) {
            return false;
        }
        // 如果是小程序微信支付，则提交
        if ($order['pay_type']['value'] != 20 || $order['pay_source'] != 'wx' || $order['transaction_id'] == '') {
            return false;
        }
        sleep(1);
        $express = null;
        $logistics_type = 3;
        // 请求参数
        $params_arr = [
            // 订单，需要上传物流信息的订单
            'order_key' => [
                // 订单单号类型，用于确认需要上传详情的订单。枚举值1，使用下单商户号和商户侧单号；枚举值2，使用微信支付单号。
                "order_number_type" => 2,
                // 原支付交易对应的微信订单号
                "transaction_id" => $order['transaction_id']
            ],
            // 发货模式，发货模式枚举值：1、UNIFIED_DELIVERY（统一发货）2、SPLIT_DELIVERY（分拆发货） 示例值: UNIFIED_DELIVERY
            "delivery_mode" => 1,
            // 物流模式，发货方式枚举值：1、实体物流配送采用快递公司进行实体物流配送形式 2、同城配送 3、虚拟商品，虚拟商品，例如话费充值，点卡等，无实体配送形式 4、用户自提
            "logistics_type" => $logistics_type,//$this->getLogisticsType($this['delivery_type']),
            // 物流信息列表，发货物流单列表，支持统一发货（单个物流单）和分拆发货（多个物流单）两种模式，多重性: [1, 10]
            "shipping_list" => [
                [
                    // 物流单号，物流快递发货时必填，示例值: 323244567777 字符字节限制: [1, 128]
                    "tracking_no" => '',
                    // 物流公司编码，快递公司ID，参见「查询物流公司编码列表」，物流快递发货时必填， 示例值: DHL 字符字节限制: [1, 128]
                    "express_company" => '',
                    // 商品信息，例如：微信红包抱枕*1个，限120个字以内
                    "item_desc" => "余额充值",
                    // 联系方式，当发货的物流公司为顺丰时，联系方式为必填，收件人或寄件人联系方式二选一
                    "contact" => [
                        // 收件人联系方式，收件人联系方式为，采用掩码传输，最后4位数字不能打掩码 示例值: `189****1234, 021-****1234, ****1234, 0**2-***1234, 0**2-******23-10, ****123-8008` 值限制: 0 ≤ value ≤ 1024
                        "receiver_contact" => ''
                    ]
                ]
            ],
            // 上传时间，用于标识请求的先后顺序 示例值: `2022-12-15T13:29:35.120+08:00`
            "upload_time" => $this->getUploadTime(),
            // 支付者，支付者信息
            "payer" => [
                // 用户标识，用户在小程序appid下的唯一标识。 下单前需获取到用户的Openid 示例值: oUpF8uMuAJO_M2pxb1Q9zNjWeS6o 字符字节限制: [1, 128]
                "openid" => $user['open_id']
            ]
        ];
        // 小程序配置信息
        $app = AppWx::getApp($order['app_id']);
        $model = new WxOrder($app);
        if ($model->uploadExpress($params_arr)) {
            return true;
        } else {
            log_write($model->getError());
            return false;
        }
    }

    private function getUploadTime()
    {
        $microtime = microtime();
        list($microSeconds, $timeSeconds) = explode(' ', $microtime);
        $milliseconds = round($microSeconds * 1000);
        return date('Y-m-d') . 'T' . date('H:i:s') . '.' . $milliseconds . '+08:00';
    }
}
