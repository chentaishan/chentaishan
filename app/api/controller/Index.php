<?php

namespace app\api\controller;

use app\api\model\page\Page as AppPage;
use app\api\model\settings\Setting as SettingModel;
use app\common\enum\settings\SettingEnum;
use app\common\model\app\AppUpdate as AppUpdateModel;
use app\common\model\supplier\Service as ServiceModel;
use app\api\model\plus\chat\Chat as ChatModel;
use app\common\model\user\User;
use think\facade\Db;

/**
 * 页面控制器
 */
class Index extends Controller
{
    /**
     * 首页
     */
    public function index($page_id = null, $url = '')
    {
        // 页面元素
        $data = AppPage::getPageData($this->getUser(false), $page_id);
        //消息条数
        $Chat = new ChatModel;
        $data['msgNum'] = $Chat->mCount($this->getUser(false));
        $data['setting'] = array(
            'collection' => SettingModel::getItem('collection'),
            'officia' => SettingModel::getItem('officia'),
            'homepush' => SettingModel::getItem('homepush'),
        );
        // 扫一扫参数
        $data['signPackage'] = $this->getScanParams($url)['signPackage'];
        // 微信公众号分享参数
        $data['share'] = $this->getShareParams($url, $data['page']['params']['share_title'], $data['page']['params']['share_title'], '/pages/diy-page/diy-page', $data['page']['params']['share_img']);
        return $this->renderSuccess('', $data);
    }

    /**
     * 首页
     */
    public function diy($page_id = null, $url = '')
    {
        // 页面元素
        $data = AppPage::getPageData($this->getUser(false), $page_id);
        // 微信公众号分享参数
        $data['share'] = $this->getShareParams($url, $data['page']['params']['share_title'], $data['page']['params']['share_title'], '/pages/diy-page/diy-page', $data['page']['params']['share_img']);
        return $this->renderSuccess('', $data);
    }

    // 公众号客服
    public function mpService($shop_supplier_id)
    {
        $mp_service = ServiceModel::detail($shop_supplier_id);
        return $this->renderSuccess('', compact('mp_service'));
    }

    //底部导航
    public function nav()
    {
        $data['vars'] = SettingModel::getItem(SettingEnum::NAV);
        $data['theme'] = SettingModel::getItem(SettingEnum::THEME);
        $data['points_name'] = SettingModel::getPointsName();
        return $this->renderSuccess('', $data);
    }

    // app更新
    public function update($name, $version, $platform)
    {
        $result = [
            'update' => false,
            'wgtUrl' => '',
            'pkgUrl' => '',
        ];
        try {
            $model = AppUpdateModel::getLast();
            if ($platform == 'android') {
                $compare_version = $model['version_android'];
            } else {
                $compare_version = $model['version_ios'];
            }
            if ($model && str_replace('.', '', $version) < str_replace('.', '', $compare_version)) {
                $currentVersions = explode('.', $version);
                $resultVersions = explode('.', $compare_version);

                if ($currentVersions[0] < $resultVersions[0]) {
                    // 说明有大版本更新
                    $result['update'] = true;
                    $result['pkgUrl'] = $platform == 'android' ? $model['pkg_url_android'] : $model['pkg_url_ios'];
                    log_write('大版本');
                } else {
                    // 其它情况均认为是小版本更新
                    $result['update'] = true;
                    $result['wgtUrl'] = $model['wgt_url'];
                    log_write('小版本' . $result['wgtUrl']);
                }
            }
        } catch (\Exception $e) {

        }
        return $this->renderSuccess('', compact('result'));
    }

    //获取公众号消息签名
    public function getSignPackage($url = "")
    {
        // 消息签名
        $data['signPackage'] = $this->getMessageParams($url)['signPackage'];
        return $this->renderSuccess('', $data);
    }

    /**
     * 用户注册登录设置
     */
    public function loginSetting()
    {
        $settingDetail = SettingModel::getItem('store');
        $setting = [
            'name' => $settingDetail['name'],
            'h5_sms_open' => $settingDetail['h5_sms_open'],
            'wx_open' => $settingDetail['wx_open'],
            'mp_open' => $settingDetail['mp_open'],
            'login_logo' => $settingDetail['login_logo'],
            'login_desc' => $settingDetail['login_desc'],
            'wx_phone' => $settingDetail['wx_phone'],
        ];
        return $this->renderSuccess('', compact('setting'));
    }
    
    
    public function clearVoucher(){
        $user = User::where('is_clear','=',1)->column('user_id');
        dump($user);
        $res = Db::name('user_energy_record')
            ->where('user_id','in',$user)
            ->where('status','in',[1,2])
            ->update(['remain_energy'=>0,'status'=>3]);
        dump($res);

        $userRes = User::where('is_clear','=',1)->update(['voucher_pack_cap'=>0,'voucher_pack_released'=>0]);
        dump($userRes);
        exit;
    }

}
