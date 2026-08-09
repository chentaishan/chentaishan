<?php
// +----------------------------------------------------------------------
// | 短信：默认引擎与正文（短信宝且后台模板为空时使用下列 templates）
// | 占位符：{code} 验证码；{reason} 驳回原因；{store_name} 店铺名称
// +----------------------------------------------------------------------

return [
    // 非空时覆盖数据库「默认短信引擎」，可不依赖后台切换为短信宝
    'default_engine' => 'smsbao',
    'templates' => [
        'login_template' => '【昆仑基石浙江】您的验证码是{code}。如非本人操作，请忽略本短信',
        'apply_template' => '【昆仑基石浙江】您正在提交商户入驻申请，验证码{code}，5分钟内有效。',
        'supplier_reject_code' => '【昆仑基石浙江】您的商户入驻申请未通过审核。原因：{reason}。如有疑问请联系客服。',
        'supplier_pass_code' => '【昆仑基石浙江】恭喜您，店铺「{store_name}」入驻申请已通过审核，请登录商户端查看。',
    ],
    // 短信宝账号（后台未填时使用此处；AccessKeyId=账号，AccessKeySecret=明文密码）
    'smsbao' => [
        'AccessKeyId' => 'jlhw12',
        'AccessKeySecret' => 'jlhw123456',
        'api_url' => 'http://api.smsbao.com/',
    ],
];
