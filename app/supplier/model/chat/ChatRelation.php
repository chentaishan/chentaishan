<?php

namespace app\supplier\model\chat;
use app\common\model\plus\chat\ChatRelation as ChatRelationModel;


/**
 * 客服消息关系模型类
 */
class ChatRelation extends ChatRelationModel
{

    /**
     * 隐藏字段
     */
    protected $hidden = [
        'app_id',
        'update_time'
    ];

    
}
