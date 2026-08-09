<?php

namespace app\api\controller\user;

use app\api\controller\Controller;
use app\api\model\user\Cash as CashModel;
use app\api\model\settings\Setting as SettingModel;
use app\common\model\app\App as AppModel;

/**
 * 用户余额提现
 */
class Cash extends Controller
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
     * 提现数据
     */
    public function index()
    {
        $setting = SettingModel::getItem('balance_cash');
        $cash_ratio = $setting['cash_ratio'];
        $balance = $this->user['balance'];
        $real_name = $this->user['real_name'];
        $pay_type = $setting['pay_type'];
        return $this->renderSuccess('', compact('cash_ratio', 'balance', 'real_name', 'pay_type'));
    }

    /**
     * 提交提现申请
     */
    public function submit($data = '')
    {
        $formData = $this->normalizeSubmitData($data);
        if (empty($formData)) {
            return $this->renderError('提现数据格式不正确');
        }
        $model = new CashModel;
        if ($model->submit($this->user, $formData)) {
            return $this->renderSuccess('申请提现成功');
        }
        return $this->renderError($model->getError() ?: '提交失败');
    }

    private function normalizeSubmitData($data = '')
    {
        if (is_array($data) && !empty($data)) {
            return $this->mapLegacyFields($data);
        }

        if (is_string($data) && $data !== '') {
            $formData = json_decode(htmlspecialchars_decode($data), true);
            if (is_array($formData) && !empty($formData)) {
                return $this->mapLegacyFields($formData);
            }
        }

        $rawData = $this->request->post('data', '');
        if (is_string($rawData) && $rawData !== '') {
            $formData = json_decode(htmlspecialchars_decode($rawData), true);
            if (is_array($formData) && !empty($formData)) {
                return $this->mapLegacyFields($formData);
            }
        } elseif (is_array($rawData) && !empty($rawData)) {
            return $this->mapLegacyFields($rawData);
        }

        $postData = $this->request->post();
        if (is_array($postData) && !empty($postData)) {
            return $this->mapLegacyFields($postData);
        }

        return [];
    }

    private function mapLegacyFields(array $formData)
    {
        if (!isset($formData['money']) && isset($formData['num'])) {
            $formData['money'] = $formData['num'];
        }
        if (empty($formData['source'])) {
            $formData['source'] = 'h5';
        }
        if (empty($formData['pay_type'])) {
            $formData['pay_type'] = (!empty($formData['bank']) || !empty($formData['card'])) ? 30 : 20;
        }
        if ((int)$formData['pay_type'] === 30) {
            if (empty($formData['bank_name']) && !empty($formData['bank'])) {
                $formData['bank_name'] = $formData['bank'];
            }
            if (empty($formData['bank_account']) && !empty($formData['name'])) {
                $formData['bank_account'] = $formData['name'];
            }
            if (empty($formData['bank_card']) && !empty($formData['card'])) {
                $formData['bank_card'] = $formData['card'];
            }
        } elseif ((int)$formData['pay_type'] === 20) {
            if (empty($formData['alipay_name']) && !empty($formData['name'])) {
                $formData['alipay_name'] = $formData['name'];
            }
            if (empty($formData['alipay_account']) && !empty($formData['card'])) {
                $formData['alipay_account'] = $formData['card'];
            }
        }
        return $formData;
    }

    /**
     * 余额提现明细
     */
    public function lists($status = -1, $source = '')
    {
        $config = (new AppModel())->getCashInfo($source, $this->getUser());
        $model = new CashModel;
        return $this->renderSuccess('', [
            'list' => $model->getList($this->user['user_id'], (int)$status, $this->postData()),
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