<?php

namespace app\shop\controller\plus\assemble;

use app\shop\controller\Controller;
use app\shop\model\settings\Setting as SettingModel;

/**
 * 整点秒杀设置
 */
class Setting extends Controller
{
    /**
     *获取拼团设置
     */
    public function getSetting()
    {
        $vars['values'] = SettingModel::getItem('assemble');
        return $this->renderSuccess('', compact('vars'));
    }

    /**
     * 拼团设置
     */
    public function index()
    {
        if($this->request->isGet()){
            return $this->getSetting();
        }
        $model = new SettingModel;
        $data = $this->request->param();
        if ($model->edit('assemble', $data)) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError('操作失败');
    }
}