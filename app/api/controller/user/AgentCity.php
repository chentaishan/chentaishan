<?php

namespace app\api\controller\user;

use app\api\controller\Controller;
use app\common\service\activity\AgentRegionService;
use think\facade\Db;

/**
 * H5 区域代理申请（省/市/区代）
 */
class AgentCity extends Controller
{
    private $user;

    public function initialize()
    {
        parent::initialize();
        $this->user = $this->getUser();
    }

    /**
     * 提交区域代理申请
     * agent_type: 1省代 2市代 3区代（选传，不传则按区域 ID 推导；仅传省市时默认市代）
     */
    public function apply()
    {
        $agentType  = (int)$this->request->post('agent_type', 0);
        $provinceId = (int)$this->request->post('agent_province_id', 0);
        $cityId     = (int)$this->request->post('agent_city_id', 0);
        $districtId = (int)$this->request->post('agent_district_id', 0);
        $remark     = trim((string)$this->request->post('remark', ''));
        $userId     = (int)$this->user['user_id'];

        try {
            AgentRegionService::assertUserCanApply($this->user);
            $region = AgentRegionService::validateRegionIds($provinceId, $cityId, $districtId);
            if ($agentType > 0 && $agentType !== (int)$region['agent_type']) {
                return $this->renderError('代理类型与所选区域不匹配');
            }
            AgentRegionService::assertRegionAvailable($region, $userId);
        } catch (\InvalidArgumentException $e) {
            return $this->renderError($e->getMessage());
        }

        if (AgentRegionService::hasUserPendingApply($userId)) {
            return $this->renderError('您已有待审核申请，请勿重复提交');
        }

        $now = time();
        Db::name('city_agent_apply')->insert([
            'user_id'            => $userId,
            'agent_type'         => (int)$region['agent_type'],
            'agent_province_id'  => (int)$region['agent_province_id'],
            'agent_city_id'      => (int)$region['agent_city_id'],
            'agent_district_id'  => (int)$region['agent_district_id'],
            'status'             => AgentRegionService::STATUS_PENDING,
            'remark'             => $remark,
            'audit_remark'       => '',
            'audit_time'         => 0,
            'app_id'             => (int)($this->app_id ?? 0),
            'create_time'        => $now,
            'update_time'        => $now,
            'is_delete'          => 0,
        ]);

        return $this->renderSuccess('申请提交成功，请等待审核');
    }

    /**
     * 我的申请状态
     */
    public function status()
    {
        $userId = (int)$this->user['user_id'];
        $row = Db::name('city_agent_apply')
            ->where('user_id', '=', $userId)
            ->where('is_delete', '=', 0)
            ->order('apply_id', 'desc')
            ->find();

        if (!$row) {
            return $this->renderSuccess('', [
                'has_apply' => 0,
                'status'    => 0,
                'status_text' => '未申请',
            ]);
        }

        $data = AgentRegionService::formatApplyRow($row);
        return $this->renderSuccess('', [
            'has_apply'            => 1,
            'apply_id'             => (int)$data['apply_id'],
            'agent_type'           => (int)$data['agent_type'],
            'agent_type_text'      => (string)$data['agent_type_text'],
            'status'               => (int)$data['status'],
            'status_text'          => (string)$data['status_text'],
            'agent_province_id'    => (int)$data['agent_province_id'],
            'agent_city_id'        => (int)$data['agent_city_id'],
            'agent_district_id'    => (int)$data['agent_district_id'],
            'agent_province_name'  => (string)$data['agent_province_name'],
            'agent_city_name'      => (string)$data['agent_city_name'],
            'agent_district_name'  => (string)$data['agent_district_name'],
            'remark'               => (string)$data['remark'],
            'audit_remark'         => (string)$data['audit_remark'],
            'apply_time'           => (int)$data['create_time'],
            'audit_time'           => (int)$data['audit_time'],
        ]);
    }

    /**
     * 区域级联（申请页下拉）
     * - 不传 province_id / city_id：返回 province_list
     * - 传 province_id：返回 city_list
     * - 传 city_id：返回 district_list
     */
    public function region()
    {
        $provinceId = (int)$this->request->param('province_id', 0);
        $cityId     = (int)$this->request->param('city_id', 0);

        if ($cityId > 0) {
            $districtList = Db::name('region')
                ->where('pid', '=', $cityId)
                ->where('level', '=', 3)
                ->field('id,name,pid,level')
                ->order('id', 'asc')
                ->select()
                ->toArray();
            return $this->renderSuccess('', ['city_id' => $cityId, 'district_list' => $districtList]);
        }

        if ($provinceId > 0) {
            $cityList = Db::name('region')
                ->where('pid', '=', $provinceId)
                ->where('level', '=', 2)
                ->field('id,name,pid,level')
                ->order('id', 'asc')
                ->select()
                ->toArray();
            return $this->renderSuccess('', ['province_id' => $provinceId, 'city_list' => $cityList]);
        }

        $provinceList = Db::name('region')
            ->where('level', '=', 1)
            ->field('id,name,pid,level')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        return $this->renderSuccess('', ['province_list' => $provinceList]);
    }
}
