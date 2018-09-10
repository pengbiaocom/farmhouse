<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\UcenterMemberModel;

class SeedsController extends Controller{

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
     * 列表
     * @date: 2018年6月6日 下午2:07:02
     * @param: variable
     * @return:
     */
    public function seeds_list(Request $request){
        $model = db('seeds');
        $limit = $request->param('limit', 10, 'intval');
        $page = $request->param('page', 1, 'intval');
        $uid = $request->param('uid', 0, 'intval');

        if($page == 'undefined' || $page == '') $page = 1;

        $list = $model
            ->where('status > 0 ')
            ->order('sort ASC')
            ->limit(($page-1)*$limit, $limit)
            ->select();

        if(!empty($list)){
            foreach ($list as &$item){
                $item['cover'] = get_cover(explode(',', $item['cover'])[0], 'path');
            }

            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$list, 'paginate'=>array('page'=>sizeof($list) < $limit ? $page : $page+1, 'limit'=>$limit)]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }

    /**
     * 详情
     * @date: 2018年6月6日 下午2:48:33
     * @param: variable
     * @return:
     */
    public function detail(Request $request){
        $model = db('seeds_user');
        $uid = $request->param('uid', 0, 'intval');

        if($uid == 0) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);

        $info = $model
            ->where(['status'=>0,'pay_status'=>1,'uid'=>$uid])
            ->find();

        if(!empty($info)){
            $seeds = db("seeds")->where(['id'=>$info['sid']])->find();
            if(!empty($seeds['adult_cover'])){
                foreach (explode(',', $seeds['adult_cover']) as $item){
                    $images1[] = get_cover($item, 'path');
                }
                $info['adult_cover'] = $images1;
            }
            $info['cover'] = $info['adult_cover'];
            $info['name'] = $seeds['name'];
            $info['stock'] = $seeds['stock'];
            $info['price'] = $seeds['price'];
            $info['unit'] = $seeds['unit'];
            $info['content'] = $seeds['content'];
            $info['total_sales'] = $seeds['total_sales'];
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$info]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
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
        $info = db("seeds")->where(['id'=>$sid])->find();

        if($info['stock']==0)  return json(['code'=>1,'msg'=>'库存不足']);

        if(db("receiving_address")->where(['uid'=>$uid])->count()==0) return json(['code'=>1,'msg'=>'还没有添加收货地址']);

        if($model->where(['uid'=>$uid,'status'=>0,'pay_status'=>1])->count()==0){
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
                    'notify_url'	=> 'https://api.yifengzhonggou.com/api/Seeds/notify',
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
                    return self::pay($content['prepay_id'],$unifiedorder['out_trade_no']);
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
    public function pay($prepay_id,$sorder_sn){
        $config = $this->config;

        $data = array(
            'appId'		=> $config['appid'],
            'timeStamp'	=> time(),
            'nonceStr'	=> self::getNonceStr(),
            'package'	=> 'prepay_id='.$prepay_id,
            'signType'	=> 'MD5'
        );

        $data['paySign'] = self::makeSign($data);
        $data['sorder_sn'] = $sorder_sn;

        return json(['code'=>0,'data'=>$data]);
    }

    public function  del_sorder_sn(Request $request){

        $sorder_sn = $request->param('sorder_sn');

        if(empty($sorder_sn))  return json(['code'=>1,'msg'=>'缺少参数']);

        if(db("seeds_user")->where(['sorder_sn'=>$sorder_sn,'pay_status'=>0])->count()>0){
            db("seeds_user")->where(['sorder_sn'=>$sorder_sn,'pay_status'=>0])->delete();
            return json(['code'=>0,'msg'=>'成功']);
        }else{
            return json(['code'=>1,'msg'=>'失败']);
        }
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
            $info = db("seeds_user")->where(['sorder_sn'=>$order_sn])->find();
            if($info['pay_status'] == 0) db("seeds")->where(['id'=>$info['sid']])->setDec("stock",1);

            db("seeds_user")->where(['sorder_sn'=>$order_sn])->update(['pay_status'=>1]);
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
     * 检查用户是否有成长中的种子
     * @param Request $request
     * @return \think\response\Json
     */
    public function check_seeds(Request $request){
        $model = db("seeds_user");
        $uid = $request->param('uid',0,'intval');

        if(empty($uid))  return json(['code'=>1,'msg'=>'缺少参数']);

        $count = $model->where(['uid'=>$uid,'status'=>0,'pay_status'=>1])->count();

        return json(['code'=>0,'data'=>$count]);

   }

    /**
     * 点赞灌水
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function  dian_seeds(Request $request){
        $model = db("seeds_dian");
        $uid = $request->param('uid',0,'intval');
        $suid = $request->param("suid",0,'intval');
        if(empty($uid) || empty($suid))  return json(['code'=>1,'msg'=>'缺少参数']);

        $start_time = strtotime(date('Ymd'));
        $end_time = strtotime(date('Ymd')) + 86400;

        if(db("seeds_user")->where(['id'=>$suid,'status'=>1])->count()>0)   return json(['code'=>1,'msg'=>'该种子已长大']);

        $where =  "suid=".$suid." and uid=".$uid;

        $count = $model->where($where)->count();

        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置

        if($count==0){
            $data['suid'] = $suid;
            $data['uid'] = $uid;
            $data['create_time'] = time();
            $data['exp_value'] = mt_rand(config("MIN_EXP"),config("MAX_EXP"));
            $data['remark'] = "为你增加了".$data['exp_value']."点经验";
            if($model->insert($data)){
                $info = db("seeds_user")->where(['id'=>$suid])->find();
                $exp = $info['exp']+$data['exp_value'];
                if($exp<$info['sum_exp']){
                    db("seeds_user")->where(['id'=>$suid])->update(['exp'=>$exp,'update_time'=>time()]);
                }else if($exp==$info['sum_exp']){
                    db("seeds_user")->where(['id'=>$suid])->update(['exp'=>$exp,'update_time'=>time(),'status'=>1]);
                }
                return json(['code'=>0,'msg'=>'点赞成功']);
            }else{
                return json(['code'=>1,'msg'=>'系统繁忙']);
            }
        }else{
            return json(['code'=>1,'msg'=>'每人只能点赞一次']);
        }

    }

    /**
     * 获取点赞列表
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_dian_seeds(Request $request){
        $model = db("seeds_dian");
        $suid = $request->param("suid",0,'intval');
        $limit = $request->param('limit', 10, 'intval');
        $page = $request->param('page', 1, 'intval');
        if(empty($suid))  return json(['code'=>1,'msg'=>'缺少参数']);

        $list = $model
            ->where(['suid'=>$suid])
            ->order('create_time desc')
            ->limit(($page-1)*$limit, $limit)
            ->select();

        if(!empty($list)){
            foreach ($list as $key=>$row){
                $list[$key]['create_time'] = date("Y-m-d H:i:s",$row['create_time']);
                $list[$key]['buyer_nickname'] = db("member")->where(['uid'=>$row['uid']])->value('nickname');
            }
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$list, 'paginate'=>array('page'=>sizeof($list) < $limit ? $page : $page+1, 'limit'=>$limit)]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }

    }
}