<?php
namespace app\api\controller;

use think\Controller;
use think\Request;

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
    
    /**
    * 分析获得优惠券
    * @date: 2018年7月3日 下午2:42:16
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function share(Request $request){
        $uid = $request->param('uid', '', 'intval');
        
        if(!empty($uid)){
            $model = db('coupon', [], false);
            if($num = $model->where('uid', '=', $uid)->value('coupon_num')){
                if($model->where('uid', '=', $uid)->update(['coupon_num'=>$num+1])){
                    return json(['code'=>0, 'msg'=>'调用成功', 'data'=>[]]);
                }else{
                    return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
                }
            }else{
                if($model->insert(['uid'=>$uid, 'coupon_num'=>1])){
                    return json(['code'=>0, 'msg'=>'调用成功', 'data'=>[]]);
                }else{
                    return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
                }
            }
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }
}