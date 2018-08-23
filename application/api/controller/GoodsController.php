<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\OrderModel;

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
        $uid = $request->param('uid', 0, 'intval');
        
        if($page == 'undefined' || $page == '') $page = 1;
        
        if($category == 0) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);
        
        $list = $model
            ->alias('p')
            ->field('p.id,p.name,p.category,p.isXg,p.price,p.stock,p.market_price,p.unit,p.spec,p.price_line,p.cover,p.sales,p.total_sales,p.sort,pc.title')
            ->join('product_category pc', 'p.category = pc.id', 'LEFT')
            ->where('p.status > 0 and stock > 0 and p.category = ' . $category)
            ->order('p.sort ASC')
            ->limit(($page-1)*$limit, $limit)
            ->select();
        
        if(!empty($list)){
            //判断当天限购   计算出我当天购买过的商品
            $my_pay_product_count = [];
            $orderModel = new OrderModel();
            $myOrder = $orderModel::all(function($query) use($uid){
                $query->where('uid', $uid);
                $query->where('status', '>', 0);
                $query->where('create_time' , '>', strtotime(date('Y-m-d 0:0:0')));
            });
            foreach ($myOrder as $order){
                $products = json_decode($order['product_info'], true);
                foreach ($products as $product){
                    if(isset($product['id'])){
                        $my_pay_product_count[$product['id']] = $product['num'];
                    }else{
                        $my_pay_product_count[$product['id']] += $product['num'];
                    }
                }
            }
            
            foreach ($list as &$item){
                $item['cover'] = get_cover(explode(',', $item['cover'])[0], 'path');
            
                if(!empty($item['price_line'])){
                    $array = preg_split('/[,;\r\n]+/', trim($item['price_line'], ",;\r\n"));
            
                    if(strpos($item['price_line'],'|')){
                        $value  = array();
                        foreach ($array as $val) {
                            list($k, $v) = explode('|', $val);
                            $value[$k]   = number_format($v,2,".","");
                        }
            
                        $item['price_line'] = $value;
                    }
                }
                
                //判断已经购买的商品是否存在该用户不能购买的
                if($item['isXg'] == 1 && $my_pay_product_count[$item['id']] > 0){
                    $item['myXg'] = 1;
                }else{
                    $item['myXg'] = 0;
                }
            }
            
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$list, 'paginate'=>array('cate'=>$category, 'page'=>sizeof($list) < $limit ? $page : $page+1, 'limit'=>$limit)]);
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
        $uid = $request->param('uid', 0, 'intval');
        
        if($id == 0) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);
        
        $info = $model
            ->alias('p')
            ->field('p.id,p.name,p.category,p.isXg,p.price,p.stock,p.market_price,p.unit,p.spec,p.content,p.price_line,p.cover,p.sales as total_sales,p.total_sales as sales,p.sort,pc.title')
            ->join('product_category pc', 'p.category = pc.id', 'LEFT')
            ->where('p.status > 0 and stock > 0 and p.id = ' . $id)
            ->find();
        
        if(!empty($info)){
            //判断当天限购   计算出我当天购买过的商品
            $my_pay_product_count = [];
            $orderModel = new OrderModel();
            $myOrder = $orderModel::all(function($query) use($uid){
                $query->where('uid', $uid);
                $query->where('status', '>', 0);
                $query->where('create_time' , '>', strtotime(date('Y-m-d 0:0:0')));
            });
            foreach ($myOrder as $order){
                $products = json_decode($order['product_info'], true);
                foreach ($products as $product){
                    if(isset($product['id'])){
                        $my_pay_product_count[$product['id']] = $product['num'];
                    }else{
                        $my_pay_product_count[$product['id']] += $product['num'];
                    }
                }
            }            
            
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
                        $value[$k]   = number_format($v,2,".","");
                    }
            
                    $info['price_line'] = $value;
                }
            }
                
            //判断已经购买的商品是否存在该用户不能购买的
            if($info['isXg'] == 1 && $my_pay_product_count[$info['id']] > 0){
                $info['myXg'] = 1;
            }else{
                $info['myXg'] = 0;
            }
            
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$info]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }
}