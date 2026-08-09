<?php

namespace app\api\controller\user;

use app\api\controller\Controller;
use app\api\model\user\UserMp as UserMpModel;
use app\common\library\easywechat\AppMp;

/**
 * 公众号用户管理
 */
class Usermp extends Controller
{

    /**
     * 用户自动登录
     */
    public function login($referee_id = 0)
    {
        $app = AppMp::getApp($this->app_id, $referee_id);
        $oauth = $app->getOAuth();
        //生成完整的授权URL
        $redirectUrl = $oauth->scopes(['snsapi_userinfo'])->redirect();
        return redirect($redirectUrl);
    }

    /**
     * 用户自动登录
     */
    public function login_callback()
    {
        $app = AppMp::getApp($this->app_id);
        $oauth = $app->getOauth();
        // 获取 OAuth 授权用户信息
        $user = $oauth->userFromCode($this->request->param('code'));
        $userInfo = $user->toArray();
        $model = new UserMpModel;
        $referee_id = $this->request->param('referee_id');
        $user_id = $model->login($userInfo, $referee_id);
        return redirect(base_url() . 'h5/pages/login/mplogin?app_id=' . $this->app_id . '&token=' . $model->getToken() . '&user_id=' . $user_id);
    }

        /**
     * 用户自动登录
     */
    public function login_new_callback()
    {

        $app = AppMp::getApp($this->app_id);
        $oauth = $app->getOauth();
        // 获取 OAuth 授权用户信息
        //dump(11);

        log_write('授权登录');
        $user = $oauth->userFromCode($this->request->param('code'));
        log_write('授权登录');
        log_write($user);
        //exit;
        $user_id = $this->request->param('user_id');
        //dump(22);
        // if($user_id == 373){
        //     return $this->renderError($user_id.$this->request->param('code'));
        // }

        $userInfo = $user->toArray();

        $model = new UserMpModel;

        // $userInfo = $userInfo['raw'];
        $open = $userInfo['token_response']['openid'] ?? '';
        log_write('openid');
        log_write($open);
        //dump(33);
        // 生成token (session3rd)
        //$this->token = $this->token($userInfo['openid']);
        $users = \app\api\model\user\User::where('user_id',$user_id)->find();
        if($users && $users['mpopen_id'] == ''){
            $res = \app\api\model\user\User::where('user_id',$user_id)->update(['mpopen_id'=>$open]);
            log_write('保存');
            log_write($res);
        }

        //dump(44);
        if($res){
            return $this->renderSuccess('成功');
        }
        //return $this->renderError('失败');
        // $referee_id = $this->request->param('referee_id');
        // // $code = $this->request->param('gen_code');

        // // $referee_id = 0;
        // // if($code){
        // //     $referee_id = UserCodeService::decode($code) ?? 0;
        // // }
        // $user_id = $model->login($userInfo, $referee_id);
        // return redirect(base_url() . 'h5/pages/login/mplogin?app_id=' . $this->app_id . '&token=' . $model->getToken() . '&user_id=' . $user_id);
    }
}
