<?php
namespace app\common\model;

class ActionModel extends BaseModel{

    /* 自动完成规则 */
    protected $insert = [
        'update_time',
        'status'=>1,
    ];
    protected $update = ['update_time'];
    protected $regex = [ 'zip' => '/^[a-zA-Z]\w{0,39}$/'];
    protected $rule = [
        'name'  =>  'require|regex:zip|unique:action',
        'title' =>  'require|length:1,80',
        'remark'=>'require|length:1,140',
    ];

    protected $message = [
        'name.require'  =>  '行为标识必须',
        'name.regex' =>  '标识不合法',
        'name.unique' =>  '标识已经存在',
        'title.require' =>'标题不能为空',
        'title.length' =>'标题长度不能超过80个字符',
        'remark.require' =>'行为描述不能为空',
        'remark.length' =>'行为描述不能超过140个字符',
    ];

    protected $scene = [
        'add'   =>  ['name','title','remark'],
        'edit'  =>  ['name','title','remark'],
    ];

    protected  function setUpdateTimeAttr(){
        return time();
    }

    /**
     * 新增或更新一个行为
     * @return boolean fasle 失败 ， int  成功 返回完整的数据
     */
    public function updates(){
        $data = Request()->param();
        /* 添加或新增行为 */
        if(empty($data['id'])){ //新增数据
            $res = $this->allowField(true)->isUpdate(false)->save($data); //添加行为
            $id = $res->id;
            if(!$id){
                $this->error = lang('_NEW_BEHAVIOR_WITH_EXCLAMATION_');
                return false;
            }
        } else { //更新数据
            $status = $this->allowField(true)->isUpdate(true)->save($data,['id'=>$data['id']]); //更新基础内容
            if(false === $status){
                $this->error = lang('_UPDATE_BEHAVIOR_WITH_EXCLAMATION_');
                return false;
            }
        }
        //删除缓存
        cache('action_list', null);

        //内容添加或更新完成
        return $data;

    }

    public function getAction($map){
        $result = $this->where($map)->select()->toArray();
        return $result;
    }

    public function getActionOpt(){
        $result = $this->where(['status'=>1])->field('name,title')->select()->toArray();
        return $result;
    }

    public function getListPage($map,$order='uid desc',$r=30)
    {
        $list = $this->where($map)->order($order)->paginate($r,false);
        $page = $list->render();
        $data = $list->toArray();
        return [$data['data'],$page];
    }

}