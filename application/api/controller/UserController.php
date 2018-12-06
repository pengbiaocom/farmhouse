<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\CurlModel;
use app\common\model\UcenterMemberModel;
use think\Db;
use app\common\model\OrderModel;
use app\common\model\ProductModel;

class UserController extends Controller{
    private $appid = 'wxa6737565830cae42';
    private $secret = '2db64a778849a93bf4481a5815427a54';
    private $sessionKey = '';
    
    private $config = [];
    
    public function login(Request $request){
        $invitation = $request->param('invitation', 158, 'intval');
        $code = $request->param('code', '', 'op_t');
        $encryptedData = $request->param('encryptedData', '', 'op_t');
        $iv = $request->param('iv', '', 'op_t');

        $open_url = "https://api.weixin.qq.com/sns/jscode2session";
        $curlData = array(
            'appid'=>$this->appid,
            'secret'=>$this->secret,
            'js_code'=>$code,
            'grant_type'=>'authorization_code'
        );
        
        $curlModel = new CurlModel();
        $curlModel->set_ssl_host(true);
        $curlModel->set_ssl_peer(true);
        $resJson = $curlModel->get_single($open_url, $curlData);
        $res = json_decode($resJson,true);
        
        if($res && !empty($res['openid'])){
            $ucenterMemberModel = new UcenterMemberModel();
            $user = $ucenterMemberModel::get(function($query) use($res){
                $query->where('openid', $res['openid']);
            });
            
            if($user->id > 0){
                $ucenterMemberModel::update(array('id'=>$user->id, 'openid'=>$res['openid'], 'session_key'=>$res['session_key'], 'invit'=>$invitation, 'invit_time'=>strtotime(date('Ymd'))));
                return json(['code'=>0, 'msg'=>'调用成功', 'data'=>['id'=>$user->id]]);
            }else{
                $this->sessionKey = $res['session_key'];
                $errCode = $this->decryptData($encryptedData, $iv, $data);
                if ($errCode == 0) {
                    //整理数据，并实现注册
                    $ucenterMemberModel = new UcenterMemberModel();
                    $data = json_decode($data, true);
                    $uid = $ucenterMemberModel->register($res['openid'], $res['session_key'], $data['openid'], $data['nickName'], '123456', $invitation);
                
                    if($uid > 0){
                        return json(['code'=>0, 'msg'=>'调用成功', 'data'=>['id'=>$uid]]);
                    }else{
                        return json(['code'=>0, 'msg'=>$ucenterMemberModel->getErrorMessage($uid), 'data'=>[]]);
                    }
                }else{
                    //记录下日志
                    return json(['code'=>1, 'msg'=>'用户数据解析错误', 'data'=>['errCode'=>$errCode]]);
                }                
            }
        }else{
            //授权问题，记录日志
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }
    
    /**
    * 邀请处理(老用户邀请老用户)
    * @date: 2018年11月2日 上午10:22:21
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function invitation(Request $request){
        $uid = $request->param('uid', 0, 'intval');
        $invitation = $request->param('invitation', 158, 'intval');

        $ucenterMemberModel = new UcenterMemberModel();
        $user = $ucenterMemberModel::get(function($query) use($uid){
            $query->where('id', $uid);
        });
        
        if($user->id > 0 && $user->invit == 0 && $invitation > 0) {
            $ucenterMemberModel::update(array('id'=>$user->id, 'invit'=>$invitation, 'invit_time'=>strtotime(date('Ymd'))));
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>[]]);
        } else {
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }

    /**
     * 查询订单列表
     * @param  uid 用户id  status 订单状态   limit 每页数量  page 当前页
     * @param Request $request
     * @return \think\response\Json
     * User: 离殇<pengxuancom@163.com>
     */
    public function order_list(Request $request){
        $uid = $request->param("uid");

        $status = $request->param('status');

        $limit = $request->param('limit', 10, 'intval');
        $page = $request->param('page', 1, 'intval');

        if(empty($uid))  return json(['code'=>1,'msg'=>'缺少必要参数']);

        $map = "uid=".$uid;

        if($status != 'all'){
            if($status == 3){
                $map .= " and status in (3,4)";
            }else{
                $map .= " and status=".$status;
            }

        }

        $order_list = db("order")->where($map)->order("create_time desc")->page($page, $limit)->select();

        if($order_list){
            $status_text = ['待付款','待发货','待收货','待评价','已完成'];
            foreach($order_list as $key=>$row){
                $order_list[$key]['create_time'] = date("Y-m-d H:i:s",$row['create_time']);
                $order_list[$key]['product_info'] = json_decode($row['product_info'],true);
                $order_list[$key]['statusStr'] = $row['status'] == -1 ? '已取消' : $status_text[$row['status']];
            }
            return json(['code'=>0,'msg'=>'success','data'=>$order_list,'paginate'=>['page'=>sizeof($order_list) < $limit ? $page : $page+1, 'limit'=>$limit]]);
        }else{
            return json(['code'=>1,'msg'=>'没有订单']);
        }
    }

    /**
     * 订单详情
     * @param  order_id  订单id
     * @param Request $request
     * @return \think\response\Json
     * User: 离殇<pengxuancom@163.com>
     */
    public function order_detail(Request $request){
         $order_id = $request->param('id');

        if(empty($order_id))  return json(['code'=>1,'msg'=>'缺少必要参数']);
        $status_text = ['待付款','待发货','待收货','待评价','已完成'];
        $detail = db("order")->where(['id'=>$order_id])->find();

        if($detail){
            $detail['create_time'] = date("Y-m-d H:i:s",$detail['create_time']);
            $detail['product_info'] = json_decode($detail['product_info'],true);
            $detail['statusStr'] = $status_text[$detail['status']];
            $address_info = db("receiving_address")->where(['id'=>$detail['address_id']])->find();
            if($address_info){
                $pos_province = db("district")->where(['id'=>$address_info['pos_province']])->value("name");
                $pos_city = db("district")->where(['id'=>$address_info['pos_city']])->value("name");
                $pos_district = db("district")->where(['id'=>$address_info['pos_district']])->value("name");
                $street_id = db("district")->where(['id'=>$address_info['street_id']])->value("name");

                $address_info['address'] = $pos_province.$pos_city.$pos_district.$street_id.$address_info['pos_community'];
                $detail['address'] = $address_info;
            }
            return json(['code'=>0,'msg'=>'success','data'=>$detail]);
        }else{
            return json(['code'=>1,'msg'=>'没有查询到数据']);
        }
    }

    /**
     * 确认收货
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     * User: 离殇<pengxuancom@163.com>
     */
    public function order_delivery(Request $request){
        $order_id = $request->param('id');
        $uid = $request->param("uid");
        if(empty($order_id) && empty($uid))  return json(['code'=>1,'msg'=>'缺少必要参数']);
        $detail = db("order")->where(['id'=>$order_id,'uid'=>$uid])->find();
        if($detail){
            if(db("order")->where(['id'=>$order_id,'uid'=>$uid])->update(['status'=>3])){
                return json(['code'=>0,'msg'=>'操作成功']);
            }else{
                return json(['code'=>1,'msg'=>'操作失败']);
            }
        }else{
            return json(['code'=>1,'msg'=>'没有查询到数据']);
        }
    }

    /**
     * 订单删除
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     * User: 离殇<pengxuancom@163.com>
     */
    public function  order_delete(Request $request){
       $order_id = $request->param('order_id');

       if(empty($order_id))  return json(['code'=>1,'msg'=>'缺少必要参数']);

       $detail = db("order")->where(['id'=>$order_id])->find();
       if($detail['status']==0){
           if(db("order")->where(['id'=>$order_id])->update(['status'=>-1])){
               $goods = json_decode($detail['product_info'], true);
               foreach ($goods as $good){
                   $productModel = new ProductModel();
                   $productModel->where('id', $good['id'])->setInc('stock', $good['num']);
               }
               return json(['code'=>0,'msg'=>'取消成功！']);
           }else{
               return json(['code'=>1,'msg'=>'取消失败！']);
           }
       }elseif ($detail['status'] == 1){
           $boef_time = strtotime(date('Ymd'));
           if($detail['create_time'] > $boef_time && $detail['create_time'] < intval($boef_time+70200)){
               $del = db("order")->where(['id'=>$order_id])->update(['status'=>-1]);
               if($del){
                   //处理退款接口
                   $config = [
                       'appid'=>'wxa6737565830cae42',
                       'pay_mchid'=>'1509902681',
                       'pay_apikey'=>'6ba57bc32cfd5044f8710f09ff86c664'
                   ];
                   $this->config = $config;
                    
                   if($this->refund($detail)){
                       $goods = json_decode($detail['product_info'], true);
                       foreach ($goods as $good){
                           $productModel = new ProductModel();
                           $productModel->where('id', $good['id'])->setInc('stock', $good['num']);
                           $productModel->where('id', $good['id'])->setDec('sales', $good['num']);
                       }
                       
                       $map['create_time'] = ['GT', $boef_time];
                       $map['status'] = ['GT', 0];
                       if(db("order")->where($map)->count() == 0 && db('ucenter_member')->where('id', $detail['uid'])->value('continuity_buy') > 0) {
                           db('ucenter_member')->where('id', $detail['uid'])->setDec('continuity_buy', 1);
                       }
                        
                       return json(['code'=>0,'msg'=>'取消成功！']);
                   } else {
                       db("order")->where(['id'=>$order_id])->update(['status'=>1]);
                       return json(['code'=>1,'msg'=>'取消失败！']);
                   }
               }               
           } else {
               return json(['code'=>1,'msg'=>'只有当天支付并19:30之前可以取消']);
           }
       }else{
           return json(['code'=>1,'msg'=>'待支付/待发货的订单才可以取消']);
       }

    }
    
    /**
     * 退款申请
     * @date: 2018年7月27日 上午9:00:18
     * @author: onep2p <324834500@qq.com>
     * @param: variable
     * @return:
     */
    private function refund($order){
        $config = $this->config;
    
        //退款申请参数构造
        if($order){
            $refunddorder = array(
                'appid'			=> $config['appid'],
                'mch_id'		=> $config['pay_mchid'],
                'nonce_str'		=> self::getNonceStr(),
                'out_trade_no'	=> $order['out_trade_no'],
                'out_refund_no' => 'tk_' . md5($order['out_trade_no']),//退款唯一单号，系统生成
                'total_fee'		=> $order['total_fee'] * 100,
                'refund_fee'    => $order['total_fee'] * 100,//退款金额,通过计算得到要退还的金额
            );
    
            $refunddorder['sign'] = self::makeSign($refunddorder);
    
            //请求数据
            $xmldata = self::array2xml($refunddorder);
            $url = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
            $res = self::postXmlCurl($xmldata, $url, true);
            $resData = $this->xml2array($res);
            
            if($resData['return_code'] === 'SUCCESS' && $resData['return_msg'] === 'OK' && $resData['result_code'] === 'SUCCESS'){
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
     * 检验数据的真实性，并且获取解密后的明文.
     * @param $encryptedData string 加密的用户数据
     * @param $iv string 与用户数据一同返回的初始向量
     * @param $data string 解密后的原文
     *
     * @return int 成功0，失败返回对应的错误码
     */
    public function decryptData($encryptedData, $iv, &$data)
    {
        if (strlen($this->sessionKey) != 24) {
            return -41001;
        }
        $aesKey=base64_decode($this->sessionKey);
    
    
        if (strlen($iv) != 24) {
            return -41002;
        }
        $aesIV=base64_decode($iv);
    
        $aesCipher=base64_decode($encryptedData);
    
        $result = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
    
        $dataObj = json_decode($result);
        if($dataObj  == NULL)
        {
            return -41003;
        }
        if($dataObj->watermark->appid != $this->appid)
        {
            return -41003;
        }
        
        $data = $result;
        
        return 0;
    }
    
    /**
    * 返利总览
    * @date: 2018年11月5日 下午3:49:17
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function initRebate(Request $request)
    {
       $uid = $request->param('uid');
       
       /* 读取数据库中的配置 */
       $config = cache('DB_CONFIG_DATA');
       if (!$config) {
           $config = controller("common/ConfigApi")->lists();
           cache('DB_CONFIG_DATA', $config);
       }
       config($config); //添加配置
        
       $buyInitScale = config('BUY_INIT_SCALE');//基础
       $buyIncScale = config('BUY_INC_SCALE');//增幅
       $buyMaxScale = config('BUY_MAX_SCALE');//最大
       $invitInitScale = config('INVIT_INIT_SCALE');//基础
       $invitIncScale = config('INVIT_INC_SCALE');//增幅
       $invitMaxScale = config('INVIT_MAX_SCALE');//最大
       
       $boef_time = strtotime(date('Ymd'));
       
       /* 购买统计 */
       $ucenterMemberModel = new UcenterMemberModel();
       $users = $ucenterMemberModel::all(function($query) use($uid,$boef_time){
           $query->field('user.continuity_buy, sum(order.total_fee) as total_fee, FROM_UNIXTIME(order.create_time,"%Y%m%d") as create_date');
           $query->alias('user');
           $query->join('__ORDER__ order', 'user.id = order.uid', 'left');
           $query->where('user.id', $uid);
           $query->where('order.status', '>', 0);
           $query->where('order.create_time', 'between', [$boef_time-172800,$boef_time]);
           $query->order('create_date DESC');
           $query->group('FROM_UNIXTIME(order.create_time,"%Y%m%d")');
       });
       
       $orderModel = new OrderModel();
       $map['status'] = array('GT', 0);
       $map['uid'] = $uid;
       $map['create_time'] = array('GT', $boef_time);
       $today_buy = $orderModel->where($map)->count();
       
       $rebates = [];
       $rebates['is_today_buy'] = $today_buy;
       $rebates['buy_rebate'] = 0;
       $rebates['buy_money'] = '0.00';
       $rebates['tal_profit'] = $ucenterMemberModel->where('id', $uid)->value('tal_profit');
       $rebates['continuity_buy'] = $ucenterMemberModel->where('id', $uid)->value('continuity_buy');
       
       if($rebates['continuity_buy'] == 0){
           $rebates['buy_rebate'] = 0;
       }else{
           $rebate = $buyInitScale + ($rebates['continuity_buy']-1)*$buyIncScale;
           $rebates['buy_rebate'] = min($rebate, $buyMaxScale);
       }
       
       //没有购买过或者连续购买断裂
       foreach ($users as $user){
           if($rebates['continuity_buy'] == 0){
               $rebates['buy_money'] = '0.00';
           } else {
               if($rebates['is_today_buy'] > 1){
                   $rebates['buy_money'] = sprintf("%.2f", $user['total_fee']*$rebates['buy_rebate']/100);
               }else{
                   $rebates['buy_money'] = '0.00';
               }
           }

           if($user['total_fee'] > 0) break;
       }

       /* 邀请数据统计 */
       $invits = $ucenterMemberModel::all(function($query) use($uid,$boef_time){
           $query->field('user.invit_time, order.total_fee, order.create_time');
           $query->alias('user');
           $query->join('__ORDER__ order', 'user.id = order.uid', 'left');
           $query->where('user.invit', $uid);
           $query->where('order.status', '>', 0);
           $query->where('order.create_time', 'between', [$boef_time,$boef_time+86400]);
       });
       
       $today_invit_count = 0;
       $today_invit_consumption = 0;
       foreach ($invits as $invit){
           if($invit['invit_time'] == $boef_time) $today_invit_count += 1;
           
           if(strtotime(date('Ymd',$invit['create_time'])) == $boef_time) $today_invit_consumption += $invit['total_fee'];
       }
       
       if($today_invit_count == 0){
           //当天没有要求下线成员，默认5%返利
           $rebates['invit_rebate'] = 5;
           $rebates['invit_money'] = sprintf("%.2f", $today_invit_consumption*5/100);
       }else{
           $rebates['invit_rebate'] = min($invitInitScale+($today_invit_count-1)*$invitIncScale, $invitMaxScale);
           $rebates['invit_money'] = sprintf("%.2f", $today_invit_consumption*$rebates['invit_rebate']/100);
       }
       
       return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$rebates]);
    }
    
    /**
    * 用户购买返利
    * @date: 2018年11月5日 下午3:36:47
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function buyRebate(Request $request)
    {
       $uid = $request->param('uid');
       
       /* 读取数据库中的配置 */
       $config = cache('DB_CONFIG_DATA');
       if (!$config) {
           $config = controller("common/ConfigApi")->lists();
           cache('DB_CONFIG_DATA', $config);
       }
       config($config); //添加配置
       
       $buyInitScale = config('BUY_INIT_SCALE');//基础
       $buyIncScale = config('BUY_INC_SCALE');//增幅
       $buyMaxScale = config('BUY_MAX_SCALE');//最大
       
       /* 购买统计 */
       $boef_time = strtotime(date('Ymd'));
       $ucenterMemberModel = new UcenterMemberModel();
       $users = $ucenterMemberModel::all(function($query) use($uid,$boef_time){
           $query->field('user.continuity_buy, sum(order.total_fee) as total_fee, FROM_UNIXTIME(order.create_time,"%Y-%m-%d") as create_date');
           $query->alias('user');
           $query->join('__ORDER__ order', 'user.id = order.uid', 'left');
           $query->where('user.id', $uid);
           $query->where('order.status', '>', 0);
           $query->where('order.create_time', 'between', [$boef_time-172800,$boef_time]);
           $query->order('create_date DESC');
           $query->group('FROM_UNIXTIME(order.create_time,"%Y%m%d")');
       });
       
       $orderModel = new OrderModel();
       $map['status'] = array('GT', 0);
       $map['uid'] = $uid;
       $map['create_time'] = array('GT', $boef_time);
       $today_buy = $orderModel->where($map)->count();
       
       $rebates = [];
       $rebates['is_today_buy'] = $today_buy;
       $rebates['buy_rebate'] = 0;
       $rebates['buy_money'] = '0.00';
       $rebates['last_buy_date'] = 0;
       
       //没有购买过或者连续购买断裂
       foreach ($users as $user){
           if($user['continuity_buy'] == 0){
               $rebates['buy_rebate'] = 0;
               $rebates['buy_money'] = '0.00';
               $rebates['last_buy_date'] = 0;
           } else {
               $rebate = $buyInitScale + ($user['continuity_buy']-1)*$buyIncScale;
               
               $rebates['last_buy_date'] = $user['create_date'];
               $rebates['buy_rebate'] = min($rebate, $buyMaxScale);
               $rebates['buy_money'] = sprintf("%.2f", $user['total_fee']);
           }

           if($user['total_fee'] > 0) break;
       }
       
       return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$rebates]);
    }
    
    /**
    * 分享邀请返利
    * @date: 2018年11月5日 下午3:37:43
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function shareRebate(Request $request)
    {
       $uid = $request->param('uid');
       
       /* 读取数据库中的配置 */
       $config = cache('DB_CONFIG_DATA');
       if (!$config) {
           $config = controller("common/ConfigApi")->lists();
           cache('DB_CONFIG_DATA', $config);
       }
       config($config); //添加配置

       $invitInitScale = config('INVIT_INIT_SCALE');//基础
       $invitIncScale = config('INVIT_INC_SCALE');//增幅
       $invitMaxScale = config('INVIT_MAX_SCALE');//最大

       /* 邀请数据统计 */
       $boef_time = strtotime(date('Ymd'));
       $ucenterMemberModel = new UcenterMemberModel();
       $orderModel = new OrderModel();
       
       $users = $ucenterMemberModel::all(function($query) use($uid){
           $query->alias('user');
           $query->field('user.id, user.invit_time');
           $query->where('user.invit', $uid);
       });
       
       $today_invit_count = 0;
       $rebates['sumMoney'] = 0;
       $rebates['list'] = [];
       foreach ($users as $user){
           $invit = $orderModel::get(function($query) use($user, $boef_time){
               $query->alias('order');
               $query->field('sum(order.total_fee) as total_fee, order.create_time');
               $query->where('order.uid', $user['id']);
               $query->where('order.status', '>', 0);
               $query->where('order.create_time', '>', $boef_time);
               $query->group('order.uid');
           });
           
           if($user['invit_time'] == $boef_time && !empty($invit)) $today_invit_count += 1;
           
           if(!empty($invit) && strtotime(date('Ymd',$invit['create_time'])) == $boef_time) {
               $rebates['sumMoney'] += $invit['total_fee'];
               $rebates['list'][] = ['username'=>get_nickname($user['id']), 'invit_time'=>$user['invit_time'], 'total_fee'=>$invit['total_fee']];
           } else {
               $rebates['list'][] = ['username'=>get_nickname($user['id']), 'invit_time'=>$user['invit_time'], 'total_fee'=>'0.00'];
           }
       }
       
       $last_names = array_column($rebates['list'],'total_fee');
       array_multisort($last_names,SORT_DESC,$rebates['list']);
       
       $rebates['invit_count'] = $today_invit_count;
       if($today_invit_count == 0){
           //当天没有要求下线成员，默认5%返利
           $rebates['invit_rebate'] = 5;
       }else{
           $rebates['invit_rebate'] = min($invitInitScale+($today_invit_count-1)*$invitIncScale, $invitMaxScale);
       }
       
       return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$rebates]);
    }
    
    /**
    * 累计收益分类统计
    * @date: 2018年11月14日 下午4:45:21
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function talProfit(Request $request){
       $uid = $request->param('uid');
       
       $ucenterMemberModel = new UcenterMemberModel();
       $openid = $ucenterMemberModel->where('id', $uid)->value('openid');
       
       $rebates = ['buy_profit'=>'0.00', 'invit_profit'=>'0.00'];
       $userProfit = Db::table("cms_profit")->field("sum(money) as money, profit_type")->where('openid', $openid)->group('profit_type')->select();
       foreach ($userProfit as $profit){
           if($profit['profit_type'] == 1){
               //购买
               $rebates['buy_profit'] = $profit['money'];
           }else{
               //邀请
               $rebates['invit_profit'] = $profit['money'];
           }
       }
       
       return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$rebates]);
    }
}