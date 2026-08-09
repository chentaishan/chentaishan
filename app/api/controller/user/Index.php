<?php

namespace app\api\controller\user;

use app\api\controller\Controller;
use app\api\model\page\Page as AppPage;
use app\api\model\plus\agent\Setting;
use app\api\model\order\Order as OrderModel;
use app\api\model\settings\Setting as SettingModel;
use app\api\model\plus\coupon\UserCoupon as UserCouponModel;
use app\api\model\supplier\Supplier as SupplierModel;
use app\api\model\plus\chat\Chat as ChatModel;
use app\api\model\plus\chat\ChatUser as ChatUserModel;
use think\facade\Db;

/**
 * 个人中心主页
 */
class Index extends Controller
{
    /**
     * 获取个人中心信息
     */
    public function center($url = '')
    {
        // 当前用户信息
        $user = $this->getUser();
        // 页面元素
        $page = AppPage::getCenterPageData($user);
        // 扫一扫参数
        $signPackage = $this->getScanParams($url)['signPackage'];
        return $this->renderSuccess('', [
            'page' => $page,
            'signPackage' => $signPackage
        ]);
    }

    /**
     * 获取当前用户信息
     */
    public function detail()
    {
        // 当前用户信息
        $user = $this->getUser();
        //店铺信息
        $user['is_recycle'] = $user['supplierUser'] ? SupplierModel::detail($user['supplierUser']['shop_supplier_id'])['is_recycle'] : '';
        $coupon_model = new UserCouponModel();
        $coupon = count($coupon_model->getList($user['user_id'], -1, false, false));
        // 订单总数
        $model = new OrderModel;

        // 分销商基本设置
        $setting = Setting::getItem('basic');
        // 是否开启分销功能
        $agent_open = $setting['is_open'];
        //商城设置
        $store = SettingModel::getItem('store');
        //供应商入住背景图
        $supplier_image = isset($store['supplier_image']) ? $store['supplier_image'] : '';
        // 代理区域名称
        $user['agent_province_name'] = '';
        $user['agent_city_name'] = '';
        $user['agent_district_name'] = '';
        if (!empty($user['agent_province_id'])) {
            $user['agent_province_name'] = (string)Db::name('region')->where('id', '=', $user['agent_province_id'])->value('name');
        }
        if (!empty($user['agent_city_id'])) {
            $user['agent_city_name'] = (string)Db::name('region')->where('id', '=', $user['agent_city_id'])->value('name');
        }
        if (!empty($user['agent_district_id'])) {
            $user['agent_district_name'] = (string)Db::name('region')->where('id', '=', $user['agent_district_id'])->value('name');
        }
        $user['job_grade']           = \app\common\service\activity\WzUserIdentityService::normalizeJobGrade(
            (int)($user['job_grade'] ?? 0)
        );
        $user['job_grade_text']      = \app\common\service\activity\WzUserIdentityService::getJobGradeText($user['job_grade']);
        $user['is_store']            = (int)($user['is_store'] ?? 0);
        $user['is_store_text']       = \app\common\service\activity\WzUserIdentityService::getStoreText($user);
        $user['agent_level_text']    = \app\common\service\activity\WzUserIdentityService::getAgentLevelText($user);
        $user['can_access_agent_stock'] = \app\common\service\activity\WzUserIdentityService::canAccessAgentStockZone($user);
        $user['display_grade_text']  = \app\common\service\activity\WzUserIdentityService::getIdentityDisplayText($user);
        $user['display_grade']       = $user['job_grade'];
        if (isset($user['grade']) && is_array($user['grade'])) {
            $user['grade']['name'] = $user['display_grade_text'];
        }

        // 充值功能是否开启
        $balance_setting = SettingModel::getItem('balance');
        $balance_open = intval($balance_setting['is_open']);
        return $this->renderSuccess('', [
            'coupon' => $coupon,
            'userInfo' => $user,
            'orderCount' => [
                'payment' => $model->getCount($user, 'payment'),
                'delivery' => $model->getCount($user, 'delivery'),
                'received' => $model->getCount($user, 'received'),
                'comment' => $model->getCount($user, 'comment'),
            ],
            'setting' => [
                'points_name' => SettingModel::getPointsName(),
                'agent_open' => $agent_open,
                'supplier_image' => $supplier_image,
                'balance_open' => $balance_open
            ],
            'sign' => SettingModel::getItem('sign'),
            'msgcount' => (new ChatModel)->mCount($user),
            'supplierStatus' => SupplierModel::getStatus($user),
            'chat_user_id' => (new ChatUserModel)->getChatUserId($user),
            'getPhone' => $this->isGetPhone(),
        ]);
    }

    /**
     * 当前用户设置
     */
    public function setting()
    {
        // 当前用户信息
        $user = $this->getUser();

        return $this->renderSuccess('', [
            'userInfo' => $user
        ]);
    }

    /**
     * 公众号是否绑定手机号
     */
    private function isGetPhone()
    {
        $user = $this->getUser();
        if ($user['mobile'] != '') {
            return false;
        }
        return SettingModel::getItem('store')['mp_phone'];
    }

    //获取客服图片
    public function getServiceImage()
    {
        $data = Db::name('setting')->where('key', 'kefu')->value('values');
        $img_url = json_decode($data, true)['img_url'];
        //获取当前域名
        $img_url = base_url().'uploads/'.$img_url;
        return $this->renderSuccess('', [
            'img_url' => $img_url
        ]);
    }
}