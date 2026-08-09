<?php

namespace app\common\model\order;

use app\common\model\BaseModel;

/**
 * 资讯(多条) 模型
 * 表: jjjshop_cloud_information
 */
class CloudInformation extends BaseModel
{
    protected $pk = 'information_id';
    protected $name = 'cloud_information';

    /**
     * 资讯详情
     */
    public static function detail($informationId, $appId = 0)
    {
        $model = (new static())
            ->where('information_id', '=', (int)$informationId)
            ->where('is_delete', '=', 0);
        if ($appId > 0) {
            $model->where('app_id', '=', (int)$appId);
        }
        return $model->find();
    }

    /**
     * 后台列表(分页, 支持标题/状态检索)
     */
    public function getList($params = [], $appId = 0)
    {
        $model = $this->where('is_delete', '=', 0);
        if ($appId > 0) {
            $model->where('app_id', '=', (int)$appId);
        }
        if (isset($params['title']) && $params['title'] !== '') {
            $model->where('title', 'like', '%' . trim($params['title']) . '%');
        }
        if (isset($params['status']) && $params['status'] !== '' && $params['status'] !== null) {
            $status = (int)$params['status'];
            if (in_array($status, [0, 1], true)) {
                $model->where('status', '=', $status);
            }
        }
        return $model->order(['sort' => 'asc', 'information_id' => 'desc'])
            ->paginate($params);
    }

    /**
     * 用户端可见资讯列表(仅显示中, 不分页)
     */
    public function getVisibleList($appId = 0)
    {
        $model = $this->where('is_delete', '=', 0)
            ->where('status', '=', 1)
            ->order(['sort' => 'asc', 'information_id' => 'desc']);
        if ($appId > 0) {
            $model->where('app_id', '=', (int)$appId);
        }
        return $model->select();
    }

    /**
     * 新增资讯
     */
    public function add($data, $appId = 0)
    {
        if (!$this->validateInformationData($data)) {
            return false;
        }
        return $this->save([
            'title' => $this->normalizeTitle($data['title'] ?? ''),
            'content' => $data['content'],
            'help_prompt' => trim($data['help_prompt'] ?? ''),
            'image' => trim($data['image'] ?? ''),
            'link_url' => trim($data['link_url'] ?? ''),
            'sort' => isset($data['sort']) && $data['sort'] !== '' ? (int)$data['sort'] : 100,
            'status' => isset($data['status']) ? (int)$data['status'] : 1,
            'is_delete' => 0,
            'app_id' => $appId > 0 ? (int)$appId : (int)self::$app_id,
        ]);
    }

    /**
     * 修改资讯
     */
    public function edit($data)
    {
        if (!$this->validateInformationData($data)) {
            return false;
        }
        $update = [
            'title' => isset($data['title']) ? $this->normalizeTitle($data['title']) : $this['title'],
            'content' => $data['content'],
        ];
        if (isset($data['help_prompt'])) {
            $update['help_prompt'] = trim($data['help_prompt']);
        }
        if (isset($data['image'])) {
            $update['image'] = trim($data['image']);
        }
        if (isset($data['link_url'])) {
            $update['link_url'] = trim($data['link_url']);
        }
        if (isset($data['sort']) && $data['sort'] !== '') {
            $update['sort'] = (int)$data['sort'];
        }
        if (isset($data['status']) && $data['status'] !== '') {
            $update['status'] = (int)$data['status'];
        }
        return $this->save($update) !== false;
    }

    /**
     * 软删除
     */
    public function setDelete()
    {
        return $this->save(['is_delete' => 1]) !== false;
    }

    /**
     * 设置显示状态
     */
    public function setStatus($status)
    {
        $status = filter_var($status, FILTER_VALIDATE_INT);
        if ($status === false || !in_array($status, [0, 1], true)) {
            $this->error = '显示状态只能为0或1';
            return false;
        }
        return $this->save(['status' => $status]) !== false;
    }

    /**
     * 设置排序值
     */
    public function setSort($sort)
    {
        $sort = filter_var($sort, FILTER_VALIDATE_INT);
        if ($sort === false || $sort < 0) {
            $this->error = '排序值必须为非负整数';
            return false;
        }
        return $this->save(['sort' => $sort]) !== false;
    }

    /**
     * 基础校验
     */
    private function validateInformationData($data)
    {
        if (!isset($data['content']) || $data['content'] === '' || $data['content'] === null) {
            $this->error = '缺少资讯内容';
            return false;
        }
        if (isset($data['title']) && mb_strlen(trim($data['title'])) > 255) {
            $this->error = '资讯标题不能超过255个字符';
            return false;
        }
        if (isset($data['help_prompt']) && mb_strlen(trim($data['help_prompt'])) > 500) {
            $this->error = '提示语不能超过500个字符';
            return false;
        }
        foreach (['image' => '封面图地址', 'link_url' => '跳转链接'] as $field => $label) {
            if (isset($data[$field]) && mb_strlen(trim($data[$field])) > 255) {
                $this->error = $label . '不能超过255个字符';
                return false;
            }
        }
        if (isset($data['status'])) {
            $status = filter_var($data['status'], FILTER_VALIDATE_INT);
            if ($status === false || !in_array($status, [0, 1], true)) {
                $this->error = '显示状态只能为0或1';
                return false;
            }
        }
        if (isset($data['sort']) && $data['sort'] !== '') {
            $sort = filter_var($data['sort'], FILTER_VALIDATE_INT);
            if ($sort === false || $sort < 0) {
                $this->error = '排序值必须为非负整数';
                return false;
            }
        }
        return true;
    }

    /**
     * 兼容旧管理端未提交标题的情况。
     */
    private function normalizeTitle($title)
    {
        $title = trim((string)$title);
        return $title !== '' ? $title : '平台资讯';
    }
}
