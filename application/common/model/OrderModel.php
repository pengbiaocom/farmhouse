<?php
namespace app\common\model;


class OrderModel extends BaseModel
{


    public function editData($data)
    {
        if ($data['id']) {
            $data['update_time'] = time();
            $res = $this->allowField(true)->isUpdate(true)->data($data,true)->save();
        } else {
            $data['create_time'] = $data['update_time'] = time();
            $res = $this->allowField(true)->save($data);
            action_log('add_product', 'product', $res, is_login());
        }
        return $res;
    }

    public function getListByPage($map,$order = 'create_time desc', $field = '*', $r = 20)
    {
        $list = db("order")->field($field)->where($map)->order($order)->paginate($r,false);
        $page = $list->render();
        $data = $list->toArray();
        return [$data['data'],$page];
    }

    public function getList($map, $order = 'create_time desc', $limit = 5, $field = '*')
    {
        $lists = db("order")->field($field)->where($map)->order($order)->limit($limit)->select();
        return $lists;
    }

    public function getData($id)
    {
        if ($id > 0) {
            $map['id'] = $id;
            $data = db("order")->where($map)->find();
            return $data;
        }
        return null;
    }


} 