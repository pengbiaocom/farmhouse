<?php
/**
 * Created by PhpStorm.
 * User: caipeichao
 * Date: 1/22/14
 * Time: 11:05 PM
 */

namespace Addons\LocalComment\Model;

use think\Model;

class LocalCommentModel extends Model
{

    /* 用户模型自动验证 */
    protected $rule = [
        'content'  =>  'require|length:1,99999'
    ];
    protected $message = [
        'content.require'  =>  '评论内容太长',
        'content.length' =>  '评论内容不能为空',
    ];

    protected $scene = [
        'add'   =>  ['content'],
        'edit'  =>  ['content'],
    ];

    /* 用户模型自动完成 */
    protected $insert = ['create_time','status'];

    protected $update = ['status'];

    protected function setCreateTimeAttr(){
        return time();
    }

    public function addComment($data)
    {
        $result = $this->allowField(true)->save($data);
        if (!$result) {
            return false;
        }
        return $result;
    }



    public function getComment($id){

            $comment = cache('local_comment_' . $id);
            if (is_bool($comment)) {
                $comment = $this->where(['id' => $id, 'status' => 1])->find();
                if ($comment) {
                    $comment['user'] = query_user(['avatar64', 'nickname', 'uid', 'space_url'], $comment['uid']);
                }
                cache('local_comment_' . $id, $comment, 60 * 60);
            }
            return $comment;
        }

    public function deleteComment($comment_id)
    {
        //获取微博编号
        $comment = $this->getComment($comment_id);
        if ($comment['status'] == -1) {
            return false;
        }
        $this->where(['id' => $comment_id])->setField('status', -1);
        cache('local_comment_' . $comment_id, null);
        //返回成功结果
        return true;
    }

}