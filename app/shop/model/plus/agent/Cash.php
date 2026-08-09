<?php

namespace app\shop\model\plus\agent;

use app\common\library\easywechat\AppWx;
use app\common\library\easywechat\AppMp;
use app\common\model\app\AppMp as AppMpModel;
use app\common\model\app\AppOpen as AppOpenModel;
use app\common\model\app\AppWx as AppWxModel;
use app\common\service\message\MessageService;
use app\common\service\order\OrderService;
use app\common\library\easywechat\WxPay;
use app\common\model\plus\agent\Cash as CashModel;
use app\shop\model\plus\agent\Setting as SettingModel;
use app\shop\model\plus\agent\User as AgentUserModel;
use app\shop\model\user\User as UserModel;
use app\shop\service\order\ExportService;

/**
 * 分销商提现明细模型
 */
class Cash extends CashModel
{
    /**
     * 获取器：申请时间
     */
    public function getAuditTimeAttr($value)
    {
        return $value > 0 ? date('Y-m-d H:i:s', $value) : 0;
    }

    /**
     * 获取器：打款方式
     */
    public function getPayTypeAttr($value)
    {
        return ['text' => $this->payType[$value], 'value' => $value];
    }

    /**
     * 获取分销商提现列表
     */
    public function getList($data)
    {
        $model = $this;
        // 构建查询规则
        $model = $model->alias('cash')
            ->with(['user'])
            ->field('cash.*, agent.real_name, agent.mobile, user.nickName, user.avatarUrl')
            ->join('user', 'user.user_id = cash.user_id')
            ->join('agent_user agent', 'agent.user_id = cash.user_id')
            ->order(['cash.create_time' => 'desc']);
        // 查询条件
        if (isset($data['user_id']) && $data['user_id']) {
            $model = $model->where('cash.user_id', '=', $data['user_id']);
        }
        if (isset($data['search']) && $data['search']) {
            $model = $model->where('agent.real_name|agent.mobile', 'like', '%' . $data['search'] . '%');
        }
        if (isset($data['apply_status']) && $data['apply_status'] > 0) {
            $model = $model->where('cash.apply_status', '=', $data['apply_status']);
        }
        if (isset($data['pay_type']) && $data['pay_type'] > 0) {
            $model = $model->where('cash.pay_type', '=', $data['pay_type']);
        }
        $list = $model->paginate($data);
        foreach ($list as &$item) {
            $cancelStatus = 0;
            if ($item['apply_status']['value'] == 50 && $item['pay_time'] + 86400 < time()) {
                $cancelStatus = 1;
            }
            $item['cancelStatus'] = $cancelStatus;
        }
        return $list;
    }

    /**
     * 分销商提现审核
     */
    public function submit($param)
    {
        if ($this['apply_status']['value'] != 10 && $this['apply_status']['value'] != 20) {
            $this->error = '状态错误';
            return false;
        }
        $this->startTrans();
        try {
            $data = ['apply_status' => $param['apply_status']];
            if ($param['apply_status'] == 30) {
                $data['reject_reason'] = $param['reject_reason'];
            }
            // 更新申请记录
            $data['audit_time'] = time();
            self::update($data, ['id' => $param['id']]);
            // 提现驳回：解冻分销商资金
            if ($param['apply_status'] == 30) {
                User::backFreezeMoney($param['user_id'], $param['money']);
            }
            $detail = self::detail($param['id']);
            // 发送模板消息
            (new MessageService)->cash($detail);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    /**
     * 分销商提现：微信支付企业付款
     */
    public function wechatPay0()
    {
        // 微信用户信息
        $user = UserModel::detail($this['user_id']);
        // 生成付款订单号
        $orderNO = OrderService::createOrderNo();
        // 付款描述
        $desc = '分销商提现付款';
        // 微信支付api：企业付款到零钱
        $open_id = '';
        $app = [];
        if ($user['reg_source'] == 'mp') {
            $app = AppMp::getWxPayApp($user['app_id']);
            $open_id = $user['mpopen_id'];
        } else if ($user['reg_source'] == 'wx') {
            $app = AppWx::getWxPayApp($user['app_id']);
            $open_id = $user['open_id'];
        }

        if ($open_id == '') {
            $this->error = '未找到用户open_id';
            return false;
        }

        $WxPay = new WxPay($app);
        // 请求付款api
        if ($WxPay->transfers($orderNO, $open_id, $this['money'], $desc)) {
            // 确认已打款
            $this->money();
            return true;
        }
        return false;
    }

    /**
     * 商家转账到零钱
     */
    public function wechatPay()
    {
        // 微信用户信息
        $user = UserModel::detail($this['user_id']);
        // 生成付款订单号
        $orderNO = OrderService::createOrderNo();
        // 付款描述
        $desc = '余额提现付款';
        // 微信支付api：企业付款到零钱
        $open_id = '';
        $app_id = '';
        if ($this['source'] == 'mp') {
            $open_id = $user['mpopen_id'];
            $wxConfig = AppMpModel::getAppMpCache($app_id);
            $app_id = $wxConfig['mpapp_id'];
        } else if ($this['source'] == 'wx') {
            $open_id = $user['open_id'];
            $wxConfig = AppWxModel::getAppWxCache($app_id);
            $app_id = $wxConfig['wxapp_id'];
        } else if ($this['source'] == 'app') {
            $open_id = $user['appopen_id'];
            $wxConfig = AppOpenModel::getAppOpenCache($app_id);
            $app_id = $wxConfig['openapp_id'];
        }

        if ($open_id == '') {
            $this->error = '未找到用户open_id';
            return false;
        }
        $settings = SettingModel::getItem('settlement');
        $agentUser = AgentUserModel::getAgentDetail($this['user_id']);
        $wxPay = new WxPay(null);
        $pars['real_name'] = $agentUser['real_name'];
        $pars['appid'] = $app_id;
        $pars['desc'] = $desc;
        $pars['real_money'] = $this['money'];
        $pars['order_no'] = $orderNO;
        $pars['open_id'] = $open_id;
        $pars['scene_id'] = $settings['scene_id'];
        $resArr = $wxPay->transfer($pars, $user['app_id']);
        if ($resArr['code'] == 0) {
            $this->error = $resArr['message'];
            return false;
        } else {
            if ($resArr['wx_cash_type'] == 1) {
                $this->save([
                    'batch_id' => $resArr['batch_id']
                ]);
                // 确认打款
                $this->money();
                return true;
            } else {
                return $this->save([
                    'out_bill_no' => $resArr['out_bill_no'],
                    'package_info' => $resArr['package_info'],
                    'apply_status' => 50,
                    'pay_time' => time()
                ]);
            }
        }
    }

    /*
     *统计提现总数量
     */
    public function getAgentOrderTotal()
    {
        return $this->count('id');
    }

    /*
    * 统计提现待审核总数量
    */
    public function getAgentApplyTotal($apply_status)
    {
        return $this->where('apply_status', '=', $apply_status)->count();
    }

    /**
     * 导出分销商提现
     */
    public function exportList($data)
    {
        $model = $this;
        // 构建查询规则
        $model = $model->alias('cash')
            ->with(['user'])
            ->field('cash.*, agent.real_name, agent.mobile, user.nickName, user.avatarUrl')
            ->join('user', 'user.user_id = cash.user_id')
            ->join('agent_user agent', 'agent.user_id = cash.user_id')
            ->order(['cash.create_time' => 'desc']);
        // 查询条件
        if (isset($data['user_id']) && $data['user_id']) {
            $model = $model->where('cash.user_id', '=', $data['user_id']);
        }
        if (isset($data['search']) && $data['search']) {
            $model = $model->where('agent.real_name|agent.mobile', 'like', '%' . $data['search'] . '%');
        }
        if (isset($data['apply_status']) && $data['apply_status'] > 0) {
            $model = $model->where('cash.apply_status', '=', $data['apply_status']);
        }
        if (isset($data['pay_type']) && $data['pay_type'] > 0) {
            $model = $model->where('cash.pay_type', '=', $data['pay_type']);
        }
        // 获取列表数据
        $list = $model->select();
        // 导出excel文件
        (new Exportservice)->cashList($list);
    }

    /**
     * 撤销付款
     */
    public function cancelPay()
    {
        $this->startTrans();
        try {
            if ($this['apply_status']['value'] != 50) {
                $this->error = '状态错误';
                return false;
            }
            $this->save(['apply_status' => 30, 'reject_reason' => '用户超时未确认收款']);
            User::backFreezeMoney($this['user_id'], $this['money']);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

}