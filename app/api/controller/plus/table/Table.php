<?php

namespace app\api\controller\plus\table;

use app\api\controller\Controller;
use app\api\model\plus\table\Table as TableModel;
use app\common\model\settings\Region as RegionModel;

/**
 * 表单填写控制器
 */
class Table extends Controller
{
    /**
     * 添加用户签到
     */
    public function add($table_id)
    {
        $user = $this->getUser();
        $model = TableModel::detail($table_id);
        if($this->request->isGet()){
            $regionData = RegionModel::getRegionForApi();
            return $this->renderSuccess('', compact('model', 'regionData'));
        }
        if ($model->add($user, $this->postData())) {
            return $this->renderSuccess('');
        }
        return $this->renderError($model->getError() ?: '提交失败');
    }

}