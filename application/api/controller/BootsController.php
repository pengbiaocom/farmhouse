<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\OrderModel;

class BootsController extends Controller{
    /**
    * 启动配置接口
    * @date: 2018年6月6日 下午3:38:53
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function index(){
        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置        
        
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
        $uid = intval($uid);
        
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

    /**
     * 获取用户 优惠券
     * @param Request $request
     * @return \think\response\Json
     * User: 离殇<pengxuancom@163.com>
     */
    public function get_share(Request $request){

        $uid = $request->param('uid');

        if(empty($uid))  return json(['code'=>1,'msg'=>'缺少参数']);

        $num = db("coupon")->where(['uid'=>$uid])->value("coupon_num");
        if($num){
            return json(['code'=>0,'msg'=>'success','data'=>$num]);
        }else{
            return json(['code'=>1,'msg'=>'没有数据']);
        }
    }
    
    /**
    * 待收货的变化-----订单处理
    * @date: 2018年7月16日 上午9:01:30
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function collect_change(){
        /* 读取数据库中的配置 */
        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置
        
        
        $collect_time = config('COLLECT_TIME');
        $curr_time = date('H:i');
        
        //检验时间正确,开始执行从待发货到待收货的变化
        if($collect_time === $curr_time){
            $orderModel = new OrderModel();
            
            $is_update = $orderModel->save(['status'=>2],function($query){
                $query->where('status',1);
            });
            
            if($is_update) {
                $this->writeGetDataLog('将待发货切换为待收货成功');
            }else{
                $this->writeGetDataLog('将待发货切换为待收货失败');
            }
        }
    }
    
    /**
    * 已完成的变化-----订单处理
    * @date: 2018年7月16日 上午9:02:03
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function complete_change(){
        /* 读取数据库中的配置 */
        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置
        
        
        $complete_time = config('COMPLETE_TIME');
        $curr_time = date('H:i');
        
        //检验时间正确,开始执行从待发货到待收货的变化
        if($complete_time === $curr_time){
            $orderModel = new OrderModel();
            
            $is_update = $orderModel->save(['status'=>3],function($query){
                $query->where('status',2);
            });
            
            if($is_update) {
                $this->writeGetDataLog('将待收货切换为已收货成功');
            }else{
                $this->writeGetDataLog('将待收货切换为已收货失败');                
            }
        }
    }
    
    /**
     * 抓取数据日志写入
     * @param string $content 待写入的内容
     * @param string $root 下级目录
     * @param string $name 文件名
     */
    public function writeGetDataLog($content,$root='',$name=''){
        $filename = date('Ymd').$name.'.txt';
        $fileContent = date('Y-m-d H:i:s').': '.$content."\r\n";
    
        //文件夹不存在先创建目录
        $savePath = "./getDataLog";
        if(!empty($root)) $savePath = "./getDataLog/".$root;
        if(!file_exists($savePath)) mkdir($savePath,0777,true);
    
        $fp=fopen($savePath.'/'.$filename, "a+");
        fwrite($fp,$fileContent);
        fclose($fp);
    }    
}