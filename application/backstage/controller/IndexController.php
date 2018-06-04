<?php
namespace app\backstage\controller;

use app\common\model\MenuModel;
use app\news\model\NewsModel;

class IndexController extends BackstageController
{
	
    /**
     * 后台首页控制器
     * @author 丶陌路灬离殇 <pengxuancom@163.com>
     */
    public function index()
    {
//        $com = new \COM('CKManager.jnicl');// （#注意word.application 是电脑中必须有word文档才可以的）
//        echo $com;
//        $menu = db("Menu")->where("pid=10018 and module='Wap'")->select();
//        if($menu){
//            foreach($menu as $key=>$row){
//                $menu[$key]['_'] = db("Menu")->where("pid=".$row['id']." and module='News'")->select();
//            }
//        }
//        dump($menu);
//        $data['menu'] = json_encode($menu);
//        dump(json_encode($data));
//        exit();
        if (UID) {
            $tileModel = db('tile');
            $list = $tileModel->where(['status' => 1])->order('sort asc')->select();
            foreach($list as &$key) {
                $key['url'] = url($key['url']);
                $key['url_vo'] = url($key['url_vo']);
            }
            $this->assign('list', $list);
            $this->assign('meta_title',lang('_INDEX_MANAGE_'));
            return $this->fetch();
        } else {
            $this->redirect('Public/login');
        }
    }

    /**
     * 添加常用操作
     */
    public function addTo()
    {
        $tileId = input('post.id', '', 'intval');
        $rs = db('tile')->where(['aid' => $tileId, 'status' => 1])->find();
        if($rs) {
            return ['code'=>0,'msg'=>'请勿重复添加！'];
        } else {
            $menuModel = new MenuModel();
            $nav = $menuModel->getPath($tileId);
            $max = db('Tile')->max('sort');

            $data['aid'] = $tileId;
            $data['icon'] = 'direction';
            $data['sort'] = $max+1;
            $data['status'] = 1;
            $data['title'] = $nav[1]['title'];
            $data['title_vo'] = $nav[0]['title'];
            $data['url'] = $nav[1]['url'];
            $data['url_vo'] = $nav[0]['url'];
            $data['tile_bg'] = '#1ba1e2';

            $res = db('tile')->insert($data);
            if($res) {
                return ['code'=>1,'msg'=>'添加成功！'];
            } else {
                return ['code'=>0,'msg'=>'添加失败！'];
            }
        }
    }

    /**
     * 删除常用操作
     */
    public function delTile()
    {
        $tileId = input('post.id', '', 'intval');

        $res = db('tile')->where(['id' => $tileId])->delete();
        if($res) {
            return ['code'=>1,'msg'=>'删除成功！','tile_id'=>$tileId];
        } else {
            return ['code'=>0,'msg'=>'删除失败！'];
        }
    }

    /**
     * 修改常用操作
     */
    public function setTile()
    {
        $tileId = input('post.id', '', 'intval');
        $tileIcon = input('post.icon', '', 'text');
        $tileBg = input('post.tile_bg', '', 'text');

        $data['icon'] = substr($tileIcon, 5);
        $data['tile_bg'] = $tileBg;
        $res = db('tile')->where(['id' => $tileId])->update($data);

        if($res){
            return ['code' => 1, 'msg' => '保存成功', 'tile_id' => $tileId, 'tile_icon' => $data['icon'], 'tile_bg' => $tileBg];
        }else{
            return ['code' => 0, 'msg' => '保存失败'];
        }
    }

    /**
     * 常用操作排序
     */
    public function sortTile()
    {
        $ids = input('post.ids/a', '');

        $i = 1;
        foreach($ids as $val) {
            if($val) {
                $val = substr($val,5);
                db('Tile')->where(['id' => $val])->setField(['sort' => $i]);
                $i++;
            }
        }
    }
}
