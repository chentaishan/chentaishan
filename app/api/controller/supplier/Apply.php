<?php

namespace app\api\controller\supplier;

use app\api\controller\Controller;
use app\api\model\supplier\Apply as ApplyModel;
use app\common\model\settings\Setting;
use app\api\model\supplier\Category as CategoryModel;
use app\common\model\user\Sms as SmsModel;

/**
 * 商户申请
 */
class Apply extends Controller
{
    protected $user;

    /**
     * 构造方法
     */
    public function initialize()
    {
        $this->user = $this->getUser();
    }

    //店铺分类
    public function category()
    {
        $list = CategoryModel::getALL();
        //是否需要短信验证
        $sms_open = Setting::getItem('store')['sms_open'];
        return $this->renderSuccess('', compact('list', 'sms_open'));
    }

    /**
     * 申请开店
     */
    public function index()
    {
        $data = $this->request->post();
        $model = new ApplyModel;
        // 新增记录
        if ($model->add($data,$this->user)) {
            return $this->renderSuccess('申请成功，请等待平台审核', []);
        }
        return $this->renderError($model->getError() ?: '申请失败');
    }

    //获取申请状态
    public function detail()
    {
        $detail = ApplyModel::getLastDetail($this->user['user_id']);
        return $this->renderSuccess('', compact('detail'));
    }

    /**
     * 发送短信
     */
    public function sendCode($mobile)
    {
        $model = new SmsModel();
        if ($model->send($mobile, 'apply')) {
            return $this->renderSuccess();
        }
        return $this->renderError($model->getError() ?: '发送失败');
    }


}