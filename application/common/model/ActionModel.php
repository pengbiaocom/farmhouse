<?php
namespace app\common\model;

class ActionModel extends BaseModel{

    /* �Զ���ɹ��� */
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
        'name.require'  =>  '��Ϊ��ʶ����',
        'name.regex' =>  '��ʶ���Ϸ�',
        'name.unique' =>  '��ʶ�Ѿ�����',
        'title.require' =>'���ⲻ��Ϊ��',
        'title.length' =>'���ⳤ�Ȳ��ܳ���80���ַ�',
        'remark.require' =>'��Ϊ��������Ϊ��',
        'remark.length' =>'��Ϊ�������ܳ���140���ַ�',
    ];

    protected $scene = [
        'add'   =>  ['name','title','remark'],
        'edit'  =>  ['name','title','remark'],
    ];

    protected  function setUpdateTimeAttr(){
        return time();
    }

    /**
     * ���������һ����Ϊ
     * @return boolean fasle ʧ�� �� int  �ɹ� ��������������
     */
    public function updates(){
        $data = Request()->param();
        /* ��ӻ�������Ϊ */
        if(empty($data['id'])){ //��������
            $res = $this->allowField(true)->isUpdate(false)->save($data); //�����Ϊ
            $id = $res->id;
            if(!$id){
                $this->error = lang('_NEW_BEHAVIOR_WITH_EXCLAMATION_');
                return false;
            }
        } else { //��������
            $status = $this->allowField(true)->isUpdate(true)->save($data,['id'=>$data['id']]); //���»�������
            if(false === $status){
                $this->error = lang('_UPDATE_BEHAVIOR_WITH_EXCLAMATION_');
                return false;
            }
        }
        //ɾ������
        cache('action_list', null);

        //������ӻ�������
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