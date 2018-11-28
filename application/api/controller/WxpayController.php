<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/23
 * Time: 10:28
 */
namespace app\api\controller;
use think\Controller;
use think\Request;
use think\Db;
use app\common\model\OrderModel;
use app\common\model\UcenterMemberModel;

class WxpayController extends Controller{

    protected function _initialize(){

        //微信支付参数配置(appid,商户号,支付秘钥)
        $config = [
            'appid'=>'wxa6737565830cae42',
            'pay_mchid'=>'1509902681',
            'pay_apikey'=>'6ba57bc32cfd5044f8710f09ff86c664'
        ];

		$this->config = $config;
	}

    function get_curr_time_section()
    {
        $checkDayStr = date('Y-m-d ',time());
        $timeBegin1 = strtotime($checkDayStr."08:00".":00");
        $timeEnd1 = strtotime($checkDayStr."20:00".":00");

        $curr_time = time();

        if($curr_time >= $timeBegin1 && $curr_time <= $timeEnd1)
        {
            return 1;
        }

        return 0;
    }


    /**
     * 预支付请求接口(POST)
     * @param string $openid 	openid
     * @param string $body 		商品简单描述
     * @param string $order_sn  订单编号
     * @param string $total_fee 金额
     * @return  json的数据
     */
    public function prepay(Request $request){
        $result = $this->get_curr_time_section();
        if($result==0)  return json(['code'=>0,'msg'=>'订单支付时间在8:00到20：00之间']);
        $config = $this->config;
        $uid = $request->param('uid', '', 'intval');
        $out_trade_no = $request->param('out_trade_no', '', 'op_t');

        //查询数据，进行预支付
        $orderModel = new OrderModel();
        $order = $orderModel::get(function($query) use($out_trade_no,$uid){
            $query->where('out_trade_no', $out_trade_no);
            $query->where('uid', $uid);
        });

        $ucenterMemberModel = new UcenterMemberModel();
        $user = $ucenterMemberModel::get(function($query) use($uid){
            $query->where('id', $uid);
        });


        //统一下单参数构造
        $unifiedorder = array(
            'appid'			=> $config['appid'],
            'mch_id'		=> $config['pay_mchid'],
            'nonce_str'		=> self::getNonceStr(),
            'body'			=> '益丰众购-商品购买',
            'out_trade_no'	=> 'YF'.date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT),//每一次的发起支付都重新生成一下订单号，并替换数据库
            'total_fee'		=> $order->total_fee * 100,
            'spbill_create_ip'	=> get_client_ip(),
            'notify_url'	=> 'https://api.yifengzhonggou.com/api/Wxpay/notify',
            'trade_type'	=> 'JSAPI',
            'openid'		=> $user->openid
        );
        
        //更新数据库单号
        $orderModel::update(['out_trade_no'=>$unifiedorder['out_trade_no']], function($query) use($order){
            $query->where('id', $order->id);
        });
        

        $unifiedorder['sign'] = self::makeSign($unifiedorder);
        //请求数据
        $xmldata = self::array2xml($unifiedorder);
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $res = self::curl_post_ssl($url, $xmldata);
        if(!$res){
            self::return_err("Can't connect the server");
        }
        // 这句file_put_contents是用来查看服务器返回的结果 测试完可以删除了
        //file_put_contents(APP_ROOT.'/Statics/log1.txt',$res,FILE_APPEND);

        $content = self::xml2array($res);

        if(strval($content['result_code']) == 'FAIL'){
            self::return_err(strval($content['err_code_des']));
        }
        if(strval($content['return_code']) == 'FAIL'){
            self::return_err(strval($content['return_msg']));
        }

        if(!empty($content['prepay_id'])){
            return self::pay($content['prepay_id']);
        }else{
            return json(['code'=>0,'msg'=>'发起支付失败']);
        }
    }

    /**
     * 领取种子
     * @param Request $request
     * @return \think\response\Json
     */
    public function  ling_seeds(Request $request){
        $model = db("seeds_user");
        $sid = $request->param("sid",0,'intval');
        $uid = $request->param('uid',0,'intval');

        if($sid == 0)   return json(['code'=>1,'msg'=>'缺少参数']);

        $ucenterMemberModel = new UcenterMemberModel();
        $user = $ucenterMemberModel::get(function($query) use($uid){
            $query->where('id', $uid);
        });

        if($model->where(['uid'=>$uid,'status'=>0])->count()==0){
            $info = db("seeds")->where(['id'=>$sid])->find();
            $data['sid'] = $sid;
            $data['uid'] = $uid;
            $data['sorder_sn'] =  'YF'.date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $data['sum_exp'] = $info['sum_exp'];
            $data['status'] = 0;
            $data['pay_status'] = 0;
            $data['create_time'] = $data['update_time'] = time();
            if($id = $model->insertGetId($data)){
                $config = $this->config;
                //统一下单参数构造
                $unifiedorder = array(
                    'appid'			=> $config['appid'],
                    'mch_id'		=> $config['pay_mchid'],
                    'nonce_str'		=> self::getNonceStr(),
                    'body'			=> '益丰众购-种子购买',
                    'out_trade_no'	=> 'YF'.date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT),//每一次的发起支付都重新生成一下订单号，并替换数据库
                    'total_fee'		=> $info['price'] * 100,
                    'spbill_create_ip'	=> get_client_ip(),
                    'notify_url'	=> 'https://api.yifengzhonggou.com/api/Wxpay/notifys',
                    'trade_type'	=> 'JSAPI',
                    'openid'		=> $user->openid
                );
                //更新数据库单号
                $model->where(['id'=>$id])->update(['sorder_sn'=>$unifiedorder['out_trade_no']]);
                $unifiedorder['sign'] = self::makeSign($unifiedorder);
                //请求数据
                $xmldata = self::array2xml($unifiedorder);
                $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
                $res = self::curl_post_ssl($url, $xmldata);
                if(!$res){
                    self::return_err("Can't connect the server");
                }

                $content = self::xml2array($res);

                if(strval($content['result_code']) == 'FAIL'){
                    self::return_err(strval($content['err_code_des']));
                }
                if(strval($content['return_code']) == 'FAIL'){
                    self::return_err(strval($content['return_msg']));
                }

                if(!empty($content['prepay_id'])){
                    return self::pay($content['prepay_id']);
                }else{
                    return json(['code'=>1,'msg'=>'发起支付失败']);
                }
            }else{
                return json(['code'=>1,'msg'=>'服务器繁忙']);
            }
        }else{
            return json(['code'=>1,'msg'=>'还有成长中的种子']);
        }
    }

    /**
     * 进行支付接口(POST)
     * @param string $prepay_id 预支付ID(调用prepay()方法之后的返回数据中获取)
     * @return  json的数据
     */
    public function pay($prepay_id){
        $config = $this->config;

        $data = array(
            'appId'		=> $config['appid'],
            'timeStamp'	=> time(),
            'nonceStr'	=> self::getNonceStr(),
            'package'	=> 'prepay_id='.$prepay_id,
            'signType'	=> 'MD5'
        );

        $data['paySign'] = self::makeSign($data);

        return json(['code'=>1,'data'=>$data]);
    }

    //微信支付回调验证
    public function notify(){
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];

        // 这句file_put_contents是用来查看服务器返回的XML数据 测试完可以删除了
        //file_put_contents(APP_ROOT.'/Statics/log2.txt',$res,FILE_APPEND);

        //将服务器返回的XML数据转化为数组
        $data = self::xml2array($xml);
        // 保存微信服务器返回的签名sign
        $data_sign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
        $sign = self::makeSign($data);

        // 判断签名是否正确  判断支付状态
        if ( ($sign===$data_sign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS') ) {
            $result = $data;
            //获取服务器返回的数据
            $out_trade_no =  explode('_',$data['out_trade_no']);
            $order_sn = $out_trade_no[0];			//订单单号
            $openid = $data['openid'];					//付款人openID
            $total_fee = $data['total_fee'];			//付款金额
            $transaction_id = $data['transaction_id']; 	//微信支付流水号

            //更新数据库
            $this->updateDB($order_sn,$openid,$total_fee,$transaction_id);

        }else{
            $result = false;
        }
        // 返回状态给微信服务器
        if ($result !== false) {
            $str='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        }else{
            $str='<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        echo $str;
        return $result;
    }

    //微信支付回调验证
    public function notifys(){
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];

        // 这句file_put_contents是用来查看服务器返回的XML数据 测试完可以删除了
        //file_put_contents(APP_ROOT.'/Statics/log2.txt',$res,FILE_APPEND);

        //将服务器返回的XML数据转化为数组
        $data = self::xml2array($xml);
        // 保存微信服务器返回的签名sign
        $data_sign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
        $sign = self::makeSign($data);

        // 判断签名是否正确  判断支付状态
        if ( ($sign===$data_sign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS') ) {
            $result = $data;
            //获取服务器返回的数据
            $out_trade_no =  explode('_',$data['out_trade_no']);
            $order_sn = $out_trade_no[0];			//订单单号
            $openid = $data['openid'];					//付款人openID
            $total_fee = $data['total_fee'];			//付款金额
            $transaction_id = $data['transaction_id']; 	//微信支付流水号
            db("seeds_user")->where(['sorder_sn'=>$order_sn])->update(['pay_status'=>1]);
            $info = db("seeds_user")->where(['sorder_sn'=>$order_sn])->find();
            db("seeds")->where(['id'=>$info['sid']])->setDec("stock",1);

        }else{
            $out_trade_no =  explode('_',$data['out_trade_no']);
            $order_sn = $out_trade_no[0];			//订单单号
            db("seeds_user")->where(['sorder_sn'=>$order_sn])->delete();
            $result = false;
        }
        // 返回状态给微信服务器
        if ($result !== false) {
            $str='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        }else{
            $str='<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        echo $str;
        return $result;
    }

//---------------------------------------------------------------用到的函数------------------------------------------------------------
    /**
     * 错误返回提示
     * @param string $errMsg 错误信息
     * @param string $status 错误码
     * @return  json的数据
     */
    protected function return_err($errMsg='error',$status=0){
        exit(json_encode(array('code'=>$status,'result'=>'fail','msg'=>$errMsg)));
    }

    /**
     * 更新订单状态，添加支付日志
     * @param $order_sn
     * @param $openid
     * @param $total_fee
     * @param $transaction_id
     */
    public function updateDB($order_sn,$openid,$total_fee,$transaction_id){

        $orderModel = new OrderModel();
        $order_info = $orderModel->where(['out_trade_no'=>$order_sn])->find();

        if($order_info['is_notify'] == 0){
            if(!empty($order_info['product_info'])){
                $product_info = json_decode($order_info['product_info'],true);
                foreach($product_info as $key=>$row){
                    db("product")->where(['id'=>$row['id']])->setInc("total_sales",$row['num']);
                    db("product")->where(['id'=>$row['id']])->setInc("sales",$row['num']);
                }
            }
            
            $orderModel->where(['out_trade_no'=>$order_sn])->update(['status'=>1, 'is_notify'=>1]);
            
            //:TODO  加入连续购买逻辑
            $this->rebate($openid);
            
            $data['out_trade_no'] = $order_sn;
            $data['openid']   = $openid;
            $data['total_fee'] = $total_fee;
            $data['transaction_id'] = $transaction_id;
            $data['create_time']   = time();
            
            db("pay_log")->insert($data);
        }
    }
    
    /**
    * 处理购物返利比例逻辑
    * @date: 2018年11月6日 下午1:51:42
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    private function rebate($openid){
       $orderModel = new OrderModel();
        $buyRebate = $orderModel::get(function($query) use($openid){
            $query->field("order.uid, order.create_time, user.continuity_buy, user.is_tiyan");
            $query->alias('order');
            $query->join('__UCENTER_MEMBER__ user', 'order.uid = user.id', 'left');
            $query->where('order.status', '>', 1);
            $query->where('user.openid', $openid);
            $query->order('order.create_time desc');
        });
        
        /* 读取数据库中的配置 */
        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置
    
        $initScale = config('BUY_INIT_SCALE');//基础
        $incScale = config('BUY_INC_SCALE');//增幅
        $maxScale = config('BUY_MAX_SCALE');//最大
         
        if($buyRebate['uid'] > 0 && $buyRebate['create_time'] > 0){
            $lastDate = intval(date('Ymd', $buyRebate['create_time']));
            $toDay = intval(date('Ymd'));
             
            //判断当前是否为开启返利的
            if($buyRebate['continuity_buy'] > 0){
                //判断返利是否断裂
                if($lastDate+2 < $toDay){
                    //超过两天的再次购买，返利链断裂，从头计算
                    db("ucenter_member")->where('id', $buyRebate['uid'])->update(array('continuity_buy'=>0));
                }else{
                    //未超过两天的再次购买，连续购买天数加一，按照增幅计算最终返利比例
                    $setScale = min($buyRebate['continuity_buy']+$incScale, $maxScale);
                    db("ucenter_member")->where('id', $buyRebate['uid'])->update(array('continuity_buy'=>$setScale, 'is_tiyan'=>1));
                }
            }else{
                //开启返利
                if($buyRebate['is_tiyan'] == 0){
                    //连续购买没有，体验10%返利没参与过的用户【老用户第一次购买】
                    db("ucenter_member")->where('id', $buyRebate['uid'])->update(array('continuity_buy'=>10, 'is_tiyan'=>1));
                }else{
                    //连续购买没有，且已经参与过体验的用户
                    db("ucenter_member")->where('id', $buyRebate['uid'])->update(array('continuity_buy'=>$initScale));
                }
            }
        }else{
            //没有找到该用户的历史订单，默认为新进入的用户，给与10%返利
            //要更新体验权限，不然会出现两次10%开启
            db("ucenter_member")->where('id', $buyRebate['uid'])->update(array('continuity_buy'=>10, 'is_tiyan'=>1));
        }        
    }


    /**
     * 正确返回
     * @param 	array $data 要返回的数组
     * @return  json的数据
     */
    protected function return_data($data=array()){
        exit(json_encode(array('status'=>1,'result'=>'success','data'=>$data)));
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
     * 微信支付发起请求
     */
    protected function curl_post_ssl($url, $xmldata, $second=30,$aHeader=array()){
        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '10.206.30.98');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);


        if( count($aHeader) >= 1 ){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }

        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$xmldata);
        $data = curl_exec($ch);
        if($data){
            curl_close($ch);
            return $data;
        }
        else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }
}