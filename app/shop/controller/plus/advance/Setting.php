<?php

namespace app\shop\controller\plus\advance;

use app\shop\controller\Controller;
use app\shop\model\settings\Setting as SettingModel;

/**
 * 预售活动设置
 */
class Setting extends Controller
{
    /**
     *获取设置
     */
    public function getSetting()
    {
        $vars['values'] = SettingModel::getItem('advance');
        return $this->renderSuccess('', compact('vars'));
    }

    /**
     * 预售设置
     */
    public function index()
    {
        if ($this->request->isGet()) {
            return $this->getSetting();
        }
        $model = new SettingModel;
        $data = $this->request->param();
        if ($model->edit('advance', $data)) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError('操作失败');
    }
}