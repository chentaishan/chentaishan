<?php

namespace app\server\model\chat;

use app\common\model\plus\chat\Chat as ChatModel;

/**
 * 客服消息模型类
 */
class Chat extends ChatModel
{

    /**
     * 隐藏字段
     */
    protected $hidden = [
        'app_id',
        'status',
        'update_time'
    ];

    //消息列表
    public function getList($chat_user_id, $data)
    {
        $model = (new ChatRelation())->alias('cr');
        if ($data['nickName']) {
            $model = $model->where('u.nickName', 'like', '%' . $data['nickName'] . '%');
        }
        $list = $model->where(['chat_user_id' => $chat_user_id])
            ->join('user u', 'u.user_id=cr.user_id')
            ->field('cr.*,nickName,avatarUrl')
            ->order('cr.update_time desc')
            ->paginate($data);
        foreach ($list as $key => &$value) {
            $value['newMessage'] = $this->where('user_id', '=', $value['user_id'])
                ->where('chat_user_id', '=', $value['chat_user_id'])
                ->order('chat_id desc')
                ->find();
        }
        return $list;
    }

    //获取消息条数
    public function mCount($user)
    {
        $num = 0;
        if ($user) {
            $where[] = ['user_id', '=', $user['user_id']];
            $where[] = ['status', '=', 0];
            $where[] = ['msg_type', '=', 1];
            $num = $this->where($where)->count();
        }
        return $num;
    }
}
