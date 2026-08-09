<?php

namespace app\shop\controller\user;

use app\shop\controller\Controller;
use app\shop\model\user\DigitalRightsLog as DigitalRightsLogModel;
use app\shop\model\user\GreenPointsLog as GreenPointsLogModel;
use app\shop\model\user\VoucherLog as VoucherLogModel;

/**
 * 绿色积分 / 抵扣券 / 数权流水（后台）
 */
class GreenPoints extends Controller
{
    public function greenLog()
    {
        $list = (new GreenPointsLogModel)->getList($this->request->param());
        return $this->renderSuccess('', compact('list'));
    }

    public function voucherLog()
    {
        $list = (new VoucherLogModel)->getList($this->request->param());
        return $this->renderSuccess('', compact('list'));
    }

    public function digitalLog()
    {
        $list = (new DigitalRightsLogModel)->getList($this->request->param());
        return $this->renderSuccess('', compact('list'));
    }
}
