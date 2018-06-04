<?php
namespace app\common\model;

/**
 * 身份模型
 * Class Role
 * @package common\model
 */
class RoleModel extends BaseModel
{
    protected $rule = [
        'name'  =>  'require|regex:zip|unique:role|checkName',
        'title' =>  'require|unique:role',
    ];

    protected $message = [
        'name.require'  =>  '标识不能为空',
        'name.regex' =>  '标识不合法',
        'name.unique' =>  '身份标识已经存在',
        'name.checkName'=>'身份标识只能由字母和下滑线组成',
        'title.require' =>'身份名不能为空',
        'title.unique' =>'身份名已经存在',
    ];

    protected $scene = [
        'add'   =>  ['name','title'],
        'edit'  =>  ['name','title'],
    ];

    protected  $insert = [
        'create_time',
        'update_time',
        'status'=>1
    ];

    protected $update = [
        'update_time',
        'status'=>1
    ];

    protected  function setUpdateTimeAttr(){
        return time();
    }

    protected  function setCreateTimeAttr(){
        return time();
    }

    protected $insertFields='group_id,name,title,description,user_groups,invite,audit,sort,status,create_time';
    protected $updateFields='id,group_id,name,title,description,user_groups,invite,audit,sort,status,update_time';

    /**
     * 插入数据
     * @param $data
     * @return mixed
     */
    public function insert($data=[]){

        $result=$this->allowField(true)->save($data);
        return $result;
    }

    /**
     * 修改数据
     * @param $data
     * @param $where
     * @return mixed
     */
    public function updateById($data=[],$where){
        $result=$this->allowField(true)->save($data,$where);
        return $result;
    }

    /**
     * 分页按照$map获取列表
     * @param array $map 查询条件
     * @param $order 排序
     * @param null $fields 查询字段，null表示全部字段
     * @param int $r 每页条数
     * @return mixed 一页结果列表
     */
    public function selectPageByMap($map=[],$r=20,$order,$fields=null){
        $order=$order?$order:"id asc";
        if($fields==null){
            $list = $this->where($map)->order($order)->paginate($r,false);
        }else{
            $list = $this->field($fields)->where($map)->order($order)->paginate($r,false);
        }

        $page = $list->render();
        $data = $list->toArray();
        return [$data['data'],$page];
    }

    /**
     * 通过$map获取列表
     * @param array $map 查询条件
     * @param $order 排序
     * @param null $fields 查询字段，null表示全部字段
     * @return mixed 结果列表
     */
    public function selectByMap($map=[],$order=null,$fields=null){
        $order=$order?$order:"id asc";
        if($fields==null){
            $list=$this->where($map)->order($order)->select()->toArray();
        }else{
            $list=$this->where($map)->order($order)->field($fields)->select()->toArray();
        }
        return $list;
    }

    /**
     * * 通过$map获取单条值
     * @param array $map 查询条件
     * @param string $order 排序
     * @param null $fields 查询字段，null表示全部字段
     * @return mixed 结果
     */
    public function getByMap($map=[],$order,$fields=null){
        $order=$order?$order:"id asc";
        if($fields==null){
            $data=$this->where($map)->order($order)->find()->toArray();
        }else{
            $data=$this->where($map)->order($order)->field($fields)->find()->toArray();
        }
        return $data;
    }

    /**
     * 验证身份名(只能有字母和下划线组成)
     * @param $name
     * @return bool
     */
    public function checkName($name){
        if(!preg_match('/^[_a-z]*$/i',$name)){
            return false;
        }
        return true;
    }
} 