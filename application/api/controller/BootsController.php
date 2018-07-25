<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\OrderModel;
use app\common\model\ProductModel;
use app\common\model\FundsModel;

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
                $query->where('create_time', 'between', [$priv_time-86400, $priv_time]);
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
                $query->where('create_time', 'between', [$priv_time-86400, $priv_time]);
            });
            
            if($is_update) {
                $this->writeGetDataLog('将待收货切换为已收货成功');
            }else{
                $this->writeGetDataLog('将待收货切换为已收货失败');                
            }
        }
    }
    
    /**
    * 生成退款明细数据
    * @date: 2018年7月24日 上午11:51:59
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function fund_change(){
        /* 读取数据库中的配置 */
        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置        
        
        $fund_time = config('FUND_TIME');
        $curr = date('H:i');
        $priv_time = strtotime(date('Y-m-d 0:0:0'));
        if($curr === $fund_time){
            //先把当天的商品数据获取到    获取到当天销量、价格阶段
            $prices = [];
            $funds = [];
            
            $productModel = new ProductModel();
            $products = $productModel::all(function($query){
                $query->field("id,price,price_line,sales");
                $query->where('status', 1);
            });
            if($products){
                foreach ($products as $product){
                    $curr_price = $product['price'];
                    if(!empty($product['price_line'])){
                        $array = preg_split('/[,;\r\n]+/', trim($product['price_line'], ",;\r\n"));
                    
                        if(strpos($product['price_line'],'|')){
                            foreach ($array as $val) {
                                list($k, $v) = explode('|', $val);
                                if($product['sales'] >= $k){
                                    $curr_price = number_format($v,2,".","");
                                }
                            }
                        }
                    }
                    $prices[$product['id']] = ['price'=>$product['price'], 'curr_price'=>$curr_price, 'sales'=>$product['sales']];
                }
                

                //获取到当天的所有订单，并把所涉及到的用户分组，每个用户内的商品进行处理
                $orderModel = new OrderModel();
                $orders = $orderModel::all(function($query) use($priv_time){
                    $query->where('status', 4);
                    $query->where('create_time', 'between', [$priv_time, $priv_time+86400]);
                });
                
                if($orders){
                    foreach ($orders as $order){
                        $order['product_info'] = json_decode($order['product_info'], true);
                        
                        if(!isset($funds[$order['uid']])){
                            $funds[$order['uid']]['uid'] = $order['uid'];
                            $funds[$order['uid']]['date'] = date('Ymd');
                            $funds[$order['uid']]['date_str'] = date('Y年m月d日');

                            foreach ($order['product_info'] as $item){
                                $item['sales'] = $prices[$item['id']]['sales'];
                                $item['curr_price'] = $prices[$item['id']]['curr_price'];
                                $item['num'] = intval($item['num']);
                                
                                $funds[$order['uid']]['product_info'][$item['id']] = $item;
                            }
                        }else{
                            foreach ($order['product_info'] as $item){
                                if(isset($funds[$order['uid']]['product_info'][$item['id']])){
                                    $funds[$order['uid']]['product_info'][$item['id']]['num'] += $item['num'];
                                }else{
                                    $item['sales'] = $prices[$item['id']]['sales'];
                                    $item['curr_price'] = $prices[$item['id']]['curr_price'];
                                    $item['num'] = intval($item['num']);
                                    
                                    $funds[$order['uid']]['product_info'][$item['id']] = $item;
                                }
                            } 
                        }
                    }
                }
                
                
                //开始生成数据
                if(sizeof($funds) > 0){
                    $fundsModel = new FundsModel();
                    
                    $fundsList = [];
                    foreach ($funds as $fund){
                        $info = $fundsModel::get(function($query) use($fund){
                            $query->where('uid', $fund['uid']);
                            $query->where('date', $fund['date']);
                        });
                        
                        if(empty($info)){
                            $data = array();
                            $data['uid'] = $fund['uid'];
                            $data['date'] = $fund['date'];
                            $data['date_str'] = $fund['date_str'];
                            $data['product_info'] = json_encode($fund['product_info']);
                            $data['addtime'] = time();
                            
                            $fundsList[] = $data;unset($data);
                        }
                    }
                    
                    if($fundsModel->saveAll($fundsList)){
                        $this->writeGetDataLog('生成用户退款明细数据失败');
                    }else{
                        $this->writeGetDataLog('生成用户退款明细数据成功');
                    }
                }
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