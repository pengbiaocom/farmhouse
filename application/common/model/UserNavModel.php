<?php
namespace app\common\model;

/**
 * 导航模型
 */

class UserNavModel extends BaseModel {

    protected $rule = [
        'title'  =>  'require',
        'url' =>  'require',
    ];
    protected $message = [
        'title.require'  =>  '行为标识必须',
        'url.require' =>'标题不能为空',
    ];

    protected $scene = [
        'add'   =>  ['title','url'],
        'edit'  =>  ['title','url'],
    ];

    protected $insert = [
        'create_time',
        'update_time',
        'status'=>1,
    ];

    protected $update = ['update_time','status'=>1];

    protected  function setCreateTimeAttr(){
        return time();
    }

    protected  function setUpdateTimeAttr(){
        return time();
    }

}
