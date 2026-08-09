<?php


namespace app\common\model\plus\chat;

use app\common\model\BaseModel;

/**
 * 客服消息模型
 */
class Chat extends BaseModel
{
    protected $pk = 'chat_id';
    protected $name = 'chat';

    /**
     * 关联会员表
     */
    public function user()
    {
        return $this->hasOne("app\\common\\model\\user\\User", 'user_id', 'user_id');
    }

    /**
     * 关联会员表
     */
    public function supplier()
    {
        return $this->hasOne("app\\common\\model\\supplier\\Supplier", 'shop_supplier_id', 'shop_supplier_id');
    }

    /**
     * 关联客服表
     */
    public function server()
    {
        return $this->hasOne("app\\common\\model\\plus\\chat\\ChatUser", 'chat_user_id', 'chat_user_id');
    }

    //获取聊天验证id
    public function getIdentify($user_id, $muser_id)
    {
        if ($user_id > $muser_id) {
            $identify = $user_id . '_' . $muser_id;
        } else {
            $identify = $muser_id . '_' . $user_id;
        }
        return $identify;
    }

    //添加信息
    public function add($data)
    {
        // 开启事务
        $this->startTrans();
        try {
            $shop_supplier_id = (new ChatUser())->where('chat_user_id', '=', $data['chat_user_id'])->value('shop_supplier_id');
            $ChatRelation = new ChatRelation();
            $data['shop_supplier_id'] = $shop_supplier_id ? $shop_supplier_id : 0;
            $this->save($data);
            $info = $ChatRelation->where('user_id', '=', $data['user_id'])
                ->where('chat_user_id', '=', $data['chat_user_id'])
                ->find();
            if (!$info) {
                $ChatRelation->save($data);
            } else {
                $info->save(['update_time' => time()]);
            }
            $this->commit();
            return true;
        } catch (\Exception $e) {
            log_write($e->getMessage());
            $this->rollback();
            return false;
        }

    }

    //获取聊天信息
    public function getMessage($data)
    {
        $list = $this->with(['user', 'server'])
            ->where('chat_user_id', '=', $data['chat_user_id'])
            ->where('user_id', '=', $data['user_id'])
            ->order('chat_id desc')
            ->paginate($data);
        if (isset($data['msg_type'])) {
            $this->where('chat_user_id', '=', $data['chat_user_id'])
                ->where('user_id', '=', $data['user_id'])
                ->where('msg_type', '=', $data['msg_type'])
                ->update(['status' => 1]);
        }
        return $list;
    }

}