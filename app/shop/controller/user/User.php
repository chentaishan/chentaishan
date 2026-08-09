<?php

namespace app\shop\controller\user;

use app\common\library\helper;
use app\common\model\order\CloudBonusRecord;
use app\common\model\user\Tag as TagModel;
use app\common\model\user\UserTag as UserTagModel;
use app\shop\controller\Controller;
use app\shop\model\user\Grade;
use app\shop\model\user\User as UserModel;
use app\common\service\activity\ActivityRewardService;
use app\common\service\activity\EnergyRewardService;
use app\common\model\user\BalanceLog as BalanceLogModel;
use app\common\model\user\WithdrawalRecord;
use app\common\enum\user\balanceLog\BalanceLogSceneEnum;
use think\facade\Db;
use think\facade\Log;

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
            $energyUnlockedOrders = $this->retryEnergyUnlockAfterRefereeChange((int)$user_id);
            return $this->renderSuccess('绑定成功', [
                'energy_unlock_retried_orders' => $energyUnlockedOrders,
            ]);
        }
        return $this->renderError($model->getError() ?: '绑定失败');
    }

    /**
     * 查询会员推荐人信息
     */
    public function getReferee($user_id)
    {
        $userId = (int)$user_id;
        if ($userId <= 0) {
            return $this->renderError('缺少用户ID');
        }

        $user = Db::name('user')
            ->field('user_id,nickName,mobile,referee_id,app_id')
            ->where('user_id', '=', $userId)
            ->find();
        if (!$user) {
            return $this->renderError('用户不存在');
        }

        $referee = null;
        if ((int)$user['referee_id'] > 0) {
            $referee = Db::name('user')
                ->field('user_id,nickName,mobile')
                ->where('user_id', '=', (int)$user['referee_id'])
                ->find();
        }

        return $this->renderSuccess('', [
            'user_id' => (int)$user['user_id'],
            'nickName' => (string)$user['nickName'],
            'mobile' => (string)$user['mobile'],
            'referee_id' => (int)$user['referee_id'],
            'referee' => $referee,
        ]);
    }

    /**
     * 修改会员推荐人（按推荐人用户ID或手机号）
     */
    public function setReferee()
    {
        $userId = (int)$this->request->post('user_id', 0);
        $refereeUserId = (int)$this->request->post('referee_user_id', 0);
        $refereePhone = trim((string)$this->request->post('referee_phone', ''));

        if ($userId <= 0) {
            return $this->renderError('缺少用户ID');
        }
        if ($refereeUserId <= 0 && $refereePhone === '') {
            return $this->renderError('请传推荐人ID或手机号');
        }
        Log::info('[setReferee] 请求开始', [
            'user_id' => $userId,
            'referee_user_id' => $refereeUserId,
            'referee_phone' => $refereePhone,
            'shop_user_id' => $this->store['user']['shop_user_id'] ?? 0,
        ]);

        $user = Db::name('user')
            ->field('user_id,app_id,referee_id')
            ->where('user_id', '=', $userId)
            ->find();
        if (!$user) {
            return $this->renderError('用户不存在');
        }

        if ($refereeUserId <= 0 && $refereePhone !== '') {
            $refereeUserId = (int)Db::name('user')
                ->where('mobile', '=', $refereePhone)
                ->where('is_delete', '=', 0)
                ->value('user_id');
        }
        if ($refereeUserId <= 0) {
            return $this->renderError('推荐人不存在');
        }
        if ($refereeUserId === $userId) {
            return $this->renderError('推荐人不能是自己');
        }

        $referee = Db::name('user')
            ->field('user_id,app_id,referee_id,nickName,mobile')
            ->where('user_id', '=', $refereeUserId)
            ->where('is_delete', '=', 0)
            ->find();
        if (!$referee) {
            return $this->renderError('推荐人不存在');
        }
        if ((int)$referee['app_id'] !== (int)$user['app_id']) {
            return $this->renderError('推荐人应用不匹配');
        }

        // 防循环：新推荐人的上级链路中不能出现当前用户
        if ($this->hasRefereeCycle($userId, $refereeUserId)) {
            return $this->renderError('推荐关系存在循环风险，禁止修改');
        }

        $oldRefereeId = (int)($user['referee_id'] ?? 0);
        $result = Db::name('user')
            ->where('user_id', '=', $userId)
            ->update([
                'referee_id' => $refereeUserId,
                'update_time' => time(),
            ]);

        if ($result === false) {
            Log::error('[setReferee] 数据库更新失败', ['user_id' => $userId, 'referee_user_id' => $refereeUserId]);
            return $this->renderError('修改推荐人失败');
        }
        Log::info('[setReferee] 数据库更新成功', [
            'user_id' => $userId,
            'old_referee_id' => $oldRefereeId,
            'new_referee_id' => $refereeUserId,
        ]);

        $energyUnlockedOrders = $this->retryEnergyUnlockAfterRefereeChange($userId);

        return $this->renderSuccess('修改推荐人成功', [
            'user_id' => $userId,
            'referee_id' => $refereeUserId,
            'referee_nickName' => (string)$referee['nickName'],
            'referee_mobile' => (string)$referee['mobile'],
            'energy_unlock_retried_orders' => $energyUnlockedOrders,
        ]);
    }

    /**
     * 推荐关系变更后，补跑买家历史已支付优品区订单的上级能量解锁
     */
    private function retryEnergyUnlockAfterRefereeChange(int $userId): int
    {
        try {
            return (new EnergyRewardService())->retryUnlockForBuyerPaidOrders($userId);
        } catch (\Throwable $e) {
            Log::warning('[setReferee|bound] 能量解锁补跑异常', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * 检查推荐关系是否会形成循环
     */
    private function hasRefereeCycle($userId, $newRefereeId)
    {
        $visited = [];
        $current = (int)$newRefereeId;
        $depth = 0;
        while ($current > 0 && $depth < 200) {
            if ($current === (int)$userId) {
                return true;
            }
            if (isset($visited[$current])) {
                return true;
            }
            $visited[$current] = 1;
            $next = (int)Db::name('user')->where('user_id', '=', $current)->value('referee_id');
            if ($next <= 0) {
                break;
            }
            $current = $next;
            $depth++;
        }
        return false;
    }

    /**
     * 设置是否门店（与区域代理互斥；设为门店时会清空 agent_*_id）
     * POST: user_id, is_store（0否 1是）
     */
    public function setIsStore()
    {
        $userId  = (int)$this->request->post('user_id', 0);
        $isStore = (int)$this->request->post('is_store', -1);

        if ($userId <= 0) {
            return $this->renderError('缺少用户ID');
        }
        if (!in_array($isStore, [0, 1], true)) {
            return $this->renderError('is_store 参数错误，应为 0 或 1');
        }
        if (!UserModel::hasColumn('user', 'is_store')) {
            return $this->renderError('database/20260522_user_is_store.sql');
        }

        $user = Db::name('user')->where('user_id', '=', $userId)->where('is_delete', '=', 0)->find();
        if (!$user) {
            return $this->renderError('用户不存在');
        }

        if ($isStore === 1 && \app\common\service\activity\WzUserIdentityService::hasAgentRegion($user)) {
            return $this->renderError('该用户已是区域代理，请先清空代理区域后再设为门店');
        }

        $update = [
            'is_store'    => $isStore,
            'update_time' => time(),
        ];
        if ($isStore === 1) {
            $update['agent_province_id'] = 0;
            $update['agent_city_id']     = 0;
            $update['agent_district_id'] = 0;
        }

        $ok = Db::name('user')->where('user_id', '=', $userId)->update($update);
        if ($ok === false) {
            return $this->renderError('保存失败');
        }
        return $this->renderSuccess($isStore === 1 ? '已设为门店' : '已取消门店身份');
    }

    /**
     * 设置是否合伙人（可单独设置，与代理/门店无互斥）
     * POST: user_id, is_partner（0否 1是）
     */
    public function setIsPartner()
    {
        $userId    = (int)$this->request->post('user_id', 0);
        $isPartner = (int)$this->request->post('is_partner', -1);

        if ($userId <= 0) {
            return $this->renderError('缺少用户ID');
        }
        if (!in_array($isPartner, [0, 1], true)) {
            return $this->renderError('is_partner 参数错误，应为 0 或 1');
        }
        if (!UserModel::hasColumn('user', 'is_partner')) {
            return $this->renderError('database/20260602_user_is_partner.sql');
        }

        $user = Db::name('user')->where('user_id', '=', $userId)->where('is_delete', '=', 0)->find();
        if (!$user) {
            return $this->renderError('用户不存在');
        }

        $ok = Db::name('user')->where('user_id', '=', $userId)->update([
            'is_partner'  => $isPartner,
            'update_time' => time(),
        ]);
        if ($ok === false) {
            return $this->renderError('保存失败');
        }
        return $this->renderSuccess($isPartner === 1 ? '已设为合伙人' : '已取消合伙人身份');
    }

    /**
     * 福满堂：设置会员是否开启帕点奖
     * 优品区下单支付成功后，从买家自身沿推荐人链向上查找第一个开启帕点奖的人员发放奖励
     * POST: user_id, pa_point_reward（0否 1是）
     */
    public function setPaPointReward()
    {
        $userId        = (int)$this->request->post('user_id', 0);
        $paPointReward = (int)$this->request->post('pa_point_reward', -1);

        if ($userId <= 0) {
            return $this->renderError('缺少用户ID');
        }
        if (!in_array($paPointReward, [0, 1], true)) {
            return $this->renderError('pa_point_reward 参数错误，应为 0 或 1');
        }
        if (!UserModel::hasColumn('user', 'pa_point_reward')) {
            return $this->renderError('请先执行 database/20260625_hekangyuan_pa_point.sql');
        }

        $user = Db::name('user')->where('user_id', '=', $userId)->where('is_delete', '=', 0)->find();
        if (!$user) {
            return $this->renderError('用户不存在');
        }

        $ok = Db::name('user')->where('user_id', '=', $userId)->update([
            'pa_point_reward' => $paPointReward,
            'update_time'     => time(),
        ]);
        if ($ok === false) {
            return $this->renderError('保存失败');
        }
        return $this->renderSuccess($paPointReward === 1 ? '已开启帕点奖' : '已关闭帕点奖');
    }

    /**
     * 福满堂：设置会员权益身份（消费券池分配档位）
     * POST: user_id, hky_identity（0大众 1白名单 2黑名单）
     */
    public function setIdentity()
    {
        $userId   = (int)$this->request->post('user_id', 0);
        $identity = (int)$this->request->post('hky_identity', -1);

        if ($userId <= 0) {
            return $this->renderError('缺少用户ID');
        }
        if (!in_array($identity, [0, 1, 2], true)) {
            return $this->renderError('hky_identity 参数错误，应为 0大众/1白名单/2黑名单');
        }
        if (!UserModel::hasColumn('user', 'hky_identity')) {
            return $this->renderError('请先执行 database/20260618_hekangyuan_voucher.sql');
        }

        $user = Db::name('user')->where('user_id', '=', $userId)->where('is_delete', '=', 0)->find();
        if (!$user) {
            return $this->renderError('用户不存在');
        }

        $ok = Db::name('user')->where('user_id', '=', $userId)->update([
            'hky_identity' => $identity,
            'update_time'  => time(),
        ]);
        if ($ok === false) {
            return $this->renderError('保存失败');
        }
        $text = [0 => '大众', 1 => '白名单', 2 => '黑名单'][$identity];
        return $this->renderSuccess('已设为' . $text);
    }

    /**
     * 福满堂：设置用户消费券全平台互转开关
     * POST: user_id, voucher_free_transfer（0仅同直推线 1可任意互转）
     */
    public function setVoucherFreeTransfer()
    {
        $userId = (int)$this->request->post('user_id', 0);
        $status = (int)$this->request->post('voucher_free_transfer', -1);

        if ($userId <= 0) {
            return $this->renderError('缺少用户ID');
        }
        if (!in_array($status, [0, 1], true)) {
            return $this->renderError('voucher_free_transfer 参数错误，应为 0 或 1');
        }
        if (!UserModel::hasColumn('user', 'voucher_free_transfer')) {
            return $this->renderError('请先执行 database/20260618_hekangyuan_voucher.sql');
        }

        $user = Db::name('user')->where('user_id', '=', $userId)->where('is_delete', '=', 0)->find();
        if (!$user) {
            return $this->renderError('用户不存在');
        }

        $ok = Db::name('user')->where('user_id', '=', $userId)->update([
            'voucher_free_transfer' => $status,
            'update_time'           => time(),
        ]);
        if ($ok === false) {
            return $this->renderError('保存失败');
        }
        return $this->renderSuccess($status === 1 ? '已开启全平台互转' : '已关闭(仅同直推线可转)');
    }

    /**
     * 设置会员代理区域（省/市/区代各唯一）
     * 省代：仅 agent_province_id，市/区为 0
     * 市代：agent_city_id + 所属省，agent_district_id=0
     * 区代：agent_city_id + agent_district_id + 所属省
     */
    public function setAgentRegion()
    {
        $userId     = (int)$this->request->post('user_id', 0);
        $cityId     = (int)$this->request->post('agent_city_id', 0);
        $districtId = (int)$this->request->post('agent_district_id', 0);
        $provinceId = (int)$this->request->post('agent_province_id', 0);

        if ($userId <= 0) {
            return $this->renderError('缺少用户ID');
        }

        $user = Db::name('user')->where('user_id', '=', $userId)->find();
        if (!$user) {
            return $this->renderError('用户不存在');
        }

        // 支持清空身份：省/市/区均未选择时清空
        if ($provinceId <= 0 && $cityId <= 0 && $districtId <= 0) {
            $result = Db::name('user')->where('user_id', '=', $userId)->update([
                'agent_province_id' => 0,
                'agent_city_id'     => 0,
                'agent_district_id' => 0,
                'update_time'       => time(),
            ]);
            if ($result !== false) {
                return $this->renderSuccess('代理区域已清空');
            }
            return $this->renderError('清空失败');
        }

        try {
            \app\common\service\activity\AgentRegionService::assertUserCanSetAgent($user);
            $region = \app\common\service\activity\AgentRegionService::validateRegionIds($provinceId, $cityId, $districtId);
            \app\common\service\activity\AgentRegionService::assertRegionAvailable($region, $userId);
        } catch (\InvalidArgumentException $e) {
            return $this->renderError($e->getMessage());
        }

        $update = \app\common\service\activity\AgentRegionService::buildUserAgentUpdate($region);
        $result = Db::name('user')->where('user_id', '=', $userId)->update($update);

        if ($result !== false) {
            return $this->renderSuccess('代理区域设置成功');
        }
        return $this->renderError('设置失败');
    }

    /**
     * 查询会员代理区域
     */
    public function getAgentRegion($user_id)
    {
        $userId = (int)$user_id;
        if ($userId <= 0) {
            return $this->renderError('缺少用户ID');
        }

        $fields = 'user_id, nickName, mobile, agent_province_id, agent_city_id, agent_district_id';
        if (UserModel::hasColumn('user', 'is_store')) {
            $fields .= ', is_store';
        }
        if (UserModel::hasColumn('user', 'is_partner')) {
            $fields .= ', is_partner';
        }
        if (UserModel::hasColumn('user', 'pa_point_reward')) {
            $fields .= ', pa_point_reward';
        }
        $user = Db::name('user')
            ->field($fields)
            ->where('user_id', '=', $userId)
            ->find();
        if (!$user) {
            return $this->renderError('用户不存在');
        }
        $user['is_store']      = (int)($user['is_store'] ?? 0);
        $user['is_store_text'] = \app\common\service\activity\WzUserIdentityService::getStoreText($user);
        $user['is_partner']      = (int)($user['is_partner'] ?? 0);
        $user['is_partner_text'] = \app\common\service\activity\WzUserIdentityService::getPartnerText($user);
        $user['pa_point_reward']      = (int)($user['pa_point_reward'] ?? 0);
        $user['pa_point_reward_text'] = $user['pa_point_reward'] === 1 ? '已开启帕点奖' : '未开启帕点奖';
        $user['can_access_agent_stock'] = \app\common\service\activity\WzUserIdentityService::canAccessAgentStockZone($user);

        $user['agent_province_name'] = '';
        $user['agent_city_name'] = '';
        $user['agent_district_name'] = '';

        if ($user['agent_province_id'] > 0) {
            $user['agent_province_name'] = (string)Db::name('region')->where('id', '=', $user['agent_province_id'])->value('name');
        }
        if ($user['agent_city_id'] > 0) {
            $user['agent_city_name'] = (string)Db::name('region')->where('id', '=', $user['agent_city_id'])->value('name');
        }
        if ($user['agent_district_id'] > 0) {
            $user['agent_district_name'] = (string)Db::name('region')->where('id', '=', $user['agent_district_id'])->value('name');
        }
        $user['agent_level_text'] = \app\common\service\activity\WzUserIdentityService::getAgentLevelText($user);

        return $this->renderSuccess('', $user);
    }



    /**
     * 奖励明细
     */
    public function rewardDetail()
    {
        $params = $this->request->param();
        $appId = (int)$this->store['app']['app_id'];

        $query = Db::name('activity_reward_log')
            ->alias('log')
            ->leftJoin('user user', 'user.user_id = log.user_id')
            ->leftJoin('user from_user', 'from_user.user_id = log.from_user_id')
            ->where('log.app_id', '=', $appId)
            ->field([
                'log.log_id',
                'log.user_id',
                'log.from_user_id',
                'log.order_id',
                'log.zone_type',
                'log.scene',
                'log.asset_type',
                'log.amount',
                'log.reward_month',
                'log.status',
                'log.release_balance',
                'log.release_points',
                'log.release_time',
                'log.remark',
                'log.create_time',
                'user.nickName',
                'user.mobile',
                'from_user.nickName as from_nickName',
                'from_user.mobile as from_mobile',
            ]);

        if (!empty($params['user_id'])) {
            $query->where('log.user_id', '=', (int)$params['user_id']);
        }
        if (isset($params['zone_type']) && $params['zone_type'] !== '') {
            $query->where('log.zone_type', '=', (int)$params['zone_type']);
        }
        if (!empty($params['scene'])) {
            $query->where('log.scene', '=', trim($params['scene']));
        }
        if (!empty($params['reward_month'])) {
            $query->where('log.reward_month', '=', trim($params['reward_month']));
        }
        if (!empty($params['keyword'])) {
            $keyword = trim((string)$params['keyword']);
            $query->where('log.user_id|user.nickName|user.mobile', 'like', '%' . $keyword . '%');
        }
        if (!empty($params['date']) && is_array($params['date'])) {
            $query->whereTime('log.create_time', 'between', [$params['date'][0], $params['date'][1] . ' 23:59:59']);
        }

        // 商品区域: 1=优品区 2=消费区 3=代理进货区
        $zoneMap = [1 => '品牌优选区', 2 => '惠民区', 3 => '代理进货区', 4 => '合伙人'];
        $list = $query->order(['log.log_id' => 'desc'])->paginate($params)->each(function ($item) use ($zoneMap) {
            $item['zone_type_text'] = $zoneMap[(int)$item['zone_type']] ?? '-';
            return $item;
        });

        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 公司沉淀明细
     */
    public function companySinkDetail()
    {
        $params = $this->request->param();
        $appId = (int)$this->store['app']['app_id'];
        $prefix = config('database.connections.mysql.prefix');
        $tableName = $prefix . 'company_sink_daily_log';
        $tableExists = Db::query("SHOW TABLES LIKE '{$tableName}'");
        if (empty($tableExists)) {
            return $this->renderError('公司沉淀明细表不存在');
        }

        $query = Db::name('company_sink_daily_log')
            ->alias('log')
            ->leftJoin('user user', 'user.user_id = log.user_id')
            ->where('log.app_id', '=', $appId)
            ->field([
                'log.log_id',
                'log.stat_date',
                'log.user_id',
                'log.line_no',
                'log.entry_type',
                'log.scene',
                'log.scene_name',
                'log.change_amount',
                'log.source_amount',
                'log.running_amount',
                'log.remark',
                'log.create_time',
                'user.nickName',
                'user.mobile',
            ]);
        if ($this->columnExists('company_sink_daily_log', 'before_amount')) {
            $query->field(['log.before_amount']);
        }

        if (!empty($params['user_id'])) {
            $query->where('log.user_id', '=', (int)$params['user_id']);
        }
        if (!empty($params['stat_date'])) {
            $query->where('log.stat_date', '=', (int)$params['stat_date']);
        }
        if (!empty($params['entry_type'])) {
            $query->where('log.entry_type', '=', trim((string)$params['entry_type']));
        }
        if (!empty($params['scene'])) {
            $query->where('log.scene', '=', trim((string)$params['scene']));
        }
        if (!empty($params['keyword'])) {
            $keyword = trim((string)$params['keyword']);
            $query->where('log.scene|log.scene_name|log.remark|user.nickName|user.mobile', 'like', '%' . $keyword . '%');
        }
        if (!empty($params['date']) && is_array($params['date'])) {
            $start = str_replace('-', '', (string)$params['date'][0]);
            $end = str_replace('-', '', (string)$params['date'][1]);
            if (ctype_digit($start) && ctype_digit($end)) {
                $query->whereBetween('log.stat_date', [(int)$start, (int)$end]);
            }
        }

        $list = $query->order(['log.stat_date' => 'desc', 'log.line_no' => 'asc', 'log.log_id' => 'desc'])
            ->paginate($params)
            ->each(function ($item) {
                $item['entry_type_text'] = (string)$item['entry_type'] === 'order_total' ? '当日订单入账' : '奖励类型扣减';
                return $item;
            });

        $statUserId = !empty($params['user_id']) ? (int)$params['user_id'] : 12;
        $latestRunning = Db::name('company_sink_daily_log')
            ->where('app_id', '=', $appId)
            ->where('user_id', '=', $statUserId)
            ->order('stat_date', 'desc')
            ->order('line_no', 'desc')
            ->value('running_amount');

        $summary = [
            'stat_user_id' => $statUserId,
            'current_balance' => (float)Db::name('user')->where('user_id', '=', $statUserId)->value('balance'),
            'latest_running_amount' => (float)($latestRunning === null ? 0 : $latestRunning),
            'total_in' => (float)Db::name('company_sink_daily_log')
                ->where('app_id', '=', $appId)
                ->where('user_id', '=', $statUserId)
                ->where('change_amount', '>', 0)
                ->sum('change_amount'),
            'total_out' => (float)Db::name('company_sink_daily_log')
                ->where('app_id', '=', $appId)
                ->where('user_id', '=', $statUserId)
                ->where('change_amount', '<', 0)
                ->sum('change_amount'),
        ];

        return $this->renderSuccess('', compact('list', 'summary'));
    }

    private function columnExists($table, $column)
    {
        try {
            $columns = array_column(
                Db::query('SHOW COLUMNS FROM `' . config('database.connections.mysql.prefix') . $table . '`'),
                'Field'
            );
            return in_array($column, $columns, true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 提现审核列表
     */
    public function withdrawalList()
    {
        $params = $this->request->param();
        $appId = (int)$this->store['app']['app_id'];

        $query = Db::name('withdrawal_record')
            ->alias('wr')
            ->leftJoin('user user', 'user.user_id = wr.user_id')
            ->where('wr.app_id', '=', $appId)
            ->field([
                'wr.record_id',
                'wr.user_id',
                'wr.amount',
                'wr.fee',
                'wr.actual_amount',
                'wr.withdraw_type',
                'wr.status',
                'wr.remark',
                'wr.create_time',
                'wr.update_time',
                'user.nickName',
                'user.mobile',
            ]);

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('wr.status', '=', (int)$params['status']);
        }
        if (!empty($params['withdraw_type'])) {
            $query->where('wr.withdraw_type', '=', (int)$params['withdraw_type']);
        }
        if (!empty($params['keyword'])) {
            $keyword = trim((string)$params['keyword']);
            $query->where('wr.user_id|user.nickName|user.mobile', 'like', '%' . $keyword . '%');
        }
        if (!empty($params['date']) && is_array($params['date'])) {
            $query->whereTime('wr.create_time', 'between', [$params['date'][0], $params['date'][1] . ' 23:59:59']);
        }

        $typeMap = [1 => '余额提现', 2 => '积分提现'];
        $statusMap = [0 => '待审核', 1 => '已通过', 2 => '已拒绝'];
        $list = $query->order(['wr.record_id' => 'desc'])->paginate($params)->each(function ($item) use ($typeMap, $statusMap) {
            $item['withdraw_type_text'] = $typeMap[(int)$item['withdraw_type']] ?? '未知';
            $item['status_text'] = $statusMap[(int)$item['status']] ?? '未知';
            return $item;
        });

        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 提现审核
     */
    public function withdrawalAudit()
    {
        $recordId = (int)$this->request->post('record_id', 0);
        $status = (int)$this->request->post('status', 0);
        $remark = trim((string)$this->request->post('remark', ''));

        if ($recordId <= 0) {
            return $this->renderError('缺少提现记录ID');
        }
        if (!in_array($status, [1, 2])) {
            return $this->renderError('审核状态参数错误');
        }

        $record = Db::name('withdrawal_record')
            ->where('record_id', '=', $recordId)
            ->find();
        if (!$record) {
            return $this->renderError('提现记录不存在');
        }
        if ((int)$record['status'] !== 0) {
            return $this->renderError('该记录已审核，不可重复操作');
        }

        Db::startTrans();
        try {
            // 更新提现记录状态
            Db::name('withdrawal_record')
                ->where('record_id', '=', $recordId)
                ->update([
                    'status' => $status,
                    'remark' => $remark,
                    'update_time' => time(),
                ]);

            // 拒绝时退回资产
            if ($status === 2) {
                $userId = (int)$record['user_id'];
                $amount = (string)$record['amount'];
                $withdrawType = (int)$record['withdraw_type'];

                if ($withdrawType === 1) {
                    // 退回余额
                    Db::name('user')->where('user_id', '=', $userId)->inc('balance', (float)$amount)->update();
                    BalanceLogModel::add(
                        BalanceLogSceneEnum::WITHDRAW_REFUND,
                        [
                            'user_id' => $userId,
                            'money' => (float)$amount,
                        ],
                        ['提现驳回退回' . $amount . '元']
                    );
                } else {
                    // 退回积分
                    $user = \app\common\model\user\User::detail($userId);
                    if ($user) {
                        $user->setIncPoints((float)$amount, '提现驳回退回' . $amount . '积分', false);
                    }
                }
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->renderError($e->getMessage() ?: '审核操作失败');
        }

        return $this->renderSuccess($status === 1 ? '审核通过' : '已拒绝并退回资产');
    }

    //分红周期列表
    public function bonusPerList()
    {
        $param = $this->request->get();
        if (!isset($param['type'])) {
            return $this->renderError('缺少参数');
        }

        $list = CloudBonusRecord::where(['type' => $param['type']])
            ->order('bonus_record_id','desc')
            ->paginate($param);

        return $this->renderSuccess('', compact('list'));
    }

    //分红周期修改
    /*public function bonusPerUp()
    {
        $param = $this->request->post();
        if (!isset($param['bonus_record_id']) || empty($param['bonus_record_id'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['write_bonus'])) {
            return $this->renderError('缺少参数');
        }

        CloudBonusRecord::where(['bonus_record_id' => $param['bonus_record_id']])->update([
            'write_bonus' => $param['write_bonus'],
        ]);

        return $this->renderSuccess('审核成功');
    }*/

    /**
     * 分红订单导出
     */
    public function bonusPerExport()
    {
        $model = new CloudBonusRecord();
        return $model->exportList($this->postData());
    }
    
    
    public function setIsClear(){
        $param = $this->request->post();
        if (!isset($param['is_clear'])) {
            return $this->renderError('缺少参数');
        }
        if (!isset($param['user_id']) || empty($param['user_id'])) {
            return $this->renderError('缺少参数');
        }
        $user =  Db::name('user')
            ->where('user_id', '=', $param['user_id'])
            ->find();
        if (!$user) {
            return $this->renderError('用户不存在');
        }
        // is_clear 0 未清算 1 已清算

        $data = [
            'is_clear' => $param['is_clear'],
        ];

        $res = Db::name('user')->where('user_id', '=', $param['user_id'])->update($data);
        if($res){
            return $this->renderSuccess('操作成功');
        }
        return $this->renderError('操作失败');
    }
    
    
    
}