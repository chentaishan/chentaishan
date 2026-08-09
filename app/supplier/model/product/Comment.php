<?php

namespace app\supplier\model\product;

use app\common\model\product\Comment as CommentModel;

class Comment extends CommentModel
{
    /**
     * 软删除
     */
    public function setDelete($comment_id)
    {
        return $this->where('comment_id', '=', $comment_id)->save(['is_delete' => 1]);
    }

    /**
     * 获取评价总数量
     */
    public function getCommentTotal()
    {
        return $this->where(['is_delete' => 0])->count();
    }

    /**
     * 获取待审核商品评价总数量
     */
    public function getReviewCommentTotal()
    {
        return $this->where(['is_delete' => 0, 'status' => 0])->count();
    }


    /**
     * 更新记录
     */
    public function edit($data)
    {
        $this->startTrans();
        try {
            $this->save([
                'status' => $data['status']
            ]);
            $this->updateScore($this['shop_supplier_id']);
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            $this->rollback();
            return false;
        }
    }

}