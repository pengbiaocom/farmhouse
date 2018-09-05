<?php
namespace app\api\controller;

use think\Controller;
use think\Request;

class SeedsController extends Controller{

    /**
     * 列表
     * @date: 2018年6月6日 下午2:07:02
     * @param: variable
     * @return:
     */
    public function good_list(Request $request){
        $model = db('seeds');
        $limit = $request->param('limit', 10, 'intval');
        $page = $request->param('page', 1, 'intval');
        $uid = $request->param('uid', 0, 'intval');

        if($page == 'undefined' || $page == '') $page = 1;

        $list = $model
            ->where('status > 0 ')
            ->order('sort ASC')
            ->limit(($page-1)*$limit, $limit)
            ->select();

        if(!empty($list)){
            foreach ($list as &$item){
                $item['cover'] = get_cover(explode(',', $item['cover'])[0], 'path');
            }

            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$list, 'paginate'=>array('page'=>sizeof($list) < $limit ? $page : $page+1, 'limit'=>$limit)]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }

    /**
     * 详情
     * @date: 2018年6月6日 下午2:48:33
     * @param: variable
     * @return:
     */
    public function detail(Request $request){
        $model = db('seeds');
        $id = $request->param('id', 0, 'intval');
        $uid = $request->param('uid', 0, 'intval');

        if($id == 0) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);

        $info = $model
            ->where('status > 0 and id = ' . $id)
            ->find();

        if(!empty($info)){
            if(!empty($info['cover'])){
                foreach (explode(',', $info['cover']) as $item){
                    $images[] = get_cover($item, 'path');
                }
                $info['cover'] = $images;
            }
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$info]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }
}