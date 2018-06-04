<?php
namespace app\news\model;

use app\common\model\BaseModel;

class NewsModel extends BaseModel
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
            action_log('add_news', 'news', $res, is_login());
        }
        return $res;
    }

    public function getListByPage($map,$order = 'update_time desc', $field = '*', $r = 20)
    {
        $list = db("News")->field($field)->where($map)->order($order)->paginate($r,false);
        $page = $list->render();
        $data = $list->toArray();
        return [$data['data'],$page];
    }

    public function getList($map, $order = 'view desc', $limit = 5, $field = '*')
    {
//        $lists = $this->field($field)->where($map)->order($order)->limit($limit)->select()->toArray();
        $lists = db("News")->field($field)->where($map)->order($order)->limit($limit)->select();
        return $lists;
    }

    public function getData($id)
    {
        if ($id > 0) {
            $map['id'] = $id;
            $data = db("News")->where($map)->find();
            if ($data) {
                if ($data['post_time'] == 0) {
                    $data['post_time'] = $data['create_time'];
                } elseif ($data['post_time'] > time() && (is_login()!=$data['uid'])) {
                    return false;
                }
            } else {
                return false;
            }
            return $data;
        }
        return null;
    }

    /**
     * 获取推荐位数据列表
     * @param $pos 推荐位 1-系统首页，2-推荐阅读，4-本类推荐
     * @param null $category
     * @param $limit
     * @param bool $field
     * @param $order
     * @return mixed
     */
    public function position($pos, $category = null, $limit = 5, $field = true, $order = 'sort desc,view desc')
    {
        $map = $this->listMap($category, 1, $pos);
        $res = db("News")->field($field)->where($map)->order($order)->limit($limit)->select();
        /* 读取数据 */
        return $res;
    }

    /**
     * 设置where查询条件
     * @param  number $category 分类ID
     * @param  number $pos 推荐位
     * @param  integer $status 状态
     * @return array             查询条件
     */
    private function listMap($category, $status = 1, $pos = null)
    {
        /* 设置状态 */
        $map = "status=$status";

        /* 设置分类 */
        if (!is_null($category)) {
            $newsCategoryModel = new NewsCategoryModel();
            $cates = $newsCategoryModel->getCategoryList(['pid' => $category, 'status' => 1]);
            $cates = array_column($cates, 'id');
            if($cates) {
                $category = array_merge([$category], $cates);
                $category = implode(',',$category);
            }
            $map .= " and category in (".$category.")";
        }

        /* 设置推荐位 */
        if (is_numeric($pos)) {
            $map .= " and position & {$pos} = {$pos}";
        }

        return $map;
    }

} 