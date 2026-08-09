<?php
return [
    // 汇付通道总开关（false：主订单支付宝走原生 AliPay，回调不处理）
    'huifu_enabled' => false,

    'huifu'=>[
        'subid'=>'6666000171249462',
        'notify_url'=>'index.php/job/Notify/huiFuNotify',
        // 个人钱包 回调地址
        'wallet_notify_url'=>'http://121.196.145.119:20010/pay/notify',
        // 钱包充值回调
        'recharge_notify_url'=>'http://121.196.145.119:20010/pay/notify',
        // 钱包支付回调
        'pay_notify_url'=>'http://121.196.145.119:20010/pay/notify',
        // 钱包转账回调
        'transfer_notify_url'=>'',
        'withdrawal_notify_url'=>'',
        // 个人业务申请回调
        'async_notify_url'=>'index.php/job/HuifuNotify/asyncNotify',
        // 主动取现回调
        'en_notify_url'=>'index.php/job/HuifuNotify/enNotify',
        // 退款回调
        'refund_notify_url'=>'index.php/job/Notify/refNotify',
    ]
];