<?php
namespace app\common\model;


use think\Request;

class ProductModel extends BaseModel
{
    public function editData($data)
    {
        if (!mb_strlen($data['description'], 'utf-8')) {
            $data['description'] = msubstr(op_t($data['content']), 0, 200);
        }
        $data['reason'] = '';
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

    public function getListByPage($map,$order = 'update_time desc', $field = '*', $r = 20)
    {
        $config['query'] = isset($config['query']) ? $config['query'] : Request::instance()->param();
        $list = db("Product")->field($field)->where($map)->order($order)->paginate($r,false, $config);
        $page = $list->render();
        $data = $list->toArray();
        return [$data['data'],$page];
    }

    public function getList($map, $order = 'view desc', $limit = 5, $field = '*')
    {
        $lists = db("Product")->field($field)->where($map)->order($order)->limit($limit)->select();
        return $lists;
    }

    public function getInfoData($id)
    {
        if ($id > 0) {
            $map['id'] = $id;
            $data = db("Product")->where($map)->find();
            return $data;
        }
        return null;
    }
} 