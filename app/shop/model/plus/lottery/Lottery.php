<?php

namespace app\shop\model\plus\lottery;

use app\common\exception\BaseException;
use app\common\library\helper;
use app\common\model\plus\lottery\Lottery as LotteryModel;

/**
 *转盘模型
 */
class Lottery extends LotteryModel
{
    /**
     * 获取数据
     * @param $id
     */
    public function getLottery()
    {
        $model = $this;
        $data = $model->with(['image'])->find();
        if (!$data) {
            $this->startTrans();
            try {
                $model->save([
                    'app_id' => self::$app_id,
                ]);
                $prize = [];
                for ($i = 0; $i < 8; $i++) {
                    if ($i == 0) {
                        $prize[] = [
                            'lottery_id' => $model['lottery_id'],
                            'name' => '祝你好运',
                            'image' => base_url() . 'image/image/lottery.png',
                            'stock' => 1,
                            'is_default' => 1,
                            'prompt' => '祝你好运',
                            'sort' => 1,
                            'app_id' => self::$app_id,
                        ];
                    } else {
                        $prize[] = [
                            'lottery_id' => $model['lottery_id'],
                            'name' => '祝你好运',
                            'image' => base_url() . 'image/image/lottery.png',
                            'stock' => 1,
                            'is_default' => 0,
                            'prompt' => '祝你好运',
                            'sort' => 1,
                            'app_id' => self::$app_id,
                        ];
                    }
                }
                (new LotteryPrize)->saveAll($prize);
                $data = $model->with(['image'])->find();
                $this->commit();
            } catch (\Exception $e) {
                $this->error = $e->getMessage();
                $this->rollback();
                throw new BaseException(['msg' => $e->getMessage()]);
            }
        }
        return $data;
    }

    /**
     * 修改
     * @param $value
     */
    public function edit($data)
    {
        $this->startTrans();
        try {
            if (empty($data['lottery_id'])) {
                $model = $this;
                $data['app_id'] = self::$app_id;
            } else {
                $model = self::detail();
            }
            $model->save($data);
            $this->addPrize($data['prize'], $model['lottery_id']);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

    //添加奖项
    public function addPrize($prize, $lottery_id)
    {
        $totalWeight = helper::getArrayColumnSum($prize, 'weight');
        $data = array_map(function ($prize) use ($lottery_id, $totalWeight) {
            $probability = 0;
            if ($totalWeight > 0) {
                $probability = round($prize['weight'] / $totalWeight, 2);
            }
            return [
                'app_id' => self::$app_id,
                'lottery_id' => $lottery_id,
                'name' => $prize['name'],
                'stock' => $prize['stock'],
                'type' => $prize['type'],
                'image' => $prize['image'],
                'is_default' => $prize['is_default'],
                'award_id' => $prize['award_id'],
                'prize_id' => $prize['prize_id'],
                'points' => $prize['points'],
                'draw_num' => isset($prize['draw_num']) ? $prize['draw_num'] : 0,
                'is_play' => $prize['is_play'],
                'weight' => $prize['weight'],
                'probability' => $probability,
                'prompt' => $prize['prompt'],
                'balance' => $prize['balance'],
                'product_price' => $prize['product_price']
            ];
        }, $prize);
        return $this->prize()->saveAll($data);
    }
}