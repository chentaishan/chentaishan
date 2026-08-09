<?php

namespace app\api\model\plus\chat;

use app\common\model\plus\chat\Chat as ChatModel;
use app\common\model\plus\chat\ChatUser as ChatUserModel;
use app\api\model\supplier\Supplier as SupplierModel;
use app\api\model\user\User as UserModel;
use app\api\model\settings\Setting as SettingModel;

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
        'identify',
        'status',
        'update_time'
    ];

    //消息列表
    public function myList($user)
    {
        $ChatRelation = new ChatRelation();
        $list = $ChatRelation->alias('cr')
            ->with(['user'])
            ->join('chat_user cu', 'cu.chat_user_id=cr.chat_user_id')
            ->where('cr.user_id', '=', $user['user_id'])
            ->where('cu.is_delete', '=', 0)
            ->field('cr.*,cu.nick_name as nickName,cu.avatarUrl as serviceLogo')
            ->order('update_time desc')
            ->select();
        foreach ($list as $key => &$value) {
            $value['avatarUrl'] = $value['user'] ? $value['user']['avatarUrl'] : '';
            $where['chat_user_id'] = $value['chat_user_id'];
            $where['user_id'] = $user['user_id'];
            $where['status'] = 0;
            $where['msg_type'] = 1;
            $value['num'] = $this->where($where)->count();
            unset($where['status']);
            unset($where['msg_type']);
            $value['newMessage'] = $this->where($where)->order('chat_id desc')->field('content,create_time,type')->find();
        }
        return $list;
    }

    //消息列表
    public function myChatList($chat_user_id)
    {
        $ChatRelation = new ChatRelation();
        $list = $ChatRelation->alias('cr')
            ->with(['user'])
            ->join('chat_user cu', 'cu.chat_user_id=cr.chat_user_id')
            ->where('cr.chat_user_id', '=', $chat_user_id)
            ->where('cu.is_delete', '=', 0)
            ->field('cr.*')
            ->order('cr.update_time desc')
            ->select();
        foreach ($list as $key => &$value) {
            $value['avatarUrl'] = $value['user'] ? $value['user']['avatarUrl'] : '';
            $value['nickName'] = $value['user'] ? $value['user']['nickName'] : '';
            $where['chat_user_id'] = $chat_user_id;
            $where['user_id'] = $value['user_id'];
            $where['status'] = 0;
            $where['msg_type'] = 2;
            $value['num'] = $this->where($where)->count();
            unset($where['status']);
            unset($where['msg_type']);
            $value['newMessage'] = $this->where($where)->order('chat_id desc')->field('content,create_time,type')->find();
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

    //获取用户信息
    public function getInfo($data)
    {
        if ($data['chat_user_id'] == 0) {
            $this->error = '暂未设置客服';
            return false;
        }
        $userInfo = UserModel::detail($data['user_id']);
        $chatUserInfo = ChatUserModel::detail($data['chat_user_id']);
        $data['avatarUrl'] = $userInfo['avatarUrl'];
        $data['logo'] = $chatUserInfo['avatarUrl'];
        $data['chat_name'] = $chatUserInfo['nick_name'];
        $data['url'] = SettingModel::getSysConfig()['url'];
        $supplier = SupplierModel::detail($data['shop_supplier_id'], ['logo']);
        $data['name'] = $supplier ? $supplier['name'] : '';
        $data['supplier_logo'] = $supplier && $supplier['logo'] ? $supplier['logo']['file_path'] : '';
        return $data;
    }

    public static function getNoReadCount($user_id)
    {
        return self::where('user_id', '=', $user_id)
            ->where('status', '=', 0)
            ->where('msg_type', '=', 2)
            ->count();
    }

}
