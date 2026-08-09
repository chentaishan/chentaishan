<?php

namespace app\shop\controller\chat;

use app\shop\model\plus\chat\Chat as ChatModel;
use app\shop\model\plus\chat\ChatUser as ChatUserModel;
use app\shop\model\plus\chat\ChatRelation as ChatRelationModel;
use app\shop\controller\Controller;
use app\shop\model\settings\Setting as SettingModel;
use \GatewayWorker\Lib\Gateway;
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
        $data['type'] = 2;
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

    //绑定uid
    public function bindClient()
    {
        $data = $this->postData();
        Gateway::bindUid($data['client_id'], 'supplier_' . $this->supplier['user']['supplier_user_id']);
        return $this->renderSuccess('绑定成功');
    }

    //添加客服
    public function add()
    {
        $model = new ChatUserModel;
        $data = $this->postData();
        $data['type'] = 2;
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

    //工作台登录
    public function workbench($chat_user_id)
    {
        $model = new ChatUserModel;
        $userInfo = $model->getUserLoginInfo($chat_user_id);
        if (!$userInfo) {
            return $this->renderError($model->getError() ?: '登录失败');
        } else {
            $token = signToken($userInfo['chat_user_id'], 'server');
            $data = [
                'loginServiceUserVo' => $userInfo,
                'shopLogo' => '',
                'shopName' => SettingModel::getItem('store')['name'],
                'socketUrl' => SettingModel::getSysConfig()['url'],
                'token' => $token
            ];
            Cache::tag('cache')->set('server_token_' . $token, $chat_user_id, 86400 * 30);
            return $this->renderSuccess('', compact('data'));
        }
    }
}