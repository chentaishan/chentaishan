<?php

namespace app\shop\controller\cash;

use app\shop\controller\Controller;
use app\common\library\helper;
use app\shop\model\order\Order as OrderModel;
use app\shop\model\supplier\Supplier as SupplierModel;
use app\shop\model\plus\agent\User as AgentUserModel;
use app\shop\model\user\Cash as CashModel;
use app\shop\model\user\User as UserModel;
/**
 * 提现
 */
class Cash extends Controller
{
    /**
     * 首页概况
     */
    public function index()
    {

        // 平台销售总金额
        $orderTotalMoney = helper::number2((new OrderModel())->getOrderTotalMoney());
        // 未提现总金额(全平台总余额)
        $unCashMoney = helper::number2((new CashModel())->getUnCashTotalMoney());
        // 提现总金额
        $cashTotalMoney = helper::number2((new CashModel())->getCashTotalMoney());

        //平台销售
        $ptsx = [
            'orderTotalMoney'   => $orderTotalMoney,
            'unCashMoney'       => $unCashMoney,
            'cashTotalMoney'    => $cashTotalMoney,
        ];

        // 消费区总金额
        $rebuyZoneMoney = helper::number2((new OrderModel())->getRebuyZoneMoney());
        // 消费区区域代理提成（省/市/区）
        $storeCommission = helper::number2((new OrderModel())->getStoreCommission(2));
        // 消费区拓客补贴
        $directPushReward = helper::number2((new OrderModel())->getDirectPushReward());

        //消费区
        $rebuyZone = [
            'rebuyZoneMoney'    => $rebuyZoneMoney,
            'storeCommission'   => $storeCommission,
            'directPushReward'  => $directPushReward,
        ];

        // 优品区商品总金额
        $firstZoneMoney = helper::number2((new OrderModel())->getFirstZoneMoney());
        // 优品区区域代理提成（省/市/区）
        $storeCommission = helper::number2((new OrderModel())->getStoreCommission());

        // 优品区商品
        $firstZone = [
            'firstZoneMoney'  => $firstZoneMoney,
            'storeCommission' => $storeCommission,
        ];

        return $this->renderSuccess('', compact(
            'ptsx', 'rebuyZone', 'firstZone'
        ));
    }
}
