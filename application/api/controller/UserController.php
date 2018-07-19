<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\CurlModel;
use app\common\model\UcenterMemberModel;

class UserController extends Controller{
    private $appid = 'wx25fdd247f54f5841';
    private $secret = '53552dcaee42df51c56366daaded9a07';
    private $sessionKey = '';
    
    public function login(Request $request){
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
                $ucenterMemberModel::update(array('id'=>$user->id, 'openid'=>$res['openid'], 'session_key'=>$res['session_key']));
                return json(['code'=>0, 'msg'=>'调用成功', 'data'=>['id'=>$user->id]]);
            }else{
                $this->sessionKey = $res['session_key'];
                $errCode = $this->decryptData($encryptedData, $iv, $data);
                if ($errCode == 0) {
                    //整理数据，并实现注册
                    $ucenterMemberModel = new UcenterMemberModel();
                    $data = json_decode($data, true);
                    $uid = $ucenterMemberModel->register($res['openid'], $res['session_key'], $data['openid'], $data['nickName'], '123456');
                
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
            $map .= " and status=".$status;
        }

        $order_list = db("order")->where($map)->order("create_time desc")->page($page, $limit)->select();

        if($order_list){
            $status_text = ['待付款','待发货','待收货','已完成'];
            foreach($order_list as $key=>$row){
                $order_list[$key]['create_time'] = date("Y-m-d H:i:s",$row['create_time']);
                $order_list[$key]['product_info'] = json_decode($row['product_info'],true);
                $order_list[$key]['statusStr'] = $status_text[$row['status']];
            }
            return json(['code'=>0,'msg'=>'success','data'=>$order_list,'paginate'=>['page'=>sizeof($order_list) < 10 ? $page : $page+1, 'limit'=>$limit]]);
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
        $status_text = ['待付款','待发货','待收货','已完成'];
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
           if(db("order")->where(['id'=>$order_id])->delete()){
               return json(['code'=>0,'msg'=>'删除成功！']);
           }else{
               return json(['code'=>1,'msg'=>'删除失败！']);
           }
       }else{
           return json(['code'=>1,'msg'=>'没有支付的订单才可以删除']);
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
}