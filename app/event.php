<?php
// 事件定义文件
return [
    'listen' => [
        'AppInit' => [],
        'HttpRun' => [],
        'HttpEnd' => [],
        'LogLevel' => [],
        'LogWrite' => [],
        'PaySuccess' => [
            \app\api\event\PaySuccess::class
        ],
        /*用户等级*/
        'UserGrade' => [
            \app\job\event\UserGrade::class
        ],
        /*供应商等级*/
        'AgentUserGrade' => [
            \app\job\event\AgentUserGrade::class
        ],
        /*任务调
        /*任务调度*/
        'JobScheduler' => [
            \app\job\event\JobScheduler::class
        ],
        /*订单事件*/
        'Order' => [
            \app\job\event\Order::class
        ],
        /*拼团订单*/
        'AssembleBill' => [
            \app\job\event\AssembleBill::class
        ],
        /*砍价任务*/
        'BargainTask' => [
            \app\job\event\BargainTask::class
        ],
        /*领取优惠券事件*/
        'UserCoupon' => [
            \app\job\event\UserCoupon::class
        ],
        /*分销商订单*/
        'AgentOrder' => [
            \app\job\event\AgentOrder::class
        ],
        /*直播间管理*/
        'LiveRoom' => [
            \app\job\event\LiveRoom::class
        ],
        /*预售订单*/
        'AdvanceOrder' => [
            \app\job\event\AdvanceOrder::class
        ],
        /*任务中心*/
        'UserTask' => [
            \app\job\event\UserTask::class
        ],
    ],

    'subscribe' => [
    ],
];
