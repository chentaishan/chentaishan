<?php

namespace app\api\controller\plus\agent;

use app\api\controller\Controller;
use app\api\model\plus\agent\Setting;
use app\api\model\plus\agent\User as AgentUserModel;
use app\api\model\plus\agent\Cash as CashModel;
use app\common\model\app\App as AppModel;

/**
 * 分销商提现
 */
class Cash extends Controller
{
    private $user;

    private $Agent;
    private $setting;

    /**
     * 构造方法
     */
    public function initialize()
    {
        // 用户信息
        $this->user = $this->getUser();
        // 分销商用户信息
        $this->Agent = AgentUserModel::detail($this->user['user_id']);
        // 分销商设置
        $this->setting = Setting::getAll();
    }

    /**
     * 提交提现申请
     */
    public function submit($data)
    {
        $formData = json_decode(htmlspecialchars_decode($data), true);

        $model = new CashModel;
        if ($model->submit($this->Agent, $formData)) {
            return $this->renderSuccess('申请提现成功');
        }
        return $this->renderError($model->getError() ?: '提交失败');
    }

    /**
     * 分销商提现明细
     */
    public function lists($status = -1, $source = '')
    {
        $config = (new AppModel())->getCashInfo($source, $this->getUser());
        $model = new CashModel;
        return $this->renderSuccess('', [
            // 提现明细列表
            'list' => $model->getList($this->user['user_id'], (int)$status,$this->postData()),
            // 页面文字
            'words' => $this->setting['words']['values'],
            'config' => $config
        ]);
    }

    /**
     * 确认提现
     */
    public function receipt($id)
    {
        $detail = CashModel::detail($id);
        if ($detail->receiptMoney()) {
            return $this->renderSuccess('确认收款成功');
        }
        return $this->renderError($detail->getError() ?: '确认收款失败');
    }

}