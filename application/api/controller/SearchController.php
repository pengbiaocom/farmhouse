<?php
namespace app\api\controller;

use think\Controller;
use think\Request;

class SearchController extends Controller{

    public function index(Request $request){
        $keyword = $request->param('keyword');
        if(empty($keyword))  return json(['code'=>1,'msg'=>'请输入搜索关键词']);

        $map = "name like '%".trim($keyword)."%'";

        $product_list = db("product")->where($map)->order("sort asc")->select();

        if($product_list){
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
            }
            return json(['code'=>0,'msg'=>'success!','data'=>$product_list]);
        }else{
            return json(['code'=>1,'msg'=>'没有搜索到相关的商品']);
        }

    }
}