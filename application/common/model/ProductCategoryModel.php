<?php
namespace app\common\model;


class ProductCategoryModel extends BaseModel
{

    protected $insert = ['status'=>1];

    /**
     * 获得分类树
     * @param int $id
     * @param bool $field
     * @param $map
     * @return array
     */
    public function getTree($id = 0, $field = true, $map = ['status' => ['gt', -1]])
    {
        /* 获取当前分类信息 */
        if ($id) {
            $info = $this->info($id);
            $id = $info['id'];
        }

        /* 获取所有分类 */
        $list = $this->field($field)->where($map)->order('sort')->select()->toArray();
        $list = list_to_tree($list, $pk = 'id', $pid = 'pid', $child = '_', $root = $id);

        /* 获取返回数据 */
        if (isset($info)) { //指定分类则返回当前分类极其子分类
            $info['_'] = $list;
        } else { //否则返回所有分类
            $info = $list;
        }
        return $info;
    }


    /**
     * 获取分类详细信息
     * @param $id
     * @param bool $field
     * @return mixed
     */
    public function info($id, $field = true)
    {
        /* 获取分类信息 */
        $map = [];
        if (is_numeric($id)) { //通过ID查询
            $map['id'] = $id;
        } else { //通过标识查询
            $map['name'] = $id;
        }
        return $this->field($field)->where($map)->find()->toArray();
    }

    public function editData($data)
    {
        if ($data['id']) {
            $res = $this->allowField(true)->isUpdate(true)->data($data,true)->save();
        } else {
            $res = $this->allowField(true)->save($data);
        }
        return $res;
    }

    public function getCategoryList($map, $type = 0)
    {
        $list = $this->where($map)->field('id,title,pid,sort,status')->order('sort asc')->select()->toArray();
        if (!$type) {
            return $list;
        }
        $father_list = $child_list = [];
        foreach ($list as $val) {
            if ($val['pid'] == 0) {
                $father_list[] = $val;
            } else {
                $val['title'] = '[子分类]' . $val['title'];
                $child_list[] = $val;
            }
        }
        $cateList = [];
        foreach ($father_list as $val) {
            $cateList[] = $val;
            foreach ($child_list as $tt) {
                if ($tt['pid'] == $val['id']) {
                    $cateList[] = $tt;
                }
            }
        }
        return $cateList;
    }

} 