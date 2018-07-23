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

    /**
     * 预支付请求接口(POST)
     * @param string $openid 	openid
     * @param string $body 		商品简单描述
     * @param string $order_sn  订单编号
     * @param string $total_fee 金额
     * @return  json的数据
     */
    public function prepay(Request $request){

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
            'body'			=> '益丰农舍-商品购买',
            'out_trade_no'	=> $order->out_trade_no,
            'total_fee'		=> $order->total_fee * 100,
            'spbill_create_ip'	=> get_client_ip(),
            'notify_url'	=> 'http://api.chouvc.com/api/Wxpay/notify',
            'trade_type'	=> 'JSAPI',
            'openid'		=> $user->openid
        );

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
            return json(['code'=>1,'msg'=>'发起支付失败']);
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

        $arr['appId'] = $data['appId'];
        $arr['timeStamp'] = $data['timeStamp'];
        $arr['nonceStr'] = $data['nonceStr'];
        $arr['package'] = $data['package'];
        $arr['signType'] = $data['signType'];
        $arr['paySign'] = $data['paySign'];
        $arr['prepayId'] = $prepay_id;

        return json(['code'=>0,'data'=>$arr]);
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
            $order_sn = $data['out_trade_no'];			//订单单号
            $openid = $data['openid'];					//付款人openID
            $total_fee = $data['total_fee'];			//付款金额
            $transaction_id = $data['transaction_id']; 	//微信支付流水号

            //更新数据库
            $this->updateDB($order_sn,$openid,$total_fee,$transaction_id);

        }else{
            $result = false;
        }
        // 返回状态给微信服务器
        if ($result) {
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
        exit(json_encode(array('status'=>$status,'result'=>'fail','errmsg'=>$errMsg)));
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
        $orderModel->where(['out_trade_no'=>$order_sn])->update(['status'=>4]);

        $data['out_trade_no'] = $order_sn;
        $data['openid']   = $openid;
        $data['total_fee'] = $total_fee;
        $data['transaction_id'] = $transaction_id;
        $data['create_time']   = time();

        db("pay_log")->insert($data);
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