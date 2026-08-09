<?php

namespace app\shop\controller\user;

use app\common\library\helper;
use app\common\model\user\Tag as TagModel;
use app\common\model\user\UserTag as UserTagModel;
use app\shop\controller\Controller;
use app\shop\model\user\Grade;
use app\shop\model\user\User as UserModel;
use think\facade\Db;

class User extends Controller
{
    public function index($nickName = '', $reg_date = '', $grade_id = null)
    {
        $list = UserModel::getList($nickName, $grade_id, $reg_date, $this->postData());
        $grade = (new Grade())->getLists();
        $allTag = TagModel::getAll();
        return $this->renderSuccess('', compact('list', 'grade', 'allTag'));
    }

    public function delete($user_id)
    {
        $model = UserModel::detail($user_id);
        if ($model && $model->setDelete()) {
            return $this->renderSuccess('删除成功');
        }
        return $this->renderError($model ? $model->getError() : '删除失败');
    }

    public function add()
    {
        $model = new UserModel();
        if ($model->add($this->request->param())) {
            return $this->renderSuccess('添加成功');
        }
        return $this->renderError($model->getError() ?: '添加失败');
    }

    public function recharge($user_id, $source)
    {
        $model = UserModel::detail($user_id);
        if ($model && $model->recharge($this->store['user']['user_name'], $source, $this->postData('params'))) {
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError($model ? $model->getError() : '操作失败');
    }

    public function edit($user_id)
    {
        $model = UserModel::detail($user_id);
        if ($this->request->isGet()) {
            return $this->renderSuccess('', compact('model'));
        }
        if ($model && $model->edit($this->postData())) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model ? $model->getError() : '修改失败');
    }

    public function tag($user_id)
    {
        if ($this->request->isGet()) {
            $user = UserModel::detail($user_id);
            $userTag = UserTagModel::getListByUser($user_id);
            $userTag = helper::getArrayColumn($userTag, 'tag_id');
            $allTag = TagModel::getAll();
            return $this->renderSuccess('', compact('user', 'userTag', 'allTag'));
        }
        $model = UserModel::detail($user_id);
        if ($model && $model->editTag($this->postData())) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model ? $model->getError() : '修改失败');
    }

    public function grade($user_id)
    {
        $model = UserModel::detail($user_id);
        if ($model && $model->updateGrade($this->postData())) {
            return $this->renderSuccess('修改成功');
        }
        return $this->renderError($model ? $model->getError() : '修改失败');
    }

    public function rewardFreeze($user_id)
    {
        $model = UserModel::detail($user_id);
        if ($model && $model->updateRewardFreeze(1)) {
            return $this->renderSuccess('冻结成功');
        }
        return $this->renderError($model ? $model->getError() : '用户不存在');
    }

    public function rewardUnfreeze($user_id)
    {
        $model = UserModel::detail($user_id);
        if ($model && $model->updateRewardFreeze(0)) {
            return $this->renderSuccess('解冻成功');
        }
        return $this->renderError($model ? $model->getError() : '用户不存在');
    }

    public function rewardStatus($user_id)
    {
        $model = UserModel::detail($user_id);
        if (!$model) {
            return $this->renderError('用户不存在');
        }
        $status = (int)$this->request->post('status', -1);
        if (!in_array($status, [0, 1], true)) {
            return $this->renderError('奖励状态参数错误');
        }
        if ($model->updateRewardFreeze($status)) {
            return $this->renderSuccess($status === 1 ? '奖励已冻结' : '奖励已恢复');
        }
        return $this->renderError($model->getError() ?: '操作失败');
    }

    public function bound($user_id, $phone)
    {
        $model = UserModel::detailByPhone($phone);
        if (!$model) {
            return $this->renderError('上级用户不存在');
        }
        $ret = Db::name('user')->where('user_id', $user_id)->update(['referee_id' => $model['user_id']]);
        if ($ret !== false) {
            return $this->renderSuccess('绑定成功');
        }
        return $this->renderError($model->getError() ?: '绑定失败');
    }
}