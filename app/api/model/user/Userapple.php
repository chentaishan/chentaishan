<?php

namespace app\api\model\user;

use think\facade\Cache;
use app\common\exception\BaseException;
use app\common\model\user\User as UserModel;
use app\api\model\plus\agent\Referee as RefereeModel;
use app\common\model\user\Grade as GradeModel;

/**
 * 用户模型类
 */
class Userapple extends UserModel
{
    private $token;

    /**
     * 隐藏字段
     */
    protected $hidden = [
        'open_id',
        'is_delete',
        'app_id',
        'create_time',
        'update_time'
    ];


    /**
     * 用户登录
     */
    public function login($post)
    {
        // 自动注册用户
        $refereeId = isset($post['referee_id']) ? $post['referee_id'] : 0;
        $user_id = $this->register($post, $refereeId);
        // 生成token (session3rd)
        $this->token = $this->token($post['openId']);
        // 记录缓存, 7天
        Cache::tag('cache')->set($this->token, $user_id, 86400 * 7);
        return $user_id;
    }

    /**
     * 获取token
     */
    public function getToken()
    {
        return $this->token;
    }


    /**
     * 生成用户认证的token
     */
    private function token($openid)
    {
        $app_id = self::$app_id;
        // 生成一个不会重复的随机字符串
        $guid = \getGuidV4();
        // 当前时间戳 (精确到毫秒)
        $timeStamp = microtime(true);
        // 自定义一个盐
        $salt = 'token_salt';
        return md5("{$app_id}_{$timeStamp}_{$openid}_{$guid}_{$salt}");
    }

    /**
     * 自动注册用户
     */
    private function register($data, $refereeId)
    {
        //通过unionid查询用户是否存在
        $user = null;
        if (!$user) {
            // 通过open_id查询用户是否已存在
            $user = self::detail(['app_user' => $data['openId']]);
        }
        if ($user) {
            $model = $user;
        } else {
            $model = $this;
            $data['referee_id'] = $refereeId;
            $data['reg_source'] = 'apple';
            //默认等级
            $data['grade_id'] = GradeModel::getDefaultGradeId();
        }
        $this->startTrans();
        try {
            $saveData = array_merge($data, [
                'app_user' => $data['openId'],
                'app_id' => self::$app_id
            ]);
            if (!$user) {
                $saveData = array_merge($saveData, UserModel::defaultPayPasswordData());
            }
            // 保存/更新用户记录
            if (!$model->save($saveData))
            ) {
                throw new BaseException(['msg' => '用户注册失败']);
            }
            if (!$user) {
                //注册之后关系绑定
                $this->saveRelation($model, $refereeId);
            }
            $this->commit();
            return $model['user_id'];
        } catch (\Exception $e) {
            $this->rollback();
            throw new BaseException(['msg' => $e->getMessage()]);
        }
    }
}
