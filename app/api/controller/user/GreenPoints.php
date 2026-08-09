<?php

namespace app\api\controller\user;

use app\api\controller\Controller;
use app\api\model\user\DigitalRightsLog as DigitalRightsLogModel;
use app\api\model\user\GreenPointsLog as GreenPointsLogModel;
use app\api\model\user\VoucherLog as VoucherLogModel;
use app\common\service\greenpoints\GreenPointsService;
/**
 * 绿色积分 / 抵扣券 / 数权
 */
class GreenPoints extends Controller
{
    private $user;

    /**
     * 构造方法
     */
    public function initialize()
    {
        parent::initialize();
        $this->user = $this->getUser();
    }

    /**
     * 资产概览与兑换配置
     */
    public function asset()
    {
        $userId = (int)$this->user['user_id'];
        if (!GreenPointsService::supportsSchema()) {
            return $this->renderError('请先执行数据库升级脚本 database/20260601_green_points.sql');
        }
        $balances = (new GreenPointsService())->getUserBalances($userId);
        $exchange = GreenPointsService::getExchangeConfig();
        return $this->renderSuccess('', array_merge($balances, ['exchange' => $exchange]));
    }

    /**
     * 绿色积分兑换
     * type: voucher | digital_rights
     * amount: 消耗绿色积分数量
     */
    public function exchange()
    {
        $type   = (string)$this->request->post('type', '');
        $amount = (float)$this->request->post('amount', 0);
        try {
            $balances = (new GreenPointsService())->exchange((int)$this->user['user_id'], $type, $amount);
            return $this->renderSuccess('兑换成功', $balances);
        } catch (\Throwable $e) {
            return $this->renderError($e->getMessage() ?: '兑换失败');
        }
    }

    /**
     * 绿色积分明细
     */
    public function greenLog()
    {
        $list = (new GreenPointsLogModel)->getList($this->user['user_id'], $this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 抵扣券明细
     */
    public function voucherLog()
    {
        $list = (new VoucherLogModel)->getList($this->user['user_id'], $this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 数权明细
     */
    public function digitalLog()
    {
        $list = (new DigitalRightsLogModel)->getList($this->user['user_id'], $this->postData());
        return $this->renderSuccess('', compact('list'));
    }
}
