<?php


namespace app\common\model\plus\chat;

use app\common\model\BaseModel;

/**
 * 客服用户模型
 */
class ChatUser extends BaseModel
{
    protected $pk = 'chat_user_id';
    protected $name = 'chat_user';

    /**
     * 关联应用表
     */
    public function app()
    {
        return $this->belongsTo('app\\common\\model\\app\\App', 'app_id', 'app_id');
    }

    /**
     * 关联会员表
     */
    public function user()
    {
        return $this->hasOne("app\\common\\model\\user\\User", 'user_id', 'user_id');
    }

    /**
     * 关联会员表
     */
    public function supplier()
    {
        return $this->hasOne("app\\common\\model\\supplier\\Supplier", 'shop_supplier_id', 'shop_supplier_id');
    }

    /**
     * 详情
     */
    public static function detail($where, $with = ['user', 'supplier'])
    {
        is_array($where) ? $filter = $where : $filter['chat_user_id'] = (int)$where;
        return (new static())->with($with)->where($filter)->find();
    }

    //客服列表
    public function getList($data)
    {
        $model = $this;
        if (isset($data['search']) && $data['search']) {
            $model = $model->where('user_name|user_name', 'like', '%' . $data['search'] . '%');
        }
        if (isset($data['type']) && $data['type']) {
            $model = $model->where('type', '=', $data['type']);
        }
        if (isset($data['shop_supplier_id']) && $data['shop_supplier_id']) {
            $model = $model->where('shop_supplier_id', '=', $data['shop_supplier_id']);
        }
        if (isset($data['status']) && $data['status'] > -1) {
            $model = $model->where('status', '=', $data['status']);
        }
        $list = $model->with(['user', 'supplier.logo'])
            ->where('is_delete', '=', 0)
            ->order('sort asc,create_time desc')
            ->paginate($data);
        return $list;
    }

    //添加信息
    public function add($data)
    {
        // 开启事务
        $this->startTrans();
        try {
            if ($this->checkExist($data['user_name'])) {
                $this->error = '登录账号已存在';
                return false;
            }
            if ($this->checkUserExist($data['user_id'])) {
                $this->error = '用户已绑定';
                return false;
            }
            $data['password'] = salt_hash($data['password']);
            $data['app_id'] = self::$app_id;
            $this->save($data);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            log_write($e->getMessage());
            $this->rollback();
            return false;
        }
    }

    //修改信息
    public function edit($data)
    {
        // 开启事务
        $this->startTrans();
        try {
            if ($data['user_id'] != $this['user_id']) {
                if ($this->checkUserExist($data['user_id'])) {
                    $this->error = '用户已绑定';
                    return false;
                }
            }
            if (!empty($data['password'])) {
                $data['password'] = salt_hash($data['password']);
            } else {
                unset($data['password']);
            }
            $data['app_id'] = self::$app_id;
            $this->save($data);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            log_write($e->getMessage());
            $this->rollback();
            return false;
        }
    }

    //删除客服
    public function setDelete()
    {
        return $this->save(['is_delete' => 1]);
    }

    //设置客服状态
    public function setStatus()
    {
        return $this->save(['status' => $this['status'] == 1 ? 0 : 1]);
    }


    /**
     * 验证登录账号是否重复
     */
    public function checkExist($user_name)
    {
        return !!$this->withoutGlobalScope()
            ->where('user_name', '=', $user_name)
            ->where('is_delete', '=', 0)
            ->value('chat_user_id');
    }

    /**
     * 验证会员是否已绑定
     */
    public function checkUserExist($user_id)
    {
        return !!$this->where('user_id', '=', $user_id)
            ->where('is_delete', '=', 0)
            ->value('chat_user_id');
    }

    /**
     * 客服登录信息
     */
    public function getUserLoginInfo($chat_user_id)
    {
        $detail = self::detail($chat_user_id);
        if ($detail['status'] != 1) {
            $this->error = '账号已被禁用';
            return false;
        }
        if ($detail['is_delete'] != 0) {
            $this->error = '账号已被删除';
            return false;
        }
        return $detail;

    }
}