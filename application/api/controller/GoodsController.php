<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\CommentsModel;

class GoodsController extends Controller{
    /**
    * 商品列表
    * @date: 2018年6月6日 下午2:07:02
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function good_list(Request $request){
        $model = db('product');
        $category = $request->param('cate', 0, 'intval');
        $limit = $request->param('limit', 10, 'intval');
        $page = $request->param('page', 1, 'intval');
        
        if($category == 0) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);
        
        $list = $model
            ->alias('p')
            ->field('p.id,p.name,p.category,p.isXg,p.price,p.stock,p.market_price,p.unit,p.spec,p.price_line,p.cover,p.sales,p.total_sales,p.sort,pc.title')
            ->join('product_category pc', 'p.category = pc.id', 'LEFT')
            ->where('p.status > 0 and p.category = ' . $category)
            ->order('p.sort ASC')
            ->limit(($page-1)*$limit, $limit)
            ->select();
        
        if(!empty($list)){
            foreach ($list as &$item){
                $item['cover'] = get_cover(explode(',', $item['cover'])[0], 'path');
            
                if(!empty($item['price_line'])){
                    $array = preg_split('/[,;\r\n]+/', trim($item['price_line'], ",;\r\n"));
            
                    if(strpos($item['price_line'],'|')){
                        $value  = array();
                        foreach ($array as $val) {
                            list($k, $v) = explode('|', $val);
                            $value[$k]   = $v;
                        }
            
                        $item['price_line'] = $value;
                    }
                }
            }
            
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$list, 'paginate'=>array('cate'=>$category, 'page'=>sizeof($list) < 10 ? $page : $page+1, 'limit'=>$limit)]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }

    /**
    * 商品详情
    * @date: 2018年6月6日 下午2:48:33
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function detail(Request $request){
        $model = db('product');
        $id = $request->param('id', 0, 'intval');
        
        if($id == 0) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);
        
        $info = $model
            ->alias('p')
            ->field('p.id,p.name,p.category,p.isXg,p.price,p.stock,p.market_price,p.unit,p.spec,p.content,p.price_line,p.cover,p.sales,p.total_sales,p.sort,pc.title')
            ->join('product_category pc', 'p.category = pc.id', 'LEFT')
            ->where('p.status > 0 and p.id = ' . $id)
            ->find();
        
        if(!empty($info)){
            if(!empty($info['cover'])){
                foreach (explode(',', $info['cover']) as $item){
                    $images[] = get_cover($item, 'path');
                }
                $info['cover'] = $images;
            }
            
            if(!empty($info['price_line'])){
                $array = preg_split('/[,;\r\n]+/', trim($info['price_line'], ",;\r\n"));
            
                if(strpos($info['price_line'],'|')){
                    $value  = array();
                    foreach ($array as $val) {
                        list($k, $v) = explode('|', $val);
                        $value[$k]   = $v;
                    }
            
                    $info['price_line'] = $value;
                }
            }
            
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$info]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }
    
    /**
    * 商品评价
    * @date: 2018年7月19日 下午2:23:40
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function goodRank(Request $request){
        $gid = $request->param('id', 0, 'intval');
        $rank = $request->param('rank', 1, 'intval');
        $limit = $request->param('limit', 10, 'intval');
        $page = $request->param('page', 1, 'intval');
        
        if($gid == 0) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);
        
        $commentsModel = new CommentsModel();
        $ranks = $commentsModel::all(function($query) use($gid,$rank,$page,$limit){
            $query->where('gid', $gid);
            $query->where('rank', $rank);
            $query->where('status', 1);
            $query->order('addtime desc');
            $query->limit(($page-1)*$limit, $limit);
        });
        
        if(!empty($ranks)){
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$ranks, 'paginate'=>array('page'=>sizeof($list) < 10 ? $page : $page+1, 'limit'=>$limit)]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }
    
    /**
    * 添加评价
    * @date: 2018年7月19日 下午2:24:12
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function addRank(Request $request){
        $gid = $request->param('gid', 0, 'intval');
        $uid = $request->param('uid', 0, 'intval');
        $rank = $request->param('rank', 1, 'intval');
        $message = $request->param('message', '', 'op_t');
        
        if($gid == 0 || $uid == 0 || empty($message)) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);
        
        $data['gid'] = $gid;
        $data['uid'] = $uid;
        $data['rank'] = $rank;
        $data['message'] = $message;
        $data['addtime'] = time();
        
        $commentsModel = new CommentsModel();
        $commentsModel->data($data);
        
        if($commentsModel->save()){
            return json(['code'=>0,'msg'=>'评价成功']);
        }else{
            return json(['code'=>1,'msg'=>'评价失败']);
        }
    }
}