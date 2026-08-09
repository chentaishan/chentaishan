<?php
/**
 * 福满堂商城奖励配置
 */
return [
    // 优品区确认收货后升级为会员；job_grade 仅允许 0=游客、1=会员（代理身份见 user.agent_*_id）
    'member_job_grade' => 1,

    // 优品区：支付成功登记区域代理奖待结算；确认收货释放入余额
    'first_zone_agent' => [
        'enabled'        => true,
        'region_enabled' => true,
        // ratio_id 对应 jjjshop_cloud_ratio（num 为订单实付的百分比）
        'ratio_id' => [
            'province'   => 10, // 省代理比例%
            'city_total' => 11, // 市代理总比例%（有区代时扣除区代池）
            'district'   => 12, // 区/县代理比例%
        ],
        'ratio_defaults' => [
            'province'   => 6,
            'city_total' => 5,
            'district'   => 4,
        ],
    ],

    // 优品区(zone_type=1)帕点奖：支付成功后从买家自身沿推荐人链向上查找，
    // 命中第一个「开启帕点奖(user.pa_point_reward=1)」的人员(自身也算)，
    // 发放 pay_price × cloud_ratio(ratio_id=70)% 入余额（即时发放）
    'pa_point' => [
        'enabled'       => true,
        'ratio_id'      => 70,
        'ratio_default' => 0,
        'max_depth'     => 200, // 向上查找最大层级，防止死循环
    ],

    // 消费区(zone_type=2)：支付成功按收货地址匹配省/市/区代，登记待结算(金额=商品配置×数量)；确认收货入账
    'normal_zone_region' => [
        'enabled' => true,
    ],

    // 代理进货区(zone_type=3)：支付成功登记待结算，确认收货释放入余额
    'agent_stock_region' => [
        'enabled' => true,
        'direct_push' => [
            'enabled'        => true,
            'ratio_id'       => 37, // 直推：pay_price×num%，备注「供应链拓客补贴」
            'ratio_default'  => 0,
        ],
    ],

    // 合伙人每日分红：当日0点前已收货未分红订单实付合计 × ratio_id → 合伙人均分
    'partner_dividend' => [
        'enabled'       => true,
        'ratio_id'      => 38,
        'ratio_default' => 0,
        // 测试计划任务：截止=当前执行时刻（勿在生产长期开启）
        'test_enabled'  => false,
    ],

    // 提现：jjjshop_cloud_ratio（后台「通用比例」或 withdrawalConfig 接口）
    'withdrawal' => [
        'ratio_id' => [
            'service_fee_percent' => 5,  // 提现手续费比例%
            'min_amount'          => 34, // 最低提现金额(元)
        ],
        'defaults' => [
            'min_amount'          => 100.00,
            'service_fee_percent' => 6.00,
        ],
    ],

    // 绿色积分兑换比例（jjjshop_cloud_ratio.num，status=0 启用）
    'green_points' => [
        'ratio_id' => [
            'voucher_ratio'        => 35, // 1绿色积分兑换抵扣券(元)
            'digital_rights_ratio' => 36, // 1绿色积分兑换数权
        ],
        'defaults' => [
            'voucher_ratio'        => 1.00,
            'digital_rights_ratio' => 1.00,
        ],
    ],

    // 贡献值记录（仅优品区 zone_type=1）：支付成功后按订单商品行×数量分记录；下级支付成功触发上级解锁；解锁额进入消费券
    'energy' => [
        'enabled'   => true,
        'zone_type' => 1,
        'ratio_id'  => [
            'package_amount'       => 30,
            'energy_multiplier'    => 31,
            'odd_release_percent'  => 32,
            'even_release_percent' => 33,
        ],
        'defaults' => [
            'package_amount'       => 2000.00,
            'energy_multiplier'    => 1.5,
            'odd_release_percent'  => 50,
            'even_release_percent' => 100,
        ],
    ],

    /**
     * 福满堂玩法（全部仅优品区 zone_type=1 触发，消费区暂无玩法）
     * 复用 user_energy_record / energy_unlock_log 作为贡献值记录与解锁流水。
     */
    'ruby' => [
        'enabled'   => true,
        'zone_type' => 1,

        // 贡献值：默认2次，第1次50%、第2次100%，合计1.5倍
        // 倍数 = Σ释放比例/100；基数=报单金额(ratio_id=30,默认2000)；解锁额全额进【消费券】
        'contribution' => [
            // 释放次数 ratio_id（默认2次）
            'release_count_ratio_id'   => 40,
            'release_count_default'    => 2,
            // 第 i 次释放比例 ratio_id = release_ratio_base + i（41..50 对应第1..10次）
            'release_ratio_base'       => 40,
            'max_release_count'        => 10,
            'release_ratio_defaults'   => [50, 100],
        ],
    ],

    /**
     * 福满堂：消费券包额度 + 消费券公共池 + 互转 + 买数字资产
     * 数据表见 database/20260618_hekangyuan_voucher.sql
     */
    'hekangyuan' => [
        'enabled'   => true,
        'zone_type' => 1,

        // 每件优品区下单赠送的消费券包额度（个人从公共池可领取上限，累加）
        'voucher_pack' => [
            'ratio_id' => 68,
            'default'  => 3000.00,
        ],

        // 消费券公共池：白/黑/大众三档分配比例%（后台可调）
        'pool' => [
            'ratio_id' => [
                'white'  => 61, // 白名单分配比例%
                'black'  => 62, // 黑名单分配比例%
                'public' => 63, // 大众分配比例%
                'self_weight' => 64, // 本人下单权重
                'push_weight' => 65, // 直推下单权重
            ],
            'defaults' => [
                'white'  => 20,
                'black'  => 5,
                'public' => 75,
                'self_weight' => 1,
                'push_weight' => 1,
            ],
        ],

        // 互转手续费%（全额进次日公共池）
        'transfer' => [
            'fee_ratio_id' => 66,
            'fee_default'  => 20,
        ],

        // 买数字资产手续费%（全额进次日公共池）
        'exchange' => [
            'fee_ratio_id' => 67,
            'fee_default'  => 20,
        ],

        // 数字资产（底池铸币/币价模型，复用香韵逻辑；承载于 jjjshop_hekangyuan_setting）
        // 卖出净额进【余额】；卖出手续费% 取 cloud_ratio(69)，默认0
        'digital' => [
            'sell_fee_ratio_id' => 69,
            'sell_fee_default'  => 0,
        ],

        // 会员权益身份枚举
        'identity' => [
            'public' => 0, // 大众（默认）
            'white'  => 1, // 白名单
            'black'  => 2, // 黑名单
        ],
    ],
];
