<?php
namespace app\api\controller;

use think\Controller;
use think\Request;

class CategoryController extends Controller{

    /**
    * 返回商品分类
    * @date: 2018年6月5日 下午2:59:10
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function  category(Request $request){
        $model = db('product_category');
        $list = $model->where('status > 0')->order('sort asc')->select();
        
        if($list){
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$list]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }
}