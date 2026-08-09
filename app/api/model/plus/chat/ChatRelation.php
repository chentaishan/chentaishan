<?php

namespace app\api\model\plus\chat;

use app\common\model\plus\chat\ChatRelation as ChatRelationModel;
use app\api\model\plus\chat\ChatUser as ChatUserModel;

/**
 * 客服消息关系模型类
 */
class ChatRelation extends ChatRelationModel
{
    /**
     * 分配客服
     */
    public function getChatUser($shop_supplier_id, $user = false)
    {
        $chat_user_id = 0;
        if ($user) {
            $chat_user_id = $this
                ->alias('cr')
                ->join('chat_user cu', 'cu.chat_user_id=cr.chat_user_id')
                ->where('cr.shop_supplier_id', '=', $shop_supplier_id)
                ->where('cu.status', '=', 1)
                ->where('cu.is_delete', '=', 0)
                ->value('cr.chat_user_id');
            if (!$chat_user_id) {
                $userId = (new ChatUserModel)->where('status', '=', 1)
                    ->where('shop_supplier_id', '=', $shop_supplier_id)
                    ->where('is_delete', '=', 0)
                    ->column('chat_user_id');
                $num = count($userId);
                if ($num) {
                    $key = mt_rand(0, $num - 1);
                    $chat_user_id = $userId[$key];
                }
            }
        }
        return $chat_user_id ? $chat_user_id : 0;
    }

}
