<?php
namespace app\api\controller;

use think\Controller;
use think\Request;

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
            ->field('p.id,p.name,p.category,p.price,p.market_price,p.unit,p.spec,p.price_line,p.cover,p.sort,pc.title')
            ->join('product_category pc', 'p.category = pc.id', 'LEFT')
            ->where('p.status > 0 and p.category = ' . $category)
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
            
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$list, 'paginate'=>array('cate'=>$category, 'page'=>sizeof($limit) < 10 ? $page : $page+1, 'limit'=>$limit)]);
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
            ->field('p.id,p.name,p.category,p.price,p.market_price,p.unit,p.spec,p.price_line,p.cover,p.sort,pc.title')
            ->join('product_category pc', 'p.category = pc.id', 'LEFT')
            ->where('p.status > 0 and p.id = ' . $id)
            ->find();
        
        if(!empty($info)){
            $info['cover'] = get_cover(explode(',', $info['cover'])[0], 'path');
            
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
}