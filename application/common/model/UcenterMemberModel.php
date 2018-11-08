<?php
namespace app\common\model;

/**
 * 会员模型
 */
class UcenterMemberModel extends BaseModel
{

    /* 用户模型自动验证 */
    protected $rule = [
        'username'  =>  'require|length:2,32',
        'password'  =>  'require|length:6,30',
        'email' =>  'email',
        'mobile'=>['regex'=>'/^(1[3|4|5|7|8])[0-9]{9}$/'],
    ];

    protected $message = [
        'name.require'  =>  '用户名必填',
        'name.length'     => '名称长度在2-32字符之间',
        'password.require' =>'密码必填',
        'password.length'=>'密码长度在6-3-字符之间',
        'email' =>  '邮箱格式错误',
        'mobile'=>'请输入正确的手机号',
    ];

    protected $scene = [
        'add'   =>  ['username','password','email','mobile'],
        'edit'  =>  ['email','mobile'],
    ];

    /* 用户模型自动完成 */
    protected $insert = ['status' => 1,'password','reg_time','reg_ip','update_time'];

    protected  function setPasswordAttr($value){
        return think_ucenter_md5($value,UC_AUTH_KEY);
    }

    protected  function setRegTimeAttr(){
        return time();
    }

    protected  function setRegIpAttr(){
        return get_client_ip(1);
    }

    protected  function setUpdateTimeAttr(){
        return time();
    }


    /**
     * 检测用户名是不是被禁止注册(保留用户名)
     * @param  string $username 用户名
     * @return boolean          ture - 未禁用，false - 禁止注册
     */
    protected function checkDenyMember($username)
    {
        $config = db('Config')->where(['name' => 'USER_NAME_BAOLIU'])->find();
        $denyName=$config['value'];
        if($denyName!=''){
            $denyName=explode(',',$denyName);
            foreach($denyName as $val){
                if(!is_bool(strpos($username,$val))){
                    return false;
                }
            }
        }
        return true;
    }

    protected function checkUsername($username)
    {

        //如果用户名中有空格，不允许注册
        if (strpos($username, ' ') !== false) {
            return false;
        }
        preg_match("/^[a-zA-Z0-9_]{0,64}$/", $username, $result);

        if (!$result) {
            return false;
        }
        return true;
    }

    /**
     * 验证用户名长度
     * @param $username
     * @return bool
     */
    protected function checkUsernameLength($username)
    {
        $length = mb_strlen($username, 'utf-8'); // 当前数据长度
        if ($length < modC('USERNAME_MIN_LENGTH',2,'USERCONFIG') || $length > modC('USERNAME_MAX_LENGTH',32,'USERCONFIG')) {
            return false;
        }
        return true;
    }

    /**
     * 注册一个新用户
     * @param  string $username 用户名
     * @param  string $nickname 昵称
     * @param  string $password 用户密码
     * @param  string $email 用户邮箱
     * @param  string $mobile 用户手机号码
     * @return integer          注册成功-用户信息，注册失败-错误编号
     */
    public function register($openid, $session_key, $username, $nickname, $password, $invit, $email='', $mobile='', $type=1)
    {
        $data = [
            'openid' => $openid,
            'session_key' => $session_key,
            'username' => $username,
            'password' => $password,
            'invit' => $invit,
            'invit_time' => strtotime(date('Ymd')),
            'email' => $email,
            'mobile' => $mobile,
            'type' => $type,
        ];

        //验证手机
        if (empty($data['mobile'])) unset($data['mobile']);
        if (empty($data['username'])) unset($data['username']);
        if (empty($data['email'])) unset($data['email']);

        /* 添加用户 */
        $member = new MemberModel();
        $result = $member->registerMember($nickname);
        if($result>0){
            $data['id'] = $result;
            $this->allowField(true)->save($data);
            $uid = $this->id;
            if ($uid === false) {
                //如果注册失败，则回去Memeber表删除掉错误的记录
                $member->where(['uid' => $result])->delete();
            }
            action_log('reg','ucenter_member',1,1);
            return $uid ? $uid : 0; //0-未知错误，大于0-注册成功
        }else{
            return $result;
        }
    }

    /**
     * 用户登录认证
     * @param  string  $username 用户名
     * @param  string  $password 用户密码
     * @param  integer $type 用户名类型 （1-用户名，2-邮箱，3-手机，4-UID）
     * @return integer           登录成功-用户ID，登录失败-错误编号
     */
    public function login($username, $password, $type = 1)
    {
        $map = [];
        switch ($type) {
            case 1:
                $map['username'] = $username;
                break;
            case 2:
                $map['email'] = $username;
                break;
            case 3:
                $map['mobile'] = $username;
                break;
            case 4:
                $map['id'] = $username;
                break;
            default:
                return 0; //参数错误
        }
        /* 获取用户数据 */
        $user = $this->where($map)->find();

        $return = check_action_limit('input_password','ucenter_member',$user['id'],$user['id']);

        if($return && !$return['state']){
            return $return['info'];
        }

        if (is_object($user) && $user['status']) {
            /* 验证用户密码 */
            if (think_ucenter_md5($password, UC_AUTH_KEY) === $user['password']) {
                $this->updateLogin($user['id']); //更新用户登录信息
                return $user['id']; //登录成功，返回用户ID
            } else {
                action_log('input_password','ucenter_member',$user['id'],$user['id']);
                return -2; //密码错误
            }
        } else {
            return -1; //用户不存在或被禁用
        }
    }

    /**
     * 初始化角色用户信息
     * @param $role_id
     * @param $uid
     * @return bool
     */
    public  function initRoleUser($role_id = 0, $uid)
    {
        $memberModel = new MemberModel();
        $role = db('Role')->where(['id' => $role_id])->find();
        if ($role['audit']) { //该角色需要审核
            $user_role['status'] = 2; //未审核
        } else {
            $user_role['status'] = 1;
        }
        $result = db('UserRole')->insert($user_role,['uid'=>$uid,'role_id'=>$role_id,'step' => "start"]);
        if (!$role['audit']) {
            //该角色不需要审核
            $memberModel->initUserRoleInfo($role_id, $uid);
        }
        $memberModel->initDefaultShowRole($role_id, $uid);

        return $result;
    }


    public function getLocal($username, $password)
    {
        $aUsername = $username;
        check_username($aUsername, $email, $mobile, $type);

        switch ($type) {
            case 1:
                $map = ['username'=>$username];
                break;
            case 2:
                $map = ['email'=>$username];
                break;
            case 3:
                $map = ['mobile'=>$username];
                break;
            case 4:
                $map = ['id'=>$username];
                break;
            default:
                return 0; //参数错误
        }

        /* 获取用户数据 */
        $user = $this->where($map)->find();

        if (is_object($user) && $user['status']) {
            /* 验证用户密码 */
            if (think_ucenter_md5($password, UC_AUTH_KEY) === $user['password']) {
                return $user; //登录成功，返回用户ID
            } else {
                return false; //密码错误
            }
        } else {
            return false; //用户不存在或被禁用
        }
    }

    /**
     * 用户密码找回认证
     * @param  string  $username 用户名
     * @param  string  $password 用户密码
     * @param  integer $type 用户名类型 （1-用户名，2-邮箱，3-手机，4-UID）
     * @return integer           登录成功-用户ID，登录失败-错误编号
     */
    public function lomi($username, $email)
    {
        $map = ['username'=>$username,'email'=>$email];
        /* 获取用户数据 */
        $user = $this->where($map)->find();
        if (is_object($user)) {
            return $user; //成功，返回用户最后登录时间
        } else {
            return -2; //用户和邮箱不符
        }
    }

    /**
     * 用户密码找回认证2
     * @param  string  $username 用户名
     * @param  string  $password 用户密码
     * @param  integer $type 用户名类型 （1-用户名，2-邮箱，3-手机，4-UID）
     * @return integer           登录成功-用户ID，登录失败-错误编号
     */
    public function reset($uid)
    {
        $map = ['id'=>$uid];
        /* 获取用户数据 */
        $user = $this->where($map)->find();
        if (is_object($user)) {
            return $user; //成功，返回用户数据
        } else {
            return -2; //用户和邮箱不符
        }
    }

    /**
     * 根据IP获取用户最后注册时间
     * @param  string  $uid 用户ID或用户名
     * @param  boolean $is_username 是否使用用户名查询
     * @return array                用户信息
     */
    public function infos($regip)
    {
        $map=['reg_ip'=>$regip];
        $user = $this->where($map)->max('reg_time');
        if ($user) {
            return $user;
        } else {
            return -1; //用户不存在或被禁用
        }
    }

    /**
     * 获取用户信息
     * @param  string  $uid 用户ID或用户名
     * @param  boolean $is_username 是否使用用户名查询
     * @return array                用户信息
     */
    public function info($uid, $is_username = false)
    {
        if ($is_username) { //通过用户名获取
            $map = ['username'=>$uid];
        } else {
            $map = ['id'=>$uid];
        }

        $user = $this->where($map)->field('id,username,email,mobile,status')->find();
        if (is_object($user) && $user['status'] = 1) {
            return [$user['id'], $user['username'], $user['email'], $user['mobile']];
        } else {
            return -1; //用户不存在或被禁用
        }
    }

    /**
     * 检测用户信息
     * @param  string  $field 用户名
     * @param  integer $type 用户名类型 1-用户名，2-用户邮箱，3-用户电话
     * @return integer         错误编号
     */
    public function checkField($field, $type = 1)
    {
        switch ($type) {
            case 1:
                $data = ['username'=>$field];
                break;
            case 2:
                $data = ['email'=>$field];
                break;
            case 3:
                $data = ['mobile'=>$field];
                break;
            default:
                return 0; //参数错误
        }

        return $this->create($data) ? 1 : $this->getError();
    }

    /**
     * 更新用户登录信息
     * @param  integer $uid 用户ID
     */
    protected function updateLogin($uid)
    {
        $data = [
            'last_login_time'=>time(),
            'last_login_ip'=>get_client_ip(1),
        ];
        $this->save($data,['id'=>$uid]);
    }

    /**
     * 更新用户信息
     * @param int    $uid 用户id
     * @param string $password 密码，用来验证
     * @param array  $data 修改的字段数组
     * @return true 修改成功，false 修改失败
     */
    public function updateUserFields($uid, $password, $data)
    {
        if (empty($uid) || empty($password) || empty($data)) {
            $this->error = lang('_PARAM_ERROR_25_');
            return false;
        }

        //更新前检查用户密码
        if (!$this->verifyUser($uid, $password)) {
            $this->error = lang('_VERIFY_ERROR_PW_WRONG_');
            return false;
        }

        //更新用户信息
        return $this->allowField(true)->isUpdate(true)->save($data,['id'=>$uid]);
    }

    /**
     * 重置用户密码
     * @param int    $uid 用户id
     * @param string $password 密码，用来验证
     * @param array  $data 修改的字段数组
     * @return true 修改成功，false 修改失败
     */
    public function updateUserFieldss($uid, $data)
    {
        if (empty($uid) || empty($data)) {
            $this->error = lang('_PARAM_ERROR_25_');
            return false;
        }
        //更新用户信息
        return $this->allowField(true)->isUpdate(true)->save($data,['id'=>$uid]);
    }

    /**
     * 验证用户密码
     * @param int    $uid 用户id
     * @param string $password_in 密码
     * @return true 验证成功，false 验证失败
     */
    public function verifyUser($uid, $password_in)
    {
        $data = $this->where(['id'=>$uid])->find();
        $password = $data['password'];
        if (think_ucenter_md5($password_in, UC_AUTH_KEY) === $password) {
            return true;
        }
        return false;
    }




    /**修改密码
     * @param $old_password
     * @param $new_password
     * @return bool
     */
    public function changePassword($old_password, $new_password)
    {
        //检查旧密码是否正确
        if (!$this->verifyUser(is_login(), $old_password)) {
            $this->error = -41;
            return false;
        }
        //更新用户信息
        $model = $this;
        $data = ['password' => $new_password];
        $model->allowField(['password'])->save($data,['id'=>is_login()]);
        //返回成功信息
        clean_query_user_cache(is_login(), 'password');//删除缓存
        db('user_token')->where('uid=' . is_login())->delete();
        return true;
    }

    public function getErrorMessage($error_code = null)
    {

        $error = $error_code == null ? $this->error : $error_code;
        switch ($error) {
            case -1:
                $error = lang('_USER_NAME_MUST_BE_IN_LENGTH_').modC('USERNAME_MIN_LENGTH',2,'USERCONFIG').'-'.modC('USERNAME_MAX_LENGTH',32,'USERCONFIG').lang('_BETWEEN_CHARACTERS_WITH_EXCLAMATION_');
                break;
            case -2:
                $error = lang('_USER_NAME_IS_FORBIDDEN_TO_REGISTER_WITH_EXCLAMATION_');
                break;
            case -3:
                $error = lang('_USER_NAME_IS_OCCUPIED_WITH_EXCLAMATION_');
                break;
            case -4:
                $error = lang('_PW_LENGTH_6_30_');
                break;
            case -41:
                $error = lang('_USERS_OLD_PASSWORD_IS_INCORRECT_');
                break;
            case -5:
                $error = lang('_MAILBOX_FORMAT_IS_NOT_CORRECT_WITH_EXCLAMATION_');
                break;
            case -6:
                $error = lang('_EMAIL_LENGTH_4_32_');
                break;
            case -7:
                $error = lang('_MAILBOX_IS_PROHIBITED_TO_REGISTER_WITH_EXCLAMATION_');
                break;
            case -8:
                $error = lang('_MAILBOX_IS_OCCUPIED_WITH_EXCLAMATION_');
                break;
            case -9:
                $error = lang('_MOBILE_PHONE_FORMAT_IS_NOT_CORRECT_WITH_EXCLAMATION_');
                break;
            case -10:
                $error = lang('_MOBILE_PHONES_ARE_PROHIBITED_FROM_REGISTERING_WITH_EXCLAMATION_');
                break;
            case -11:
                $error = lang('_PHONE_NUMBER_IS_OCCUPIED_WITH_EXCLAMATION_');
                break;
            case -12:
                $error = lang('_UN_LIMIT_SOME_');
                break;
            case -31:
                $error = lang('_THE_NICKNAME_IS_PROHIBITED_');
                break;
            case -33:
                $error = lang('_NICKNAME_LENGTH_MUST_BE_IN_').modC('NICKNAME_MIN_LENGTH',2,'USERCONFIG').'-'.modC('NICKNAME_MAX_LENGTH',32,'USERCONFIG').lang('_BETWEEN_CHARACTERS_WITH_EXCLAMATION_');
                break;
            case -32:
                $error = lang('_THE_NICKNAME_IS_NOT_LEGAL_');
                break;
            case -30:
                $error = lang('_THE_NICKNAME_HAS_BEEN_OCCUPIED_');
                break;

            default:
                $error = lang('_UNKNOWN_ERROR_');
        }
        return $error;
    }


    /**
     * addSyncData
     * @return mixed
     */
    public function addSyncData()
    {
        $data = ['email'=>$this->rand_email(),'password'=>'123456','type'=>2]; // type=2 视作用邮箱注册
        $uid = $this->allowField(['email','password','type'])->save($data);
        return $uid;
    }

    protected  function rand_email()
    {
        $email = create_rand(10) . modC('SYNC_LOGIN_EMAIL_SUFFIX', '@think28.com', 'USERCONFIG');
        if ($this->where(['email' => $email])->select()) {
            $this->rand_email();
        } else {
            return $email;
        }
    }

}
