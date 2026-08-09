<?php

namespace app\api\controller\plus\live\agora;

use app\api\controller\Controller;
use app\api\model\plus\live\Room as RoomModel;
use app\common\library\agora\token\TokenService;
use app\common\model\plus\live\UserGift as UserGiftModel;
use app\common\model\settings\Setting as SettingModel;

/**
 * 声网api
 */
class Api extends Controller
{
    /**
     * 登录房间
     */
    public function login($room_id, $channel)
    {
        $user = $this->getUser();
        $value = SettingModel::getItem('live');
        $appId = $value['app_id'];
        $appCertificate = $value['key'];
        $room = RoomModel::detail($room_id);
        $isCaster = false;
        if ($room['user_id'] == $user['user_id']) {
            $isCaster = true;
        }
        $userSign = TokenService::getRtcToken($user['user_id'], $channel, $appId, $appCertificate, $isCaster);
        //累计观看人数
        (new RoomModel())->where('room_id', '=', $room_id)->inc('view_num')->update();
        $user_gift_model = UserGiftModel::detail($room_id, $user['user_id']);
        if (!$user_gift_model) {
            // 插入用户礼物表
            (new UserGiftModel())->save([
                'room_id' => $room_id,
                'user_id' => $user['user_id'],
                'app_id' => $room['app_id']
            ]);
        }
        return $this->renderSuccess('', compact('userSign', 'user', 'appId'));
    }

    /**
     * rtm授权
     */
    public function rtmToken()
    {
        $user = $this->getUser();
        $value = SettingModel::getItem('live');
        $appId = $value['app_id'];
        $appCertificate = $value['key'];
        $userSign = TokenService::getRtmToken($user['user_id'] . "", $appId, $appCertificate);
        return $this->renderSuccess('', compact('userSign'));
    }

    /**
     * 请求录制
     */
    public function record_acquire($room_id)
    {
        $settings = SettingModel::getItem('live');
        if ($settings['is_record'] == 0) {
            return $this->renderSuccess('');
        }
        $room = RoomModel::detail($room_id);
        if ($room['record_resource_id'] != '') {
            return $this->renderSuccess('');
        }
        $curl = curl_init();
        $header = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($settings['username'] . ':' . $settings['password'])
        ];
        $data = "{
          \"cname\": \"{$room['room_name']}\",
          \"uid\": \"{$room['record_uid']}\",
          \"clientRequest\":{
          }
        }";
        curl_setopt($curl, CURLOPT_URL, "https://api.agora.io/v1/apps/{$settings['app_id']}/cloud_recording/acquire");
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        $resourceId = '';
        if (isset($response['resourceId']) && $response['resourceId']) {
            $resourceId = $response['resourceId'];
            $room->save([
                'record_resource_id' => $resourceId
            ]);
        }
        return $this->renderSuccess('', compact('resourceId'));
    }

    /**
     * 开始录制
     * {
     * "resourceId": "nUwUbQf9Zg6tsgtLslGnDg0lk8RYaUE09pqOuSIgwfx_RCWYj4P3oDfpUoGbm4kTO6pcgOOsoogy0zqHVMN-Xt-D_pSRZoGKePV1i5mt-MWp-wsSGHFVRYqVnNYhiNE4VhDf33vBNJw4FReRsORLxajYLaR0TeQJL5M_V8mGvkZeg9KQ_9TmOtlyWxpavS7ynAsMQuDEk_V7Pbl1HLC5_dxFLTcCc4rH9mBZ7gUCjELwKyf-uoxgMAuOjtZHibf1ba-ZW_zijbsf8fxHYSGK84ud3iSi5Z-UH9DZWZbnMN1KJ1zA1pnU1-i2kTb_9TSV",
     * "sid": "661086d50844a5a9b680f6808a4b668c"
     * }
     */
    public function record_start($room_id)
    {
        $settings = SettingModel::getItem('live');
        if ($settings['is_record'] == 0) {
            return $this->renderSuccess('');
        }
        $room = RoomModel::detail($room_id);
        if ($room['record_sid'] != '') {
            return $this->renderSuccess('');
        }
        $curl = curl_init();

        $header = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($settings['username'] . ':' . $settings['password'])
        ];
        $userSign = TokenService::getRtcToken($room['record_uid'], $room['room_name'], $settings['app_id'], $settings['key'], false);
        $data = "{
            \"cname\":\"{$room['room_name']}\",
            \"uid\":\"{$room['record_uid']}\",
            \"clientRequest\":{
                \"token\":\"{$userSign}\",
                \"recordingConfig\":{
                    \"maxIdleTime\":300,
                    \"streamTypes\":2,
                    \"audioProfile\":1,
                    \"channelType\":1,
                    \"videoStreamType\":0
                },
                \"storageConfig\":{
                    \"vendor\":{$settings['vendor']},
                    \"region\": {$settings['region']},
                    \"bucket\":\"{$settings['bucket']}\",
                    \"accessKey\":\"{$settings['accessKey']}\",
                    \"secretKey\":\"{$settings['secretKey']}\"
                }	
            }
        }";
        //\"fileNamePrefix\":{$settings['fileNamePrefix']}
        curl_setopt($curl, CURLOPT_URL, "https://api.agora.io/v1/apps/{$settings['app_id']}/cloud_recording/resourceid/{$room['record_resource_id']}/mode/mix/start");
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        $sid = '';
        if (isset($response['sid']) && $response['sid']) {
            $sid = $response['sid'];
            $room->save([
                'record_sid' => $sid
            ]);
        }
        return $this->renderSuccess('', compact('sid'));
    }

    /**
     * 请求录制
     */
    public function record_query($room_id)
    {
        $settings = SettingModel::getItem('live');
        if ($settings['is_record'] == 0) {
            return $this->renderSuccess('');
        }
        $room = RoomModel::detail($room_id);
        if ($room['record_sid'] == '') {
            return $this->renderSuccess('');
        }
        $curl = curl_init();
        $header = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($settings['username'] . ':' . $settings['password'])
        ];
        $url = "https://api.agora.io/v1/apps/{$settings['app_id']}/cloud_recording/resourceid/{$room['record_resource_id']}/sid/{$room['record_sid']}/mode/mix/query";
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        $record_url = $response['serverResponse']['fileList'];
        if ($record_url != '') {
            $room->save([
                'record_url' => $settings['domain'] . '/' . $record_url
            ]);
        }
        return $this->renderSuccess('', compact('record_url'));
    }

    /**
     * 停止录制
     */
    public function record_stop($room_id)
    {
        $settings = SettingModel::getItem('live');
        $room = RoomModel::detail($room_id);
        if ($room['record_sid'] == '') {
            return $this->renderSuccess('');
        }
        $curl = curl_init();
        $header = [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($settings['username'] . ':' . $settings['password'])
        ];
        $data = "{
          \"cname\": \"{$room['room_name']}\",
          \"uid\": \"{$room['record_uid']}\",
          \"clientRequest\":{
          }
        }";
        curl_setopt($curl, CURLOPT_URL, "https://api.agora.io/v1/apps/{$settings['app_id']}/cloud_recording/resourceid/{$room['record_resource_id']}/sid/{$room['record_sid']}/mode/mix/stop");
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);
        return $this->renderSuccess('');
    }
}