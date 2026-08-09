<?php

namespace app\api\controller\plus\chat;

use app\api\model\plus\chat\Chat as ChatModel;
use app\api\controller\Controller;
use \GatewayWorker\Lib\Gateway;
use app\api\model\settings\Setting as SettingModel;

/**
 * 客服消息
 */
class Chat extends Controller
{
    protected $user;

    /**
     * 构造方法
     */
    public function initialize()
    {
        $this->user = $this->getUser();
    }

    //用户聊天列表
    public function index()
    {
        $Chat = new ChatModel;
        $list = $Chat->myList($this->user);
        $url = SettingModel::getSysConfig()['url'];
        return $this->renderSuccess('', compact('list', 'url'));
    }

    //客服聊天列表
    public function userList($chat_user_id)
    {
        $Chat = new ChatModel;
        $list = $Chat->myChatList($chat_user_id);
        $url = SettingModel::getSysConfig()['url'];
        return $this->renderSuccess('', compact('list', 'url'));
    }

    //获取聊天信息
    public function record()
    {
        $Chat = new ChatModel;
        $data = $this->postData();
        $list = $Chat->getMessage($data);
        return $this->renderSuccess('', compact('list'));
    }

    //获取聊天用户信息
    public function getInfo()
    {
        $Chat = new ChatModel;
        $data = $this->postData();
        $info = $Chat->getInfo($data);
        if($info){
            return $this->renderSuccess('', compact('info'));
        }else{
            return $this->renderError($Chat->getError() ?: '暂未设置客服');
        }

    }

    //绑定uid
    public function bindClient()
    {
        $param = $this->postData();
        if ($param['type'] == 1) {
            Gateway::bindUid($param['client_id'], $this->user['user_id']);
            $data['Online'] = Gateway::isUidOnline('server_' . $param['chat_user_id']) ? 'on' : 'off';
            return $this->renderSuccess('绑定成功', compact('data'));
        } else {
            Gateway::bindUid($param['client_id'], 'server_' . $param['chat_user_id']);
            $data['Online'] = Gateway::isUidOnline($param['user_id']) ? 'on' : 'off';
            return $this->renderSuccess('绑定成功', compact('data'));
        }
    }

}