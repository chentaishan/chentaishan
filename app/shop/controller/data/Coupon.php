<?php

namespace app\shop\controller\data;

use app\shop\controller\Controller;
use app\shop\model\plus\coupon\Coupon as CouponModel;

/**
 * 优惠券控制器
 */
class Coupon extends Controller
{

    /**
     * 优惠券列表
     */
    public function index()
    {
        $list = (new CouponModel)->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }


}