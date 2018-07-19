<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\CurlModel;
use app\common\model\ProductModel;
use app\common\model\OrderModel;
use app\common\model\CouponModel;
use think\Db;
use app\common\model\UcenterMemberModel;

class OrderController extends Controller{
    private $wx_key = "";//申请支付后有给予一个商户账号和密码，登陆后自己设置key
    private $appid = 'wx25fdd247f54f5841';//小程序id
    
    public function create_order(Request $request){
        //接收订单信息
        $uid = $request->param('uid', '', 'intval');
        $product_info = $request->param('product_info/a');
        $address_id = $request->param('address_id', '', 'intval');
        $coupon_num = $request->param('coupon_num', 0, 'intval');
        $remark = $request->param('remark', '', 'op_t');
        
        if(empty($uid) || empty($product_info) || empty($address_id)) return json(['code'=>1, 'msg'=>'调用失败', 'data'=>['info'=>'参数错误']]);
        
        //分析订单数据
        $total_fee = $this->total_fee($uid, $product_info, $address_id, $coupon_num, $remark);
        if($total_fee == -3) return json(['code'=>1, 'msg'=>'调用失败', 'data'=>['info'=>'订单中不存在购买商品']]);
        if($total_fee == -2) return json(['code'=>1, 'msg'=>'调用失败', 'data'=>['info'=>'存在限购商品或者某商品库存不足']]);
        if($total_fee == -1) return json(['code'=>1, 'msg'=>'调用失败', 'data'=>['info'=>'下单失败']]);
        if($total_fee == 0) return json(['code'=>1, 'msg'=>'调用失败', 'data'=>['info'=>'参数异常']]);
        
        return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$total_fee]);
    }
    
    /**
    * 预支付
    * @date: 2018年7月9日 下午2:18:42
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function payment(Request $request){
        $out_trade_no = $request->param('out_trade_no', '', 'op_t');
        $uid = $request->param('uid', '', 'intval');
        
        //查询数据，进行预支付
        $orderModel = new OrderModel();
        $order = $orderModel::get(function($query) use($out_trade_no){
            $query->where('out_trade_no', $out_trade_no);
        });
        
        $ucenterMemberModel = new UcenterMemberModel();
        $user = $ucenterMemberModel::get(function($query) use($uid){
            $query->where('uid', $uid);
        });
        
        //这里是按照顺序的 因为下面的签名是按照顺序 排序错误 肯定出错
        $post['appid'] = $this->appid;
        $post['body'] = "益丰农舍-商品购买";//描述
        $post['mch_id'] = "";//商户号
        $post['nonce_str'] = $this->nonce_str();//随机字符串
        $post['notify_url'] = "";//回调地址自己填写
        $post['openid'] = $user->openid;//用户在商户appid下的唯一标识
        $post['out_trade_no'] = $order->out_trade_no;//商户订单号
        $post['spbill_create_ip'] = get_client_ip();//终端的ip
        $post['total_fee'] = $order->total_fee;//因为充值金额最小是1 而且单位为分 如果是充值1元所以这里需要*100
        $post['trade_type'] = "JSAPI";//交易类型 默认
        $sign = $this->sign($post);//签名
        
        
        $post_xml = '<xml>
           <appid>'.$post['appid'].'</appid>
           <body>'.$post['body'].'</body>
           <mch_id>'.$post['mch_id'].'</mch_id>
           <nonce_str>'.$post['nonce_str'].'</nonce_str>
           <notify_url>'.$post['notify_url'].'</notify_url>
           <openid>'.$post['openid'].'</openid>
           <out_trade_no>'.$post['out_trade_no'].'</out_trade_no>
           <spbill_create_ip>'.$post['spbill_create_ip'].'</spbill_create_ip>
           <total_fee>'.$post['total_fee'].'</total_fee>
           <trade_type>'.$post['trade_type'].'</trade_type>
           <sign>'.$sign.'</sign>
        </xml> ';
        
        $curlModel = new CurlModel();
        $curlModel->set_ssl_host(true);
        $curlModel->set_ssl_peer(true);
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $xml = $curlModel->post_single($url,$post_xml);
        var_dump($xml);
    }
    
    /**
    * 计算提交订单最终总价
    * @date: 2018年7月4日 上午9:41:29
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    private function total_fee($uid, $product_info, $address_id, $coupon_num, $remark){
        $user = Db::table('__UCENTER_MEMBER__')->alias('um')->field("um.id,um.username,c.coupon_num")->join('__COUPON__ c', 'um.id = c.uid', 'LEFT')->where('um.id = ' . $uid)->find();
        
        /* 读取数据库中的配置 */
        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置
        
        $freight = config('FREIGHT_QUOTA');//默认为0，没有满免
        $coupon_max = config('COUPON_MAXCOUNT');//最大使用优惠券的数量   默认为5
        $coupon_price = config('COUPON_DENOMINATION');//没设置的情况下默认为1分钱
        $coupon_num = intval($coupon_num);
        
        if(!empty($user['id']) && $user['id'] != 1 && $coupon_num <= $coupon_max && $coupon_num <= $user['coupon_num']){
            $total_fee = 0;//订单总价
            $productList = [];//商品信息
            $order_data = [];//订单数据
            
//            $product_info = json_decode($product_info, true);
            $productArr = [];
            $productIds = [];
            if(sizeof($product_info) > 0){
                foreach ($product_info as $key=>$val){
                    $productArr[$val['good_id']] = $val['num'];
                    $productIds[] = $val['good_id'];
                }
            }else{
                return -3;
            }
            
            
            $productModel = new ProductModel();
            $products = $productModel::all(function($query) use($productIds){
                $query->where('id', 'in', $productIds);
            });
            
            $productModel->startTrans();
            foreach($products as $item=>$product){
                //判断限购、库存
                if($product->stock < $productArr[$product->id] || ($product->isXg == 1 && $productArr[$product->id] > 1)){
                    $productModel->rollback();
                    return -2;
                }else{
                    if(!$productModel->where('id', $product->id)->setDec('stock', $productArr[$product->id])){
                        $productModel->rollback();
                    }
                }
                
                $total_fee += $product->price*$productArr[$product->id];
                
                $productList[] = [
                    'id'=>$product->id,
                    'name'=>$product->name,
                    'cover'=>get_cover(explode(',', $item['cover'])[0], 'path'),
                    'price'=>$product->price,
                    'num'=>$productArr[$product->id]
                ];
            }
            
            $order_data['uid'] = $uid;
            $order_data['product_info'] = json_encode($productList);
            $order_data['coupon'] = $coupon_num;
            $order_data['address_id'] = $address_id;
            $order_data['remark'] = $remark;
            $order_data['create_time'] = time();
            $order_data['out_trade_no'] = 'YF'.date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);;

            //处理运费满减
            if($total_fee < $freight) {
                $total_fee += 2;//如果总价没到满免运费的情况下，统一收取2元的运费
                $order_data['freight'] = 2;
            }
            
            //处理优惠券
            $couponModel = new CouponModel();
            $couponModel->startTrans();
            if($coupon_num > 0) {
                if(!$couponModel->where('uid', $uid)->setDec('coupon_num', $coupon_num)){
                    $couponModel->rollBack();
                    $productModel->rollback();
                }
                
                $total_fee -= $coupon_num*$coupon_price;
            }
            
            $order_data['total_fee'] = $total_fee;
            
            $orderModel = new OrderModel();
            $orderModel->data($order_data);
            
            $orderModel->startTrans();
            if($order_id = $orderModel->save()){
                $orderModel->commit();
                $couponModel->commit();
                $productModel->commit();
                return ['total_fee'=>$total_fee, 'out_trade_no'=>$order_data['out_trade_no']];
            }else{
                $orderModel->rollback();
                $couponModel->rollBack();
                $productModel->rollback();
                return -1;
            }
        }else{
            return 0;//错误的用户ID
        }
    }
    
    /**
    * 生成随机字符串
    * @date: 2018年7月4日 上午9:09:32
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    private function nonce_str(){
        $result = '';
        $str = 'QWERTYUIOPASDFGHJKLZXVBNMqwertyuioplkjhgfdsamnbvcxz';
        for ($i=0;$i<32;$i++){
            $result .= $str[rand(0,48)];
        }
        
        return $result;
    }
    
    /**
    * 签名函数
    * @date: 2018年7月4日 上午9:10:06
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    private function sign($data){
        $stringA = '';
        foreach ($data as $key=>$value){
            if(!$value) continue;
            if($stringA) $stringA .= '&'.$key."=".$value;
            else $stringA = $key."=".$value;
        }
        
        $wx_key = $this->wx_key;
        $stringSignTemp = $stringA.'&key='.$wx_key;
        return strtoupper(md5($stringSignTemp));
    }
}