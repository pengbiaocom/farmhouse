<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageListBuilder;
use app\common\model\OrderModel;
use app\common\model\ProductModel;

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
            ->buttonModalPopup(url('Order/print_select'), array(), '打印所选项', ['class'=>'layui-btn ajax-post tox-confirm', 'data-title'=>'打印所选项小票', 'target-form'=>'ids', 'data-confirm'=>'是否要打印所选项小票'])
            ->buttonModalPopup(url('Order/print_search'), $select, '打印筛选结果', ['class'=>'layui-btn ajax-post tox-confirm', 'data-confirm'=>'是否要打印筛选结果小票'])
            ->buttonModalPopup(url('Order/refunds'), array(), '退还所选项', ['class'=>'layui-btn ajax-post'])
            ->buttonModalPopup(url('Order/refunds'), $select, '退还筛选结果', ['class'=>'layui-btn ajax-post'])
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

    /**
    * 打印所选
    * @date: 2018年7月27日 上午9:50:29
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function print_select($ids){
        /* 读取数据库中的配置 */
        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置
        
        //加载商品应收单价（ID、price格式）
        $productModel = new ProductModel();
        $prices = [];
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
            
            
            //获取到满足条件的订单数据（包括订单数据、用户数据、地址数据）
            $orderModel = new OrderModel();
            $orders = $orderModel::all(function($query) use($ids){
                $query->alias('order');
                $query->field("order.*,member.nickname,address.name as address_name,address.mobile as address_mobile,address.pos_community,province.name as province_name,city.name as city_name,district.name as district_name,street.name as street_name");
                $query->join('__MEMBER__ member', 'order.uid = member.uid', 'LEFT');
                $query->join('__RECEIVING_ADDRESS__ address', 'order.address_id = address.id', 'LEFT');
                $query->join('__DISTRICT__ province', 'address.pos_province = province.id', 'LEFT');
                $query->join('__DISTRICT__ city', 'address.pos_city = city.id', 'LEFT');
                $query->join('__DISTRICT__ district', 'address.pos_district = district.id', 'LEFT');
                $query->join('__DISTRICT__ street', 'address.street_id = street.id', 'LEFT');
                $query->where('order.id', 'IN', $ids);
            });

            //分析整理最后需要打印的数据
            foreach ($orders as &$item){
                $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
                
                $item['product_info'] = json_decode($item['product_info'], true);
                $goods = [];
                foreach ($item['product_info'] as $key=>$good){
                    $good['curr_price'] = $prices[$good['id']]['curr_price'];
                    $good['num_price'] = $prices[$good['id']]['curr_price'] * $good['num'];
                    $goods[] = $good;
                    unset($good);
                }
                $item['products'] = $goods;
                unset($goods);unset($item['product_info']);
            }

            $this->assign('orders', $orders);
            $this->assign('config', $config);
        }
        
        return $this->fetch();
    }
    
    /**
    * 打印筛选结果
    * @date: 2018年7月27日 上午9:50:55
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function print_search(){
        /* 读取数据库中的配置 */
        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置
        
        //加载商品应收单价（ID、price格式）
        $productModel = new ProductModel();
        $prices = [];
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
            
            //筛选参数
            $status = input('status', -1, 'intval');
            $create_time = input('create_time', strtotime(date('Y-m-d')), 'intval');
            $keyword = input('keyword','','op_t');
            
            //获取到满足条件的订单数据（包括订单数据、用户数据、地址数据）
            $orderModel = new OrderModel();
            $orders = $orderModel::all(function($query) use($status,$create_time,$keyword){
                $query->alias('order');
                $query->field("order.*,member.nickname,address.name as address_name,address.mobile as address_mobile,address.pos_community,province.name as province_name,city.name as city_name,district.name as district_name,street.name as street_name");
                $query->join('__MEMBER__ member', 'order.uid = member.uid', 'LEFT');
                $query->join('__RECEIVING_ADDRESS__ address', 'order.address_id = address.id', 'LEFT');
                $query->join('__DISTRICT__ province', 'address.pos_province = province.id', 'LEFT');
                $query->join('__DISTRICT__ city', 'address.pos_city = city.id', 'LEFT');
                $query->join('__DISTRICT__ district', 'address.pos_district = district.id', 'LEFT');
                $query->join('__DISTRICT__ street', 'address.street_id = street.id', 'LEFT');
                
                if($status != -1) $query->where('order.status', $status);
                
                if(!empty($create_time)) $query->where('order.create_time', 'between', [$create_time, $create_time+86400]);
                
                if(!empty($keyword)) $query->where('out_trade_no', 'like', '%'.$keyword.'%');
                
            });
            
            //分析整理最后需要打印的数据
            foreach ($orders as &$item){
                $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
        
                $item['product_info'] = json_decode($item['product_info'], true);
                $goods = [];
                foreach ($item['product_info'] as $key=>$good){
                    $good['curr_price'] = $prices[$good['id']]['curr_price'];
                    $good['num_price'] = $prices[$good['id']]['curr_price'] * $good['num'];
                    $goods[] = $good;
                    unset($good);
                }
                $item['products'] = $goods;
                unset($goods);unset($item['product_info']);
            }
        
            $this->assign('orders', $orders);
            $this->assign('config', $config);
        }
        
        return $this->fetch('print_select');
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

		for ($i = 1; $i <= 50; $i++) {
		    ob_flush();
		    flush();
		    echo $i.'<br/>';
		    sleep(rand(0, 1));
		}
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