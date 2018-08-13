<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\OrderModel;

class SearchController extends Controller{

    public function index(Request $request){
        $keyword = $request->param('keyword');
        $uid = $request->param('uid', 0, 'intval');
        if(empty($keyword))  return json(['code'=>1,'msg'=>'请输入搜索关键词']);

        $map = "name like '%".trim($keyword)."%'";

        $product_list = db("product")->where($map)->order("sort asc")->select();

        if($product_list){

            //判断当天限购   计算出我当天购买过的商品
            $my_pay_product_count = [];
            $orderModel = new OrderModel();
            $myOrder = $orderModel::all(function($query) use($uid){
                $query->where('uid', $uid);
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

            foreach ($product_list as &$item){
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

                //判断已经购买的商品是否存在该用户不能购买的
                if($item['isXg'] == 1 && $my_pay_product_count[$item['id']] > 0){
                    $item['myXg'] = 1;
                }else{
                    $item['myXg'] = 0;
                }
            }
            return json(['code'=>0,'msg'=>'success!','data'=>$product_list]);
        }else{
            return json(['code'=>1,'msg'=>'没有搜索到相关的商品']);
        }

    }
}