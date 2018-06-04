<?php
namespace app\common\model;

class VerifyModel extends BaseModel
{
    protected $insert = [
        'create_time'
    ];

    protected  function setCreateTimeAttr(){
        return time();
    }

    public function addVerify($account, $type, $uid = 0,$check_verify = 1)
    {

        if($check_verify){
            $aVerify = input('post.verify', '', 'text');
            if (empty($aVerify)) {
                $this->error = '验证码不能为空';
                return false;
            }

            $verify_id = $type=='email'? 3 : 2;
            $captcha = new \think\captcha\Captcha();
            if (! $captcha->check($aVerify,$verify_id)) {
                $this->error =  '验证码验证错误~';
                return false;
            }
        }


        $return = check_action_limit('send_verify', 'Ucenter',0, 1, false);//通过行为限制在全站层面防止频繁发送验证码
        if ($return && !$return['state']) {
            $this->error = $return['info'];
            return false;
        }
        action_log('send_verify', 'Ucenter',-1,1);


        $uid = $uid ? $uid : is_login();
        if ($type == 'mobile' || (modC('EMAIL_VERIFY_TYPE', 0, 'USERCONFIG') == 2 && $type == 'email')) {
            $verify = create_rand(6, 'num');
        } else {
            $verify = create_rand(32);
        }
        $this->where(['account' => $account, 'type' => $type])->delete();
        $data['verify'] = $verify;
        $data['account'] = $account;
        $data['type'] = $type;
        $data['uid'] = $uid;
        $res = $this->allowField(true)->save($data);
        if (!$res) {
            $this->error = '';
            return false;
        }
        return $verify;
    }

    public function getVerify($id)
    {
        $verify = $this->where(['id' => $id])->value('verify');
        return $verify;
    }

    public function checkVerify($account, $type, $verify, $uid)
    {
        $verify1 = $this->where(['account' => $account, 'type' => $type, 'verify' => $verify, 'uid' => $uid])->select();
        if (!$verify1) {
            return false;
        }

        $this->where(['account' => $account, 'type' => $type])->delete();

        return true;
    }

}
