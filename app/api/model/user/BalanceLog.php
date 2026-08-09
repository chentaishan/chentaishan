<?php



namespace app\api\model\user;



use app\common\enum\user\balanceLog\BalanceLogSceneEnum;
use app\common\model\user\BalanceLog as BalanceLogModel;

use app\common\model\user\User as UserModel;



/**

 * 用户余额变动明细模型

 */

class BalanceLog extends BalanceLogModel

{

    /**

     * 隐藏字段

     */

    protected $hidden = [

        'app_id',

    ];



    /**

     * 获取账单明细列表

     */

    public function getTop10($userId)

    {

        $list = $this->where('user_id', '=', $userId)

            ->with(['user' => function ($query) {

                $query->field('user_id,nickName,mobile');

            }])

            ->order(['log_id' => 'desc'])

            ->limit(10)

            ->select();



        return $this->appendDisplayDescribe($list);

    }



    /**

     * 获取账单明细列表

     */

    public function getList($userId, $type)

    {

        $model = $this;

        if ($type == 'rechange') {

            $model = $model->where('scene', '=', 10);

        }

        $list = $model->where('user_id', '=', $userId)

            ->with(['user' => function ($query) {

                $query->field('user_id,nickName,mobile');

            }])

            ->order(['log_id' => 'desc'])

            ->paginate(30);



        return $this->appendDisplayDescribe($list);

    }



    /**

     * 统一生成前端展示用 describe 字段

     */

    private function appendDisplayDescribe($list)

    {

        $items = $list instanceof \think\Paginator ? $list->getCollection() : $list;

        if ($items->isEmpty()) {

            return $list;

        }



        $peerUserIds = [];

        $peerMobiles = [];

        foreach ($items as $item) {

            $scene = self::resolveSceneValue($item['scene']);

            if ($scene !== BalanceLogSceneEnum::TRANSFER) {

                continue;

            }

            $rawDescribe = (string)$item['describe'];
            $peerUserId = self::parseTransferUserId($rawDescribe);

            if ($peerUserId > 0) {

                $peerUserIds[] = $peerUserId;
                continue;

            }
            $mobile = self::parseTransferMobile($rawDescribe);

            if ($mobile !== '') {

                $peerMobiles[] = $mobile;

            }

        }



        $nameByUserId = [];

        if (!empty($peerUserIds)) {

            $nameByUserId = UserModel::where('user_id', 'in', array_unique($peerUserIds))

                ->where('is_delete', '=', 0)

                ->column('nickName', 'user_id');

        }

        $nameByMobile = [];

        if (!empty($peerMobiles)) {

            $nameByMobile = UserModel::where('mobile', 'in', array_unique($peerMobiles))

                ->where('is_delete', '=', 0)

                ->column('nickName', 'mobile');

        }



        foreach ($items as &$item) {

            $item['nickName'] = (string)($item['user']['nickName'] ?? '');

            $scene = self::resolveSceneValue($item['scene']);

            $sceneText = is_array($item['scene']) ? (string)($item['scene']['text'] ?? '') : '';

            $rawDescribe = (string)$item['describe'];



            if ($scene === BalanceLogSceneEnum::TRANSFER) {

                $peerNickName = self::resolveTransferPeerNickName($rawDescribe, $nameByUserId, $nameByMobile);

                $item['describe'] = self::buildTransferDisplayDescribe(

                    $rawDescribe,

                    (float)$item['money'],

                    $peerNickName

                );

                continue;

            }



            $item['describe'] = self::buildDefaultDisplayDescribe($rawDescribe, $sceneText);

        }

        unset($item);



        return $list;

    }



    private static function resolveSceneValue($scene): int

    {

        return is_array($scene) ? (int)($scene['value'] ?? 0) : (int)$scene;

    }



    private static function resolveTransferPeerNickName(string $describe, array $nameByUserId, array $nameByMobile): string

    {

        $peerUserId = self::parseTransferUserId($describe);

        if ($peerUserId > 0 && isset($nameByUserId[$peerUserId])) {

            return (string)$nameByUserId[$peerUserId];

        }



        $mobile = self::parseTransferMobile($describe);

        if ($mobile !== '' && isset($nameByMobile[$mobile])) {

            return (string)$nameByMobile[$mobile];

        }



        return '';

    }



    private static function buildDefaultDisplayDescribe(string $rawDescribe, string $sceneText): string

    {

        if ($rawDescribe !== '') {

            return $rawDescribe;

        }

        return $sceneText !== '' ? $sceneText : '余额变动';

    }



    private static function buildTransferDisplayDescribe(string $rawDescribe, float $money, string $peerNickName): string

    {

        if ($peerNickName === '') {

            return self::buildDefaultDisplayDescribe($rawDescribe, '余额互转');

        }



        if ($money > 0 || self::isIncomingTransferDescribe($rawDescribe)) {

            return '余额互转，来自' . $peerNickName;

        }



        return '余额互转，转给' . $peerNickName;

    }



    private static function parseTransferUserId(string $describe): int

    {

        if (preg_match('/转出给用户(\d+)/', $describe, $matches)) {

            return (int)$matches[1];

        }

        if (preg_match('/收到用户(\d+)转账/', $describe, $matches)) {

            return (int)$matches[1];

        }

        return 0;

    }



    private static function parseTransferMobile(string $describe): string

    {

        if (preg_match('/转出至(1\d{10})/', $describe, $matches)) {

            return $matches[1];

        }

        if (preg_match('/转入来自(1\d{10})/', $describe, $matches)) {

            return $matches[1];

        }

        return '';

    }



    private static function isIncomingTransferDescribe(string $describe): bool

    {

        return (bool)preg_match('/收到用户\d+转账|转入来自/', $describe);

    }



}

