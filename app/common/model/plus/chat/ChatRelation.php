<?php


namespace app\common\model\plus\chat;

use app\common\model\BaseModel;

/**
 * 客服消息关系模型
 */
class ChatRelation extends BaseModel
{
    protected $pk = 'relation_id';
    protected $name = 'chat_relation';

    /**
     * 关联会员表
     */
    public function user()
    {
        return $this->belongsTo('app\\common\\model\\user\\User', 'user_id', 'user_id');
    }

    /**
     * 关联供应商表
     */
    public function supplier()
    {
        return $this->belongsTo('app\\common\\model\\supplier\\Supplier', 'shop_supplier_id', 'shop_supplier_id');
    }

    /**
     * 关联客服表
     */
    public function chatUser()
    {
        return $this->belongsTo('app\\common\\model\\plus\\chat\\ChatUser', 'chat_user_id', 'chat_user_id');
    }

    //消息列表
    public function getList($chat_user_id, $data)
    {
        $model = $this;
        $list = $model->with(['user'])
            ->where(['chat_user_id' => $chat_user_id])
            ->order('update_time desc')
            ->paginate($data);
        return $list;
    }
}