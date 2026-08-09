<?php

namespace app\common\model\settings;

use app\common\enum\settings\SettingEnum;
use think\facade\Cache;
use app\common\enum\settings\DeliveryTypeEnum;
use app\common\model\BaseModel;
use app\common\enum\settings\OperateTypeEnum;
use think\Model;
use app\common\library\captcha\Captcha;
use app\common\library\captcha\Captcode;

/**
 * 系统设置模型
 */
class Setting extends BaseModel
{
    protected $name = 'setting';
    protected $createTime = false;

    /**
     * 获取器: 转义数组格式
     */
    public function getValuesAttr($value)
    {
        return json_decode($value, true);
    }

    /**
     * 修改器: 转义成json格式
     */
    public function setValuesAttr($value)
    {
        return json_encode($value);
    }

    /**
     * 获取验证码
     */
    public function getLoginCode($palte)
    {
        $code_status = self::getSysConfig()[$palte];
        $codeImage = "";
        $codeKey = "";
        if ($code_status) {
            //生成code
            $captcode = new Captcode();
            $generator = $captcode->generate();
            //生成图片
            $captcha = new Captcha();
            $image = $captcha->create($generator);
            $codeImage = $image['image'];
            $codeKey = $image['uniqid'];
            Cache::tag('cache')->set($generator['uniqid'] . '_' . $palte, $generator['value'], 300);
        }
        $data = [
            'codeImage' => $codeImage,
            'codeKey' => $codeKey
        ];
        return $data;
    }

    /**
     * 获取指定项设置
     */
    public static function getItem($key, $app_id = null)
    {
        $data = self::getAll($app_id);
        $data_key = $data[$key];
        if (isset($data_key)) {
            $data_key = $data[$key]['values'];
            jsonRecursive($data_key);
        } else {
            $data_key = [];
        }
        return $data_key;
    }

    /**
     * 获取系统配置
     */
    public static function getSysConfig()
    {
        $model = new static;
        $result = $model->withoutGlobalScope()->where('key', '=', SettingEnum::SYS_CONFIG)->value('values');
        if (!$result) {
            $result = $model->defaultData()[SettingEnum::SYS_CONFIG]['values'];
        } else {
            $result = json_decode($result, true);
            $result = array_merge_multiple($model->defaultData()[SettingEnum::SYS_CONFIG]['values'], $result);
        }
        return $result;
    }

    /**
     * 获取指定项设置
     */
    public static function getSupplierItem($key, $shop_supplier_id, $app_id = null)
    {
        $data = self::getAll($app_id, $shop_supplier_id);
        $data_key = $data[$key];
        if (isset($data_key)) {
            $data_key = $data[$key]['values'];
            jsonRecursive($data_key);
        } else {
            $data_key = [];
        }

        return $data_key;
    }

    /**
     * 获取设置项信息
     */
    public static function detail($key, $shop_supplier_id = 0)
    {
        $where = [];
        if ($shop_supplier_id) {
            $where['shop_supplier_id'] = $shop_supplier_id;
        }

        return (new static())->where('key', '=', $key)->where($where)->find();
    }

    /**
     * 全局缓存: 系统设置
     */
    public static function getAll($app_id = null, $shop_supplier_id = 0)
    {
        $static = new static;
        is_null($app_id) && $app_id = $static::$app_id;
        if (!$data = Cache::get('setting_' . $app_id . '_' . $shop_supplier_id)) {
            $setting = $static->where(compact('app_id'))->where('shop_supplier_id', $shop_supplier_id)->select();
            $data = empty($setting) ? [] : array_column($static->collection($setting)->toArray(), null, 'key');
            $data && Cache::tag('cache')->set('setting_' . $app_id . '_' . $shop_supplier_id, $data);
        }
        return $static->getMergeData($data);
    }

    /**
     * 数组转换为数据集对象
     */
    public function collection($resultSet)
    {
        $item = current(get_mangled_object_vars($resultSet));
        if ($item instanceof Model) {
            return \think\model\Collection::make($resultSet);
        } else {
            return \think\Collection::make($resultSet);
        }
    }


    /**
     * 合并用户设置与默认数据
     */
    private function getMergeData($userData)
    {
        $defaultData = $this->defaultData();
        if (isset($userData['nav']['values']['data']['list'])) {
            unset($defaultData['nav']['values']['data']['list']);
        }
        if (isset($userData['balance_cash']['values'])) {
            unset($defaultData['balance_cash']['values']);
        }
        return array_merge_multiple($defaultData, $userData);
    }

    /**
     * 店铺是否开启
     */
    public static function getStoreOpen()
    {
        $data = (new static())->getItem(SettingEnum::STORE);
        return $data['store_open'] == 1;
    }

    /**
     * 默认配置
     */
    public function defaultData($storeName = null)
    {
        return [
            'store' => [
                'key' => 'store',
                'describe' => '商城设置',
                'values' => [
                    // 商城名称
                    'name' => $storeName ?: '玖玖珈商城',
                    // 快递100
                    'kuaidi100' => [
                        'customer' => '1',
                        'secret' => '',
                        'key' => '1',
                    ],
                    //默认头像
                    'avatarUrl' => base_url() . 'image/user/avatarUrl.png',
                    // 押金
                    'supplier_cash' => '0',
                    // 模式类型，B2B B2B2C
                    'operate_type' => OperateTypeEnum::B2B,
                    // 新商品是否需要审核
                    'add_audit' => '1',
                    // 审核通过后再修改是否需要审核
                    'edit_audit' => '1',
                    // 供应商入驻图片
                    'supplier_image' => base_url() . 'image/supplier/supplier_image.jpg',
                    // 是否开启短信
                    'sms_open' => '1',
                    // 是否记录日志
                    'is_get_log' => true,
                    'store_open' => '1',//是否显示店铺0不显示
                    // 是否向小程序发送物流
                    'is_send_wx' => false,
                    //商城logo
                    'logoUrl' => base_url() . 'image/diy/logo.png',
                    // H5注册是否开启短信验证
                    'h5_sms_open' => true,
                    // 小程序是否开启微信授权
                    'wx_open' => true,
                    // 小程序是否开启内部手机绑定
                    'wx_phone' => true,
                    // 公众号是否开启微信授权
                    'mp_open' => true,
                    // 腾讯地图key
                    'tx_key' => '',
                    // 默认昵称前缀
                    'user_name' => '会员',
                    // 登录logo
                    'login_logo' => base_url() . 'image/user/login_logo.png',
                    // 登录描述
                    'login_desc' => '成为会员，立享更多优惠福利',
                    // 公众号是否开启手机绑定
                    'mp_phone' => true,
                ],
            ],
            'mp_service' => [
                'key' => 'mp_service',
                'describe' => '公众号客服设置',
                'values' => [
                    // qq
                    'qq' => '',
                    // 微信
                    'wechat' => '',
                    // 微信公众号图片
                    'mp_image' => '',
                ],
            ],
            'trade' => [
                'key' => 'trade',
                'describe' => '交易设置',
                'values' => [
                    'order' => [
                        'close_days' => '3',
                        'receive_days' => '10',
                        'refund_days' => '7'
                    ],
                    'freight_rule' => '10',
                ]
            ],
            'storage' => [
                'key' => 'storage',
                'describe' => '上传设置',
                'values' => [
                    'default' => 'local',
                    'max_image' => '2',
                    'max_video' => '50',
                    'engine' => [
                        'local' => [],
                        'qiniu' => [
                            'bucket' => '',
                            'access_key' => '',
                            'secret_key' => '',
                            'domain' => 'http://'
                        ],
                        'aliyun' => [
                            'bucket' => '',
                            'access_key_id' => '',
                            'access_key_secret' => '',
                            'domain' => 'http://'
                        ],
                        'qcloud' => [
                            'bucket' => '',
                            'region' => '',
                            'secret_id' => '',
                            'secret_key' => '',
                            'domain' => 'http://'
                        ],
                    ]
                ],
            ],
            'sms' => [
                'key' => 'sms',
                'describe' => '短信通知',
                'values' => [
                    'default' => 'aliyun',
                    'engine' => [
                        'aliyun' => [
                            'AccessKeyId' => '',
                            'AccessKeySecret' => '',
                            'sign' => '',
                            'login_template' => '',
                            'apply_template' => '',
                            'supplier_reject_code' => '',
                            'supplier_pass_code' => ''
                        ],
                        'qcloud' => [
                            'AccessKeyId' => '',
                            'AccessKeySecret' => '',
                            'sign' => '',
                            'login_template' => '',
                            'apply_template' => '',
                            'supplier_reject_code' => '',
                            'supplier_pass_code' => ''
                        ],
                        'hwcloud' => [
                            'AccessKeyId' => '',
                            'AccessKeySecret' => '',
                            'sign' => '',
                            'sender' => '',
                            'login_template' => '',
                            'apply_template' => '',
                            'supplier_reject_code' => '',
                            'supplier_pass_code' => '',
                            'url' => ''
                        ],
                        'smsbao' => [
                            'AccessKeyId' => '',
                            'AccessKeySecret' => '',
                            'sign' => '',
                            'api_url' => 'http://api.smsbao.com/',
                            'login_template' => '',
                            'apply_template' => '',
                            'supplier_reject_code' => '',
                            'supplier_pass_code' => ''
                        ],
                    ],
                ],
            ],
            'tplMsg' => [
                'key' => 'tplMsg',
                'describe' => '模板消息',
                'values' => [
                    'payment' => [
                        'is_enable' => '0',
                        'template_id' => '',
                    ],
                    'delivery' => [
                        'is_enable' => '0',
                        'template_id' => '',
                    ],
                    'refund' => [
                        'is_enable' => '0',
                        'template_id' => '',
                    ],
                ],
            ],
            'printer' => [
                'key' => 'printer',
                'describe' => '小票打印机设置',
                'values' => [
                    'is_open' => '0',   // 是否开启打印
                    'printer_id' => '', // 打印机id
                    'order_status' => [], // 订单类型 10下单打印 20付款打印 30确认收货打印
                    'label_print_type' => '0',
                    'siid' => ''
                ],
            ],
            'full_free' => [
                'key' => 'full_free',
                'describe' => '满额包邮设置',
                'values' => [
                    'is_open' => '0',   // 是否开启满额包邮
                    'money' => '',      // 单笔订单额度
                ],
            ],
            'recharge' => [
                'key' => 'recharge',
                'describe' => '用户充值设置',
                'values' => [
                    'is_entrance' => '1',   // 是否允许用户充值
                    'is_custom' => '1',   // 是否允许自定义金额
                    'is_match_plan' => '1',   // 自定义金额是否自动匹配合适的套餐
                    'describe' => "1. 账户充值仅限微信在线方式支付，充值金额实时到账；\n" .
                        "2. 账户充值套餐赠送的金额即时到账；\n" .
                        "3. 账户余额有效期：自充值日起至用完即止；\n" .
                        "4. 若有其它疑问，可拨打客服电话400-000-1234",     // 充值说明
                ],
            ],
            'points' => [
                'key' => 'points',
                'describe' => '积分设置',
                'values' => [
                    'points_name' => '积分',         // 积分名称自定义
                    'is_shopping_gift' => '0',      // 是否开启购物送积分
                    'gift_ratio' => '100',            // 是否开启购物送积分
                    'is_shopping_discount' => '0',    // 是否允许下单使用积分抵扣
                    'is_trans_balance' => '0',    // 是否允许转换余额
                    'discount' => [     // 积分抵扣
                        'discount_ratio' => '0.01',       // 积分抵扣比例
                        'full_order_price' => '100.00',       // 订单满[?]元
                        'max_money_ratio' => '10',             // 最高可抵扣订单额百分比
                    ],
                    // 充值说明
                    'describe' => "a) 积分不可兑现、不可转让,仅可在本平台使用;\n" .
                        "b) 您在本平台参加特定活动也可使用积分,详细使用规则以具体活动时的规则为准;\n" .
                        "c) 积分的数值精确到个位(小数点后全部舍弃,不进行四舍五入)\n" .
                        "d) 买家在完成该笔交易(订单状态为“已签收”)后才能得到此笔交易的相应积分,如购买商品参加店铺其他优惠,则优惠的金额部分不享受积分获取;",
                ],
            ],
            'officia' => [
                'key' => 'officia',
                'describe' => '公众号关注',
                'values' => [
                    'status' => 0
                ],
            ],
            'collection' => [
                'key' => 'collection',
                'describe' => '引导收藏',
                'values' => [
                    'status' => 0
                ],
            ],
            'recommend' => [
                'key' => 'recommend',
                'describe' => '商品推荐',
                'values' => [
                    'is_recommend' => '0',
                    'location' => [],
                    'choice' => '0',
                    'type' => '10',
                    'num' => '20',
                    'product' => []
                ],
            ],
            'basic' => [
                'key' => 'basic',
                'describe' => '好物圈',
                'values' => [
                    // 是否开启
                    'status' => 0,
                    // 是否同步购物车 (商品收藏)
                    'is_shopping' => '0',
                    // 是否同步订单
                    'is_order' => '0',
                ]
            ],
            'homepush' => [
                'key' => 'homepush',
                'describe' => '首页推送',
                'values' => [
                    // 是否开启
                    'is_open' => 0,
                ]
            ],
            'pointsmall' => [
                'key' => 'pointsmall',
                'describe' => '积分商城',
                'values' => [
                    // 是否开启
                    'is_open' => false,
                    // 是否使用优惠券
                    'is_coupon' => false,
                    // 是否分销
                    'is_agent' => false,
                ]
            ],
            'bargain' => [
                'key' => 'bargain',
                'describe' => '限时砍价',
                'values' => [
                    // 是否使用优惠券
                    'is_coupon' => false,
                    // 是否分销
                    'is_agent' => false,
                    // 是否开启积分
                    'is_point' => false,
                    // 规则
                    'bargain_rules' => ''
                ]
            ],
            'sign' => [
                'key' => 'sign',
                'describe' => '签到有礼',
                'values' => [
                    // 是否开启
                    'is_open' => false,
                    'is_increase' => false,
                    'increase_reward' => 0,
                    'no_increase' => 0,
                    'ever_sign' => 0,
                    'reward_data' => [],
                    'coupon' => [],
                    'is_coupon' => false,
                    'sign_type' => '0',
                    'content' => ''
                ]
            ],
            'seckill' => [
                'key' => 'seckill',
                'describe' => '限时秒杀',
                'values' => [
                    // 是否开启积分
                    'is_point' => false,
                    // 是否开启分销
                    'is_agent' => false,
                    //未付款订单自动关闭时间,分钟
                    'order_close' => 10,
                    // 是否使用优惠券
                    'is_coupon' => false,
                ]
            ],
            'assemble' => [
                'key' => 'assemble',
                'describe' => '限时拼团',
                'values' => [
                    // 是否开启
                    'is_open' => false,
                    // 是否开启积分
                    'is_point' => false,
                    // 是否开启分销
                    'is_agent' => false,
                    // 是否使用优惠券
                    'is_coupon' => false,
                ]
            ],
            'live' => [
                'key' => 'live',
                'describe' => '直播设置',
                'values' => [
                    // 是否开启直播
                    'is_open' => '0',
                    //是否开启审核
                    'is_audit' => '1',
                    //礼物名称
                    'gift_name' => '玖币',
                    // appid
                    'sdkappid' => '',
                    // key
                    'key' => '',
                    // 是否开启录制
                    'is_record' => '0',
                    // 存储设置
                    'vendor' => '',
                    'region' => '',
                    'bucket' => '',
                    'accessKey' => '',
                    'secretKey' => '',
                    'username' => '',
                    'password' => '',
                    'domain' => ''
                ]
            ],
            'appshare' => [
                'key' => 'appshare',
                'describe' => 'app分享',
                'values' => [
                    // 分享类型 1公众号/h5 2小程序 3下载页
                    'type' => '1',
                    // 公众号、h5地址
                    'open_site' => '',
                    // 小程序原始id
                    'gh_id' => '',
                    // 跳转网页
                    'web_url' => '',
                    // 下载页
                    'down_url' => '',
                    // 绑定类型
                    'bind_type' => '1'
                ]
            ],
            'sys_config' => [
                'key' => 'sys_config',
                'describe' => '系统设置',
                'values' => [
                    'admin_name' => '三勾商城运营管理系统',
                    'admin_bg_img' => '',
                    'shop_name' => '三勾商城管理系统',
                    'shop_bg_img' => '',
                    'shop_logo_img' => '',
                    'supplier_name' => '三勾商城商户管理系统',
                    'supplier_bg_img' => '',
                    'supplier_logo_img' => '',
                    'server_name' => '三勾商城客服服务系统',
                    'server_bg_img' => '',
                    'url' => 'wss://',
                    'service_open' => 0,
                    'admin_code' => 1,
                    'shop_code' => 1,
                    'supplier_code' => 1,
                    'server_code' => 1,
                    // 微信服务商支付
                    'weixin_service' => [
                        'is_open' => 0,
                        'app_id' => '',
                        'mch_id' => '',
                        'apikey' => '',
                        'cert_pem' => '',
                        'key_pem' => '',
                        'serial_no' => ''
                    ],
                    'storage' => [
                        'default' => 'local',
                        'max_image' => '2',
                        'max_video' => '20',
                        'engine' => [
                            'local' => [],
                            'qiniu' => [
                                'bucket' => '',
                                'access_key' => '',
                                'secret_key' => '',
                                'domain' => 'http://'
                            ],
                            'aliyun' => [
                                'bucket' => '',
                                'access_key_id' => '',
                                'access_key_secret' => '',
                                'domain' => 'http://'
                            ],
                            'qcloud' => [
                                'bucket' => '',
                                'region' => '',
                                'secret_id' => '',
                                'secret_key' => '',
                                'domain' => 'http://'
                            ],
                        ]]
                ]
            ],
            'balance' => [
                'key' => 'balance',
                'describe' => '充值设置',
                'values' => [
                    // 是否开启
                    'is_open' => 0,
                    // 是否可以自定义
                    'is_plan' => 1,
                    // 最低充值金额
                    'min_money' => 1,
                    // 充值说明
                    'describe' => "a) 账户充值仅限在线方式支付，充值金额实时到账；\n" .
                        "b) 有问题请联系客服;\n",
                ]
            ],
            'h5Alipay' => [
                'key' => 'h5Alipay',
                'describe' => 'h5支付宝支付',
                'values' => [
                    // 是否开启
                    'is_open' => false,
                    // 支付宝app_id
                    'app_id' => '',
                    // 支付宝公钥
                    'publicKey' => '',
                    // 应用私钥
                    'privateKey' => ''
                ]
            ],
            'nav' => [
                'key' => 'nav',
                'describe' => '底部导航',
                'values' => [
                    'data' => [
                        'is_auto' => "0",
                        "type" => "0",
                        "backgroundColor" => "#FFFFFF",
                        "textColor" => "#000000",
                        "textHoverColor" => "#E2231A",
                        "bulge" => "true",
                        // 菜单
                        'list' => [
                            [
                                'text' => '首页',
                                'iconPath' => self::$base_url . 'image/tabbar/home.png',
                                'selectedIconPath' => self::$base_url . 'image/tabbar/home_active.png',
                                "link_url" => "/pages/index/index",
                                'is_show' => true
                            ],
                            [
                                'text' => '分类',
                                'iconPath' => self::$base_url . 'image/tabbar/category.png',
                                'selectedIconPath' => self::$base_url . 'image/tabbar/category_active.png',
                                "link_url" => "/pages/product/category",
                                'is_show' => true
                            ],
                            [
                                'text' => '商户',
                                'iconPath' => self::$base_url . 'image/tabbar/shop.png',
                                'selectedIconPath' => self::$base_url . 'image/tabbar/shop_active.png',
                                "link_url" => "/pages/shop/shop_list",
                                'is_show' => true
                            ],
                            [
                                'text' => '购物车',
                                'iconPath' => self::$base_url . 'image/tabbar/cart.png',
                                'selectedIconPath' => self::$base_url . 'image/tabbar/cart_active.png',
                                "link_url" => "/pages/cart/cart",
                                'is_show' => true
                            ],
                            [
                                'text' => '我的',
                                'iconPath' => self::$base_url . 'image/tabbar/user.png',
                                'selectedIconPath' => self::$base_url . 'image/tabbar/user_active.png',
                                "link_url" => "/pages/user/index/index",
                                'is_show' => true
                            ],
                        ]
                    ]
                ]
            ],
            SettingEnum::THEME => [
                'key' => 'theme',
                'describe' => '主题设置',
                'values' => [
                    'theme' => '0',//主题设置
                ],
            ],
            SettingEnum::ADVANCE => [
                'key' => 'advance',
                'describe' => '预售设置',
                'values' => [
                    // banner图
                    'image' => [],
                    // 预售未付款关闭订单时间
                    'end_time' => '10',//分钟
                    // 支付定金预售结束后未付款结束时间
                    'pay_time' => '24',//小时
                    // 未支付尾款是否退定金
                    'money_return' => '0',//0否1是
                    // 是否开启分销
                    'is_agent' => '0',//0否1是
                ],
            ],
            SettingEnum::BALANCE_CASH => [
                'key' => 'balance_cash',
                'describe' => '余额提现设置',
                'values' => [
                    // 是否开启
                    'is_open' => '0',
                    // 最低提现金额
                    'min_money' => 1,
                    // 提现比例
                    'cash_ratio' => 100,
                    // 提现方式
                    'pay_type' => ['10', '20', '30'],   // 参数值：10微信支付 20支付宝支付 30银行卡支付
                    //微信提现场景ID
                    'scene_id' => ''
                ]
            ],
            SettingEnum::TASK => [
                'key' => 'task',
                'describe' => '任务中心',
                'values' => [
                    //任务背景图
                    'back_image' => base_url() . 'image/task/task.png',
                    //成长任务
                    'grow_task' => [
                        [
                            'name' => '上传头像',
                            'image' => base_url() . 'image/task/image.png',
                            'is_open' => '1',
                            'task_type' => 'image',
                            'rule' => '首次修改头像奖励5积分',
                            'points' => 5,
                            'status' => 0
                        ],
                        [
                            'name' => '完善基本资料',
                            'image' => base_url() . 'image/task/base.png',
                            'is_open' => '1',
                            'task_type' => 'base',
                            'rule' => '首次修改昵称奖励5积分',
                            'points' => 5,
                            'status' => 0
                        ],
                    ],
                    //日常任务
                    'day_task' => [
                        [
                            'name' => '分享商品',
                            'image' => base_url() . 'image/task/product.png',
                            'is_open' => '1',
                            'task_type' => 'product',
                            'rule' => '当日分享商品给好友奖励5积分',
                            'points' => 5,
                            'status' => 0
                        ],
                        [
                            'name' => '分享文章',
                            'image' => base_url() . 'image/task/article.png',
                            'is_open' => '1',
                            'task_type' => 'article',
                            'rule' => '当日分享文章给好友奖励5积分',
                            'points' => 5,
                            'status' => 0
                        ],
                    ],
                ]
            ],
            SettingEnum::SERVICE => [
                'key' => 'service',
                'describe' => '用户协议',
                'values' => [
                    'service' => '',
                ]
            ],
            SettingEnum::PRIVACY => [
                'key' => 'privacy',
                'describe' => '隐私协议',
                'values' => [
                    'privacy' => '',
                ]
            ],
        ];
    }

}
