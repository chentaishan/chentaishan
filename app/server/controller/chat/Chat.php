<?php

namespace app\server\controller\chat;

use app\server\model\chat\Chat as ChatModel;
use app\server\controller\Controller;
use app\common\model\user\User as UserModel;
use app\common\model\supplier\Supplier as SupplierModel;
use app\server\model\order\Order as OrderModel;
use app\server\model\chat\ChatUser as ChatUserModel;
use app\server\model\chat\ChatRelation as ChatRelationModel;
use app\common\model\settings\Setting as SettingModel;
use GatewayWorker\Lib\Gateway;

/**
 * 客服消息
 */
class Chat extends Controller
{

    //我的聊天列表
    public function index()
    {
        $Chat = new ChatModel;
        $list = $Chat->getList($this->getServerId(), $this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    //聊天记录
    public function list()
    {
        $Chat = new ChatRelationModel;
        $list = $Chat->getList($this->getServerId(), $this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    //获取聊天信息
    public function record()
    {
        $Chat = new ChatModel;
        $data = $this->postData();
        $data['chat_user_id'] = $this->getServerId();
        $list = $Chat->getMessage($data);
        return $this->renderSuccess('', compact('list'));
    }

    //添加客服
    public function add()
    {
        $model = new ChatUserModel;
        $data = $this->postData();
        $data['type'] = 1;
        $data['shop_supplier_id'] = $this->getSupplierId();
        if ($model->add($data)) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    //编辑客服
    public function edit($chat_user_id)
    {
        $model = ChatUserModel::detail($chat_user_id);
        if ($this->request->isGet()) {
            return $this->renderSuccess('', compact('model'));
        }
        if ($model->edit($this->postData())) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }

    //删除客服
    public function delete($chat_user_id)
    {
        $model = ChatUserModel::detail($chat_user_id);
        if ($model->setDelete($this->postData())) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }

    //设置客服状态
    public function set($chat_user_id)
    {
        $model = ChatUserModel::detail($chat_user_id);
        if ($model->setStatus()) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model->getError() ?: '修改失败');
    }

    //获取用户信息
    public function getInfo($user_id)
    {
        //用户信息
        $userInfo = UserModel::detail($user_id);
        //客服信息
        $chatUserInfo = ChatUserModel::detail($this->getServerId());
        //用户订单
        $orderList = (new OrderModel)->getOrderList($user_id);
        $data['userInfo'] = $userInfo;
        $data['logo'] = $chatUserInfo['avatarUrl'];
        $data['name'] = $chatUserInfo['nick_name'];
        $data['orderList'] = $orderList;
        return $this->renderSuccess('', compact('data'));
    }

    //工作台登录
    public function workbench($chat_user_id)
    {
        $model = new ChatUserModel;
        $userInfo = $model->getUserLoginInfo($chat_user_id);
        if (!$userInfo) {
            return $this->renderError($model->getError() ?: '登录失败');
        } else {
            $supplier = SupplierModel::detail($userInfo['shop_supplier_id'], ['logo']);
            $data = [
                'userInfo' => $userInfo,
                'shopLogo' => $supplier['logo'] ? $supplier['logo']['file_path'] : '',
                'shopName' => $supplier['name'],
                'socketUrl' => SettingModel::getSysConfig()['url'],
                'token' => ''
            ];
            return $this->renderSuccess('', compact('data'));
        }
    }

    //聊天移除
    public function remove($relation_id)
    {
        $model = new ChatRelationModel();
        if ($model->remove($relation_id)) {
            return $this->renderSuccess('移除成功');
        }
        return $this->renderError($model->getError() ?: '移除失败');
    }

    //绑定uid
    public function bindClient()
    {
        $data = $this->postData();
        Gateway::bindUid($data['client_id'], 'server_' . $this->getServerId());
        return $this->renderSuccess('绑定成功');
    }
}