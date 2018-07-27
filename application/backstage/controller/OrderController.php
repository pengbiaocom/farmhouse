<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageListBuilder;
use app\common\model\OrderModel;

class OrderController extends BackstageController{

    private $config = [];

    public function   index(){
        $r = config("LIST_ROWS");
        $orderModel = new OrderModel();

        $map = array();//定义条件数据
        $select = array();
        
        //状态
        $status = input('status', -1, 'intval');
        if($status !== -1){
            $map['status'] = array('EQ', $status);
            $select['status'] = $status;
        }
        
        //时间   所选日期当天的订单
        $create_time = input('create_time', strtotime(date('Y-m-d')), 'intval');
        if(!empty($create_time)){
            $map['create_time'] = array('between', [$create_time, $create_time+86400]);
            $select['create_time'] = $create_time;
        }
        
        //搜索
        $keyword = input('keyword','','op_t');
        if(!empty($keyword)){
            $map['out_trade_no'] = array('like', $keyword);
            $select['out_trade_no'] = $keyword;
        }

        list($list,$totalCount)=$orderModel->getListByPage($map,'refund asc, printd asc, create_time desc','*',$r);

        $statustext = [0=>"待付款",1=>'待发货',2=>'待收货',3=>'待评价',4=>'已完成'];
        if($list){
            foreach($list as $key=>$row){
                $list[$key]['statustext'] =$statustext[$row['status']];
                $list[$key]['printdtext'] = $row['printd']==0 ? '<span style="color:red;">未打印</span>':'<span style="color:green;">已打印</sapn>';
                $list[$key]['refundtext'] = $row['refund']==0 ? '<span style="color:red;">未退还</span>':'<span style="color:green;">已退还</span>';
                $list[$key]['nickname'] = get_nickname($row['uid']);
                
                $product_info = json_decode($row['product_info'], true);
                $list[$key]['goods_info'] = "";
                foreach ($product_info as $item){
                    $list[$key]['goods_info'] .= $item['name'] . '*' . $item['num'] . '<br/>';
                }
            }
        }
        
        $builder=new BackstageListBuilder();
        $builder->title('订单列表')
            ->ajaxButton('', '', '打印所选项', ['class'=>'layui-btn ajax-post tox-confirm', 'data-confirm'=>'是否要打印所选项小票'])
            ->ajaxButton('', '', '打印筛选结果', ['class'=>'layui-btn ajax-post tox-confirm', 'data-confirm'=>'是否要打印筛选结果小票'])
            ->ajaxButton(url('Order/refunds'), array(), '退还所选项', ['class'=>'layui-btn ajax-post tox-confirm', 'data-confirm'=>'是否要退还所选项'])
            ->ajaxButton(url('Order/refunds'), $select, '退还筛选结果', ['class'=>'layui-btn ajax-post tox-confirm', 'data-confirm'=>'是否要打印筛选结果'])
            ->keyId('out_trade_no', '订单编号')
            ->setSearchPostUrl(url('order/index'))
            ->searchDateTime('日期', 'create_time', 'date')
            ->searchSelect('订单状态', 'status', 'select', '', '', [['id'=>-1, 'value'=>'请选择'],['id'=>0,'value'=>'待付款'],['id'=>1,'value'=>'待发货'],['id'=>2,'value'=>'待收货'],['id'=>3,'value'=>'待评价'],['id'=>4,'value'=>'已完成']])
            ->searchText('','keyword','text','订单编号')
            ->keyText('nickname','用户名称')
            ->keyText('goods_info','商品信息')
            ->keyText('coupon', '优惠券使用量')
            ->keyText('freight', '运费')
            ->keyText('total_fee','订单价格')
            ->keyHtml('remark', '备注信息')
            ->keyText('printdtext','是否打印')
            ->keyText('refundtext','是否退款')
            ->keyText('statustext','状态')
            ->keyCreateTime()
            ->data($list);
        $builder->pagination($totalCount);
        return $builder->show();
    }

    public function edit(){

    }
    
    /**
    * 获取满足条件的退款订单
    * @date: 2018年7月27日 上午9:00:05
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function refunds(){
        //微信支付参数配置(appid,商户号,支付秘钥)
        $config = [
            'appid'=>'wxa6737565830cae42',
            'pay_mchid'=>'1509902681',
            'pay_apikey'=>'6ba57bc32cfd5044f8710f09ff86c664'
        ];

		$this->config = $config;
		
		
        dump($_REQUEST);
    }
    
    /**
    * 退款申请
    * @date: 2018年7月27日 上午9:00:18
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    private function refund($orders){
        $config = $this->config;
        
        //退款申请参数构造
        if(sizeof($orders) > 0){
            foreach ($orders as $order){
                $refunddorder = array(
                    'appid'			=> $config['appid'],
                    'mch_id'		=> $config['pay_mchid'],
                    'nonce_str'		=> self::getNonceStr(),
                    'out_trade_no'	=> $order->out_trade_no,
                    'out_refund_no' => $order->out_trade_no . md5($order->out_trade_no),//退款唯一单号，系统生成
                    'total_fee'		=> $order->total_fee * 100,
                    'refund_fee'    => '',//退款金额,通过计算得到要退还的金额
                );
                
                $refunddorder['sign'] = self::makeSign($refunddorder);
                
                //请求数据
                $xmldata = self::array2xml($refunddorder);
                $url = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
                $res = self::postXmlCurl($xmldata, $url, true);
                
                var_dump($res);exit;                
            }
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
}