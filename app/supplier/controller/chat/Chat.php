<?php

namespace app\supplier\controller\chat;

use app\supplier\model\chat\Chat as ChatModel;
use app\supplier\controller\Controller;
use app\supplier\model\user\User as UserModel;
use app\supplier\model\supplier\Supplier as SupplierModel;
use app\supplier\model\order\Order as OrderModel;
use app\supplier\model\chat\ChatUser as ChatUserModel;
use app\supplier\model\chat\ChatRelation as ChatRelationModel;
use app\supplier\model\settings\Setting as SettingModel;
use think\facade\Cache;

/**
 * 客服消息
 */
class Chat extends Controller
{

    //我的聊天列表
    public function index()
    {
        $Chat = new ChatUserModel;
        $data = $this->postData();
        $data['type'] = 1;
        $data['shop_supplier_id'] = $this->getSupplierId();
        $list = $Chat->getList($data);
        return $this->renderSuccess('', compact('list'));
    }

    //聊天记录
    public function list($chat_user_id)
    {
        $Chat = new ChatRelationModel;
        $list = $Chat->getList($chat_user_id, $this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    //获取聊天信息
    public function record()
    {
        $Chat = new ChatModel;
        $list = $Chat->getMessage($this->postData());
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
        //供应商信息
        $supplierInfo = SupplierModel::detail($this->getSupplierId(), ['logo']);
        //用户订单
        $orderList = (new OrderModel)->getOrderList($user_id, $this->postData());
        $data['userInfo'] = $userInfo;
        $data['logo'] = $supplierInfo['logo']['file_path'];
        $data['name'] = $supplierInfo['name'];
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
            $token = signToken($userInfo['chat_user_id'], 'server');
            $data = [
                'loginServiceUserVo' => $userInfo,
                'shopLogo' => $supplier['logo'] ? $supplier['logo']['file_path'] : '',
                'shopName' => $supplier['name'],
                'socketUrl' => SettingModel::getSysConfig()['url'],
                'token' => signToken($userInfo['chat_user_id'], 'server')
            ];
            Cache::tag('cache')->set('server_token_' . $token, $chat_user_id, 86400 * 30);
            return $this->renderSuccess('', compact('data'));
        }
    }
}