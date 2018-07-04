<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\CurlModel;

class OrderController extends Controller{
    private $wx_key = "";//申请支付后有给予一个商户账号和密码，登陆后自己设置key
    private $appid = 'wx25fdd247f54f5841';//小程序id
    
    public function create_order(Request $request){
        //这里是按照顺序的 因为下面的签名是按照顺序 排序错误 肯定出错
        $post['appid'] = $this->appid;
        $post['body'] = "腾讯充值中心-QQ会员充值";//描述
        $post['mch_id'] = "";//商户号
        $post['nonce_str'] = $this->nonce_str();//随机字符串
        $post['notify_url'] = "";//回调地址自己填写
        $post['openid'] = "";//用户在商户appid下的唯一标识
        $post['out_trade_no'] = time();//商户订单号
        $post['spbill_create_ip'] = get_client_ip();//终端的ip
        $post['total_fee'] = $this->total_fee();//因为充值金额最小是1 而且单位为分 如果是充值1元所以这里需要*100
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
    private function total_fee(){
        return 0.01;
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