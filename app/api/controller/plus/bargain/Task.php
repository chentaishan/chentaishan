<?php

namespace app\api\controller\plus\bargain;

use app\api\controller\Controller;
use app\api\model\plus\bargain\Task as TaskModel;

/**
 * 砍价任务模型
 */
class Task extends Controller
{
    /**
     * 创建砍价任务
     */
    public function add($bargain_activity_id, $bargain_product_id, $bargain_product_sku_id, $product_sku_id)
    {
        // 用户信息
        $user = $this->getUser();
        // 创建砍价任务
        $model = new TaskModel;
        if (!$model->add($user['user_id'], $bargain_activity_id, $bargain_product_id, $bargain_product_sku_id, $product_sku_id)) {
            return $this->renderError($model->getError() ?: '砍价任务创建失败');
        }
        return $this->renderSuccess('', [
            'bargain_task_id' => $model['bargain_task_id']
        ]);
    }

    /**
     * 获取砍价任务详情
     */
    public function detail($bargain_task_id, $url = '')
    {
        $detail = (new TaskModel)->getTaskDetail($bargain_task_id, $this->getUser(false));
        //分享
        $share = $this->getShareParams($url, "发现了一个好物，快来帮我砍一刀吧", $detail['task']['product_name'], '/pages/plus/bargain/haggle/haggle', $detail['product']['product']['image'][0]['file_path']);
        return $this->renderSuccess('', array_merge($detail, compact('share')));
    }

    /**
     * 帮砍一刀
     */
    public function cut($bargain_task_id)
    {
        // 砍价任务详情
        $model = TaskModel::detail($bargain_task_id);
        // 砍一刀的金额
        $cut_money = $model->getCutMoney();
        // 帮砍一刀事件
        $status = $model->helpCut($this->getUser());
        if ($status == true) {
            return $this->renderSuccess('砍价成功', compact('cut_money'));
        }
        return $this->renderError($model->getError() ?: '砍价失败');
    }

}