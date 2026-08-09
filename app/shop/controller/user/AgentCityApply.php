<?php

namespace app\shop\controller\user;

use app\common\service\activity\AgentRegionService;
use app\shop\controller\Controller;
use app\shop\model\user\User as UserModel;
use think\facade\Db;

/**
 * 区域代理申请审核（省/市/区代）
 */
class AgentCityApply extends Controller
{
    /**
     * 申请列表
     */
    public function index()
    {
        $params = $this->request->param();
        $query = Db::name('city_agent_apply')->alias('a')
            ->leftJoin('user u', 'u.user_id = a.user_id')
            ->where('a.is_delete', '=', 0)
            ->field([
                'a.apply_id', 'a.user_id', 'a.agent_type',
                'a.agent_province_id', 'a.agent_city_id', 'a.agent_district_id',
                'a.status', 'a.remark', 'a.audit_remark', 'a.create_time', 'a.audit_time',
                'u.nickName', 'u.mobile',
            ]);

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('a.status', '=', (int)$params['status']);
        }
        if (isset($params['agent_type']) && $params['agent_type'] !== '') {
            $query->where('a.agent_type', '=', (int)$params['agent_type']);
        }
        if (!empty($params['keyword'])) {
            $keyword = trim((string)$params['keyword']);
            $query->where('u.user_id|u.nickName|u.mobile', 'like', '%' . $keyword . '%');
        }

        $list = $query->order('a.apply_id', 'desc')->paginate($params)->each(function ($item) {
            return AgentRegionService::formatApplyRow($item);
        });

        return $this->renderSuccess('', compact('list'));
    }

    /**
     * 申请详情
     */
    public function detail($apply_id)
    {
        $model = Db::name('city_agent_apply')->alias('a')
            ->leftJoin('user u', 'u.user_id = a.user_id')
            ->where('a.apply_id', '=', (int)$apply_id)
            ->where('a.is_delete', '=', 0)
            ->field([
                'a.apply_id', 'a.user_id', 'a.agent_type',
                'a.agent_province_id', 'a.agent_city_id', 'a.agent_district_id',
                'a.status', 'a.remark', 'a.audit_remark', 'a.create_time', 'a.audit_time',
                'u.nickName', 'u.mobile', 'u.agent_province_id as user_agent_province_id',
                'u.agent_city_id as user_agent_city_id', 'u.agent_district_id as user_agent_district_id',
                'u.is_store',
            ])
            ->find();
        if (!$model) {
            return $this->renderError('申请记录不存在');
        }
        $model = AgentRegionService::formatApplyRow($model);
        return $this->renderSuccess('', compact('model'));
    }

    /**
     * 审核
     * POST: apply_id,status(20通过|30拒绝),audit_remark
     */
    public function audit()
    {
        $applyId     = (int)$this->request->post('apply_id', 0);
        $status      = (int)$this->request->post('status', 0);
        $auditRemark = trim((string)$this->request->post('audit_remark', ''));

        if ($applyId <= 0) {
            return $this->renderError('缺少申请ID');
        }
        if (!in_array($status, [AgentRegionService::STATUS_APPROVED, AgentRegionService::STATUS_REJECTED], true)) {
            return $this->renderError('审核状态参数错误');
        }
        if ($status === AgentRegionService::STATUS_REJECTED && $auditRemark === '') {
            return $this->renderError('拒绝时请填写审核备注');
        }

        $apply = Db::name('city_agent_apply')
            ->where('apply_id', '=', $applyId)
            ->where('is_delete', '=', 0)
            ->find();
        if (!$apply) {
            return $this->renderError('申请记录不存在');
        }
        if ((int)$apply['status'] !== AgentRegionService::STATUS_PENDING) {
            return $this->renderError('该申请已处理，请勿重复审核');
        }

        Db::startTrans();
        try {
            if ($status === AgentRegionService::STATUS_APPROVED) {
                $region = [
                    'agent_type'         => (int)($apply['agent_type'] ?: AgentRegionService::resolveAgentType(
                        (int)$apply['agent_province_id'],
                        (int)$apply['agent_city_id'],
                        (int)($apply['agent_district_id'] ?? 0)
                    )),
                    'agent_province_id'  => (int)$apply['agent_province_id'],
                    'agent_city_id'      => (int)$apply['agent_city_id'],
                    'agent_district_id'  => (int)($apply['agent_district_id'] ?? 0),
                ];
                AgentRegionService::assertRegionAvailable($region, (int)$apply['user_id']);

                $updateUser = AgentRegionService::buildUserAgentUpdate($region);
                Db::name('user')->where('user_id', '=', (int)$apply['user_id'])->update($updateUser);
            }

            Db::name('city_agent_apply')->where('apply_id', '=', $applyId)->update([
                'status'       => $status,
                'audit_remark' => $auditRemark,
                'audit_time'   => time(),
                'update_time'  => time(),
            ]);

            Db::commit();
            return $this->renderSuccess($status === AgentRegionService::STATUS_APPROVED ? '审核通过' : '已拒绝');
        } catch (\InvalidArgumentException $e) {
            Db::rollback();
            return $this->renderError($e->getMessage());
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->renderError($e->getMessage() ?: '审核失败');
        }
    }
}
