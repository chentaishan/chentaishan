<?php

namespace app\shop\model\file;

use app\common\exception\BaseException;
use app\common\library\storage\Driver as StorageDriver;
use app\common\model\file\UploadFile as UploadFileModel;
use app\shop\model\settings\Setting as SettingModel;


/**
 * 图片模型
 */
class UploadFile extends UploadFileModel
{

    /**
     * 软删除
     */
    public function softDelete($fileIds)
    {
        $list = $this->where('file_id', 'in', $fileIds)->select();
        foreach ($list as $item) {
            if ($item['storage'] == 'local') {
                $file = 'uploads/' . $item['save_name'];
                if (file_exists($file)) {
                    unlink($file);
                }
            } else {
                $config = SettingModel::getItem('storage');
                $config['default'] = $item['storage'];
                $StorageDriver = new StorageDriver($config);
                $StorageDriver->delete($item['file_name']);
            }
            $this->where('file_id', '=', $item['file_id'])->update(['is_delete' => 1]);
        }
        return true;
    }

}
