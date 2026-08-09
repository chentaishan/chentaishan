<?php

namespace app\api\model\plus\chat;

use app\common\model\plus\chat\ChatUser as ChatUserModel;

/**
 * 客服消息关系模型类
 */
class ChatUser extends ChatUserModel
{
    /**
     * 获取当前用户客服id
     */
    public function getChatUserId($user)
    {
        $chatUserId = $this->where('status', '=', 1)
            ->where('is_delete', '=', 0)
            ->where('user_id', '=', $user['user_id'])
            ->value('chat_user_id');
        return $chatUserId ? $chatUserId : 0;
    }

}
