<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\CurlModel;

class UserController extends  Controller{
    private $appid = 'wx25fdd247f54f5841';
    private $secret = '53552dcaee42df51c56366daaded9a07';
    private $sessionKey = '';
    
    public function login(Request $request){
        $code = $request->param('code', '', 'op_t');
        $encryptedData = $request->param('encryptedData', '', 'op_t');
        $iv = $request->param('iv', '', 'op_t');

        $open_url = "https://api.weixin.qq.com/sns/jscode2session";
        $data = array(
            'appid'=>$this->appid,
            'secret'=>$this->secret,
            'js_code'=>$code,
            'grant_type'=>'authorization_code'
        );
        
        $curlModel = new CurlModel();
        $curlModel->set_ssl_host(true);
        $curlModel->set_ssl_peer(true);
        $resJson = $curlModel->get_single($open_url, $data);
        $res = json_decode($resJson,true);
        
        if($res && !empty($res['openid'])){
            $this->sessionKey = $res['session_key'];
            $errCode = $this->decryptData($encryptedData, $iv, $data);
            if ($errCode == 0) {
                //整理数据，并实现注册
                return json(['code'=>0, 'msg'=>'调用成功', 'data'=>array('id'=>101,'nickName'=>$data['nickName'], 'avatarUrl'=>$data['avatarUrl'])]);
            }else{
                //记录下日志
                return json(['code'=>1, 'msg'=>'用户数据解析错误', 'data'=>[]]);
            }
        }else{
            //授权问题，记录日志
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }

    public function order_list(Request $request){

    }

    public function order_detail(Request $request){

    }

    public function address_list(Request $request){

    }

    public function  add_address(Request $request){

    }

    public function edit_address(Request $request){

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
    
        $result=openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
    
        $dataObj=json_decode($result);
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