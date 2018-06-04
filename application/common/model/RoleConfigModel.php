<?php
namespace app\common\model;


class RoleConfigModel extends BaseModel
{
    public function addData($data){
        $data['update_time']=time();
        $result=$this->allowField(true)->save($data);
        return $result;
    }

    public function saveData($map=[],$data=[]){
        $data['update_time']=time();
        $result=$this->allowField(true)->save($data,$map);
        return $result;
    }

    public function deleteData($map){
        $result=$this->where($map)->delete();
        return $result;
    }
} 