<?php

namespace app\api\controller\user;

use app\api\controller\Controller;
use app\api\model\order\Order as OrderModel;

/**
 * 拼团控制器
 */
class Assemble extends Controller
{
    // 当前用户
    private $user;

    /**
     * 构造方法
     */
    public function initialize()
    {
        parent::initialize();
        $this->user = $this->getUser();   // 用户信息

    }

    /**
     *拼团列表
     */
    public function lists()
    {
        $model = new OrderModel();
        $list = $model->getAssembleList($this->user['user_id'], $this->postData());
        return $this->renderSuccess('', compact('list'));
    }
}