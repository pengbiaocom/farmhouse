<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\OrderModel;
use app\common\model\ProductModel;
use app\common\model\CouponModel;
use app\common\model\UcenterMemberModel;
use think\Db;

class BootsController extends Controller{
    private $config = [
       'appid'=>'wxa6737565830cae42',
       'pay_mchid'=>'1509902681',
       'pay_apikey'=>'6ba57bc32cfd5044f8710f09ff86c664'
    ];
    
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
        
        if(!empty($uid)){
            $couponModel = new CouponModel();
            $userCoupon = $couponModel::get(function($query) use($uid){
                $query->where('uid', $uid);
            });
            
            if($userCoupon->uid > 0){
                if($couponModel->where('uid', $uid)->setInc('coupon_num', 1)){
                    return json(['code'=>0, 'msg'=>'调用成功', 'data'=>[]]);
                }else{
                    return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
                }
            }else{
                if($couponModel->save(['uid'=>$uid, 'coupon_num'=>1])){
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
            $productModel = new ProductModel();
            $productModel->save(['sales'=>0], function($query){
                $query->where('id', '>', 0);
            });

            $orderModel = new OrderModel();
            $priv_time = strtotime(date('Ymd'));
            $is_update = $orderModel->save(['status'=>2],function($query) use($priv_time){
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
    * 已完成的变化-----订单处理[同时要将超过7天的未评价的订单修改为已完成]
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
            
            //处理超过7天前的未评价订单
            $priv_time = strtotime(date('Ymd'));
            $orderModel->save(['status'=>5],function($query) use($priv_time){
                $query->where('status',3);
                $query->where('create_time', '<', $priv_time-86400*7);
            });
            
            $is_update = $orderModel->save(['status'=>3],function($query) use($priv_time){
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
    * 生成退款明细数据[同时为每个订单刷入退款金额，并将未付款的订单销毁]
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
            $buyInitScale = config('BUY_INIT_SCALE');//基础
            $buyIncScale = config('BUY_INC_SCALE');//增幅
            $buyMaxScale = config('BUY_MAX_SCALE');//最大
            $invitInitScale = config('INVIT_INIT_SCALE');//基础
            $invitIncScale = config('INVIT_INC_SCALE');//增幅
            $invitMaxScale = config('INVIT_MAX_SCALE');//最大
       
            $boef_time = strtotime(date('Ymd'));
            $orderModel = new OrderModel();
            $orders = $orderModel::all(function($query) use($boef_time){
                $query->alias('order');
                $query->join('__UCENTER_MEMBER__ user', 'order.uid = user.id', 'left');
                $query->where('order.status', '>', 0);
                $query->where('order.create_time', 'between', [$boef_time,$boef_time+86400]);//今天的数据
                $query->group('order.uid');
            });
            
            $invit_dis = [];
            foreach ($orders as $order){
                /* 计算购买返利  */
                $ucenterMemberModel = new UcenterMemberModel();
                $users = $ucenterMemberModel::all(function($query) use($order,$boef_time){
                   $query->field('user.continuity_buy, sum(order.total_fee) as total_fee, FROM_UNIXTIME(order.create_time,"%Y%m%d") as create_date');
                   $query->alias('user');
                   $query->join('__ORDER__ order', 'user.id = order.uid', 'left');
                   $query->where('user.id', $order['uid']);
                   $query->where('order.status', '>', 0);
                   $query->where('order.create_time', 'between', [$boef_time-172800,$boef_time]);
                   $query->order('create_date DESC');
                   $query->group('FROM_UNIXTIME(order.create_time,"%Y%m%d")');
                });
                
                $buy_money = '0.00';
                foreach ($users as $user){
                    if($user['continuity_buy'] == 0){
                        $buy_money = '0.00';
                    } else {
                        $rebate = $buyInitScale + $user['continuity_buy']*$buyIncScale;
                        $buy_rebate = min($rebate, $buyMaxScale);
                        $buy_money = sprintf("%.2f", $user['total_fee']*$buy_rebate/100);
                    }
                
                    if($user['total_fee'] > 0) break;
                }
                
                //开始发放购买奖励
                $this->profit($order['openid'], $buy_money, 1);
                
                
                /* 计算邀请返利 */
                if($order['invit'] > 0){
                    $invits = $ucenterMemberModel::all(function($query) use($order,$boef_time){
                        $query->field('user.invit_time, order.total_fee, order.create_time');
                        $query->alias('user');
                        $query->join('__ORDER__ order', 'user.id = order.uid', 'left');
                        $query->where('user.invit', $order['invit']);
                        $query->where('order.status', '>', 0);
                        $query->where('order.create_time', '>', $boef_time);
                    });
                    
                    $today_invit_count = 0;
                    $today_invit_consumption = 0;
                    foreach ($invits as $invit){
                        if($invit['invit_time'] == $boef_time && !empty($invit)) $today_invit_count += 1;
                        
                        if(strtotime(date('Ymd',$invit['create_time'])) == $boef_time) $today_invit_consumption += $invit['total_fee'];
                    }
                
                    if($today_invit_count == 0){
                        $invit_money = sprintf("%.2f", $today_invit_consumption*5/100);
                    }else{
                        $invit_rebate = min($invitInitScale+($today_invit_count-1)*$invitIncScale, $invitMaxScale);
                        $invit_money = sprintf("%.2f", $today_invit_consumption*$invit_rebate/100);
                    }
                
                    //开始发放邀请奖励
                    if(!in_array($order['invit'], $invit_dis)){
                        $openid = $ucenterMemberModel->where('id', $order['invit'])->value('openid');
                        $this->profit($openid, $invit_money, 2);
                        $invit_dis[] = $order['invit'];
                    }
                }
            }
            
            
            //获取到当天的所有订单，并把所涉及到的用户分组，每个用户内的商品进行处理
            $orderModel = new OrderModel();
            $orders = $orderModel::all(function($query) use($boef_time){
                $query->where('create_time', '>', $boef_time);
            });
            
            if($orders){
                foreach ($orders as $order){
                    if($order['status'] > 0){
                        $order['product_info'] = json_decode($order['product_info'], true);
        
                        //刷入应退金额
                        $fund_fee = 0;
                        if(!isset($funds[$order['uid']])){
                            $funds[$order['uid']]['uid'] = $order['uid'];
                            $funds[$order['uid']]['date'] = date('Ymd');
                            $funds[$order['uid']]['date_str'] = date('Y年m月d日');
        
                            foreach ($order['product_info'] as $item){
                                $fund_fee += ($item['price'] - $prices[$item['id']]['curr_price'])*$item['num'];//价格差乘以数量为当前商品的退还
        
                                $item['sales'] = $prices[$item['id']]['sales'];
                                $item['curr_price'] = $prices[$item['id']]['curr_price'];
                                $item['num'] = intval($item['num']);
        
                                $funds[$order['uid']]['product_info'][$item['id']] = $item;
                            }
                        }else{
                            foreach ($order['product_info'] as $item){
                                $fund_fee += ($item['price'] - $prices[$item['id']]['curr_price'])*$item['num'];//价格差乘以数量为当前商品的退还
        
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
        
                        //更改订单中的应退款金额
                        $orderModel->where('id', $order['id'])->update(['refund_fee'=>$fund_fee]);
                    }else if($order['status'] == 0){
                        //这里是拿来做销毁的
                        if($orderModel::destroy($order['id'])){
                            $goods = json_decode($order['product_info'], true);
                            foreach ($goods as $good){
                                $productModel = new ProductModel();
                                $productModel->where('id', $good['id'])->setInc('stock', $good['num']);
                            }
                        }
                    }
                }
            }
        }
    }
    
    /**
     * 收益分成
     * @date: 2018年7月27日 上午9:00:18
     * @author: onep2p <324834500@qq.com>
     * @param: variable
     * @return:
     */
    private function profit($openid, $money, $type){
        $config = $this->config;

        if($money > 0){
            $money = $money < 0.3 ? 0.3 : $money;
            $refunddorder = array(
                'mch_appid'			=> $config['appid'],
                'mchid'		=> $config['pay_mchid'],
                'nonce_str'		=> self::getNonceStr(),
                'partner_trade_no'	=> md5($openid.$type.strtotime(date('Ymd'))),
                'openid' => $openid,//退款唯一单号，系统生成
                'check_name' => 'NO_CHECK',
                'amount' => $money * 100,//退款金额,通过计算得到要退还的金额
                'desc' => $type == 1 ? '购买收益' : '邀请收益',
                'spbill_create_ip' => get_client_ip()
            );
    
            $refunddorder['sign'] = self::makeSign($refunddorder);
    
            //请求数据
            $xmldata = self::array2xml($refunddorder);
            $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
            $res = self::postXmlCurl($xmldata, $url, true);
            $resData = $this->xml2array($res);
            
            if($resData['return_code'] === 'SUCCESS' && $resData['result_code'] === 'SUCCESS'){
                //录入支付数据
                if(Db::table("cms_profit")->where('partner_trade_no', $resData['partner_trade_no'])->count() == 0){
                    Db::table("cms_profit")->insert(['openid'=>$openid, 'profit_type'=>$type, 'money'=>$money, 'profit_time'=>strtotime(date('Ymd')), 'partner_trade_no'=>$resData['partner_trade_no']]);
                    Db::table("cms_ucenter_member")->where('openid', $openid)->setInc('tal_profit', $money);
                }
                
                return true;
            }
            
            return false;
        }
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    protected function getNonceStr($length = 32) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }
    
    /**
     * 生成签名
     * @return 签名
     */
    protected function makeSign($data){
        //获取微信支付秘钥
        $key = $this->config['pay_apikey'];
        // 去空
        $data=array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a=http_build_query($data);
        $string_a=urldecode($string_a);
        //签名步骤二：在string后加入KEY
        //$config=$this->config;
        $string_sign_temp=$string_a."&key=".$key;
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        // 签名步骤四：所有字符转为大写
        $result=strtoupper($sign);
        return $result;
    }

    /**
     * 将一个数组转换为 XML 结构的字符串
     * @param array $arr 要转换的数组
     * @param int $level 节点层级, 1 为 Root.
     * @return string XML 结构的字符串
     */
    protected function array2xml($arr, $level = 1) {
        $s = $level == 1 ? "<xml>" : '';
        foreach($arr as $tagname => $value) {
            if (is_numeric($tagname)) {
                $tagname = $value['TagName'];
                unset($value['TagName']);
            }
            if(!is_array($value)) {
                $s .= "<{$tagname}>".(!is_numeric($value) ? '<![CDATA[' : '').$value.(!is_numeric($value) ? ']]>' : '')."</{$tagname}>";
            } else {
                $s .= "<{$tagname}>" . $this->array2xml($value, $level + 1)."</{$tagname}>";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
        return $level == 1 ? $s."</xml>" : $s;
    }
    
    /**
     * 将xml转为array
     * @param  string 	$xml xml字符串
     * @return array    转换得到的数组
     */
    protected function xml2array($xml){
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $result= json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $result;
    }    

	/**
	 * 以post方式提交xml到对应的接口url
	 * 
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @throws WxPayException
	 */
	private static function postXmlCurl($xml, $url, $useCert = false, $second = 30)
	{		
		$ch = curl_init();
		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
		
		//设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		
		//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
		if($useCert == true){
			//设置证书
			//使用证书：cert 与 key 分别属于两个.pem文件
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, './cert/apiclient_cert.pem');
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, './cert/apiclient_key.pem');
		}
		
		//post提交方式
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		
		//运行curl
		$data = curl_exec($ch);
		
		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else { 
			$error = curl_errno($ch);
			curl_close($ch);
			throw new WxPayException("curl出错，错误码:$error");
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