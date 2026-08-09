<?php

namespace app\shop\controller\plus\agent;

use app\common\model\plus\agent\Setting as AgentSetting;
use app\shop\controller\Controller;
use app\shop\model\plus\agent\Grade as AgentGradeModel;
use app\common\model\plus\agent\GradeLog as GradeLogModel;
/**
 * 会员等级
 */
class Grade extends Controller
{
    /**
     * 会员等级列表
     */
    public function index()
    {
        $model = new AgentGradeModel;
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 添加等级
     */
    public function add()
    {
        if($this->request->isGet()){
            // 平台分销规则
            $basicSetting = AgentSetting::getItem('basic');
            return $this->renderSuccess('', compact( 'basicSetting'));
        }
        $model = new AgentGradeModel;
        // 新增记录
        if ($model->add($this->postData())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError('添加失败');
    }

    /**
     * 编辑会员等级
     */
    public function edit()
    {
        if($this->request->isGet()){
            // 平台分销规则
            $basicSetting = AgentSetting::getItem('basic');
            return $this->renderSuccess('', compact( 'basicSetting'));
        }
        $grade_id = $this->postData('grade_id');
        $model = AgentGradeModel::detail($grade_id);
        // 修改记录
        if ($model->edit($this->postData())) {
            return $this->renderSuccess();
        }
        return $this->renderError();
    }

    /**
     * 删除会员等级
     */
    public function delete($grade_id)
    {
        // 会员等级详情
        $model = AgentGradeModel::detail($grade_id);
        if (!$model->setDelete()) {
            return $this->renderError('已存在用户，删除失败');
        }
        return $this->renderSuccess('删除成功');
    }

    /**
     * 分销商申请列表
     */
    public function log()
    {
        $model = new GradeLogModel;
        $list = $model->getList($this->postData());
        return $this->renderSuccess('', compact('list'));
    }
}