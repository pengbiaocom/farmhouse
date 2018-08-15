<?php
namespace app\common\model;

use think\Request;

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
        $config['query'] = isset($config['query']) ? $config['query'] : Request::instance()->param();
        $list = db("order")->field($field)->where($map)->order($order)->paginate($r,false,$config);
        $page = $list->render();
        $data = $list->toArray();
        return [$data['data'],$page];
    }

    public function getList($map, $order = 'create_time desc', $limit = 5, $field = '*')
    {
        $lists = db("order")->field($field)->where($map)->order($order)->limit($limit)->select();
        return $lists;
    }
} 