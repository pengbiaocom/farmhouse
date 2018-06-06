<?php
namespace app\api\controller;

use think\Controller;

class BootsController extends Controller{
    /**
    * 启动配置接口
    * @date: 2018年6月6日 下午3:38:53
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function index(){
        $config = controller('common/ConfigApi')->lists();
        
        if($config){
            //可以处理需要的数据
            
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$config]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }
}