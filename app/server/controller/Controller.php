<?php

namespace app\server\controller;

use app\common\exception\BaseException;
use app\JjjController;
use app\server\model\chat\ChatUser as ChatUserModel;
use think\facade\Cache;

/**
 * 商户后台控制器基类
 */
class Controller extends JjjController
{
    /** @var array $store 客服登录信息 */
    protected $server;
    /** @var string $route 当前控制器名称 */
    protected $controller = '';

    /** @var string $route 当前方法名称 */
    protected $action = '';

    /** @var string $route 当前路由uri */
    protected $routeUri = '';

    /** @var string $route 当前路由：分组名称 */
    protected $group = '';

    /** @var array $allowAllAction 登录验证白名单 */
    protected $allowAllAction = [
        // 登录页面
        '/passport/login',
        /*系统设置*/
        '/index/base',
        /*客服绑定用户*/
        'chat/chat/bindClient'
    ];

    /**
     * 后台初始化
     */
    public function initialize()
    {
        // 当前路由信息
        $this->getRouteinfo();
        //  验证登录状态
        $this->checkLogin();
    }

    /**
     * 解析当前路由参数 （分组名称、控制器名称、方法名）
     */
    protected function getRouteinfo()
    {
        // 控制器名称
        $this->controller = strtolower($this->request->controller());
        $this->controller = str_replace(".", "/", $this->controller);
        // 方法名称
        $this->action = Request()->action();
        // 控制器分组 (用于定义所属模块)
        $groupstr = strstr($this->controller, '.', true);
        $this->group = $groupstr !== false ? $groupstr : $this->controller;
        // 当前uri
        $this->routeUri = '/' . $this->controller . '/' . $this->action;
    }

    /**
     * 验证登录状态
     */
    private function checkLogin()
    {
        // 验证当前请求是否在白名单
        if (in_array($this->routeUri, $this->allowAllAction)) {
            return true;
        }
        $token = Request()->header('token');
        if (!$token) {
            throw new BaseException(['msg' => '缺少必要的参数：token', 'code' => -1]);
        }
        $tokenStatus = Cache::get('server_token_' . $token);
        if (!$tokenStatus) {
            throw new BaseException(['msg' => 'token失效', 'code' => -1]);
        }
        $data = checkToken($token, 'server');
        if ($data['code'] != 1) {
            throw new BaseException(['msg' => $data['msg'], 'code' => -1]);
        }
        if ($data['data']['type'] != 'server') {
            throw new BaseException(['msg' => '用户信息错误', 'code' => -1]);
        }
        if (!$user = ChatUserModel::getUser($data['data'])) {
            throw new BaseException(['msg' => '没有找到用户信息', 'code' => -1]);
        }
        // 保存登录状态
        $this->server = [
            'user' => [
                'chat_user_id' => $user['chat_user_id'],
                'user_name' => $user['user_name'],
                'shop_supplier_id' => $user['shop_supplier_id'],
                'app_id' => $user['app_id'],
                'user_id' => $user['user_id'],
                'nick_name' => $user['nick_name'],
            ],
            'app' => $user['app']->toArray(),
        ];
        return true;
    }

    /**
     * 获取客服id
     */
    protected function getServerId()
    {
        return $this->server['user']['chat_user_id'];
    }

    /**
     * 获取客服id
     */
    protected function getSupplierId()
    {
        return $this->server['user']['shop_supplier_id'];
    }
}
