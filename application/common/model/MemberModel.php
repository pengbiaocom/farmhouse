<?php
namespace app\common\model;

/**
 * 文档基础模型
 */
class MemberModel extends BaseModel
{
    /* 用户模型自动完成 */
    protected $insert = [
        'login' => 0,
        'last_login_ip'=>0,
        'last_login_time'=>0,
        'reg_time',
        'reg_ip',
        'status'=>1,
        'pos_province'=>0,
        'pos_district'=>0,
        'pos_community'=>0,
    ];

    protected $rule = [
        'nickname'  =>  'require|length:2,32',
    ];

    protected $message = [
        'nickname.require'  =>  '昵称必填',
        'nickname.length'     => '昵称长度在2-32字符之间',
    ];

    protected $scene = [
        'add'   =>  ['nickname'],
        'edit'  =>  ['nickname'],
    ];

    protected  function setRegTimeAttr(){
        return time();
    }

    protected  function setRegIpAttr(){
        return get_client_ip(1);
    }

    public function getListPage($map,$order='uid desc',$r=30)
    {
        $list = $this->where($map)->order($order)->paginate($r,false);
        $page = $list->render();
        $data = $list->toArray();
        return [$data['data'],$page];
    }

    /**
     * 检测用户名是不是被禁止注册
     * @param  string $nickname 昵称
     * @return boolean          ture - 未禁用，false - 禁止注册
     */
    protected function checkDenyNickname($nickname)
    {
        $denyName = db("Config")->where(['name' => 'USER_NAME_BAOLIU'])->value('value');
        if ($denyName != '') {
            $denyName = explode(',', $denyName);
            foreach ($denyName as $val) {
                if (!is_bool(strpos($nickname, $val))) {
                    return false;
                }
            }
        }
        return true;
    }

    protected function checkNickname($nickname)
    {
        //如果用户名中有空格，不允许注册
        if (strpos($nickname, ' ') !== false) {
            return false;
        }
        preg_match('/^(?!_|\s\')[A-Za-z0-9_\x80-\xff\s\']+$/', $nickname, $result);

        if (!$result) {
            return false;
        }
        return true;
    }

    /**
     * 验证昵称长度
     * @param $nickname
     * @return bool
     */
    protected function checkNicknameLength($nickname)
    {
        $length = mb_strlen($nickname, 'utf-8'); // 当前数据长度
        if ($length < modC('NICKNAME_MIN_LENGTH',2,'USERCONFIG') || $length > modC('NICKNAME_MAX_LENGTH',32,'USERCONFIG')) {
            return false;
        }
        return true;
    }

    public function registerMember($nickname = '')
    {
        /* 在当前应用中注册用户 */
        $user['nickname'] = $nickname;
        $user['status'] = 1;
        $this->save($user);
        $uid = $this->uid;
        if(!$uid){
            $this->error = lang('_THE_FOREGROUND_USER_REGISTRATION_FAILED_PLEASE_TRY_AGAIN_WITH_EXCLAMATION_');
            return false;
        }
        return $uid;

    }


    /**
     * 登录指定用户
     * @param  integer $uid 用户ID
     * @param bool $remember
     * @param int $role_id 有值代表强制登录这个角色
     * @return boolean      ture-登录成功，false-登录失败
     */
    public function login($uid, $remember = false, $role_id = 0)
    {
        /* 检测是否在当前应用注册 */
        $user = $this->field(true)->find($uid);
        if ($role_id != 0) {
            $user['last_login_role'] = $role_id;
        } else {
                if (!intval($user['last_login_role'])) {
                    $user['last_login_role'] = $user['show_role'];
                }
        }
        session('temp_login_uid', $uid);
        session('temp_login_role_id', $user['last_login_role']);
        if ($user['status'] == 3 /*判断是否激活*/) {
            header('Content-Type:application/json; charset=utf-8');
            $data['status'] = 1;
            $data['url'] = url('Ucenter/Member/activate');

            if (Request()->isAjax()) {
                exit(json_encode($data));
            } else {
                redirect($data['url']);
            }
            return false;
        }

        if (1 != $user['status']) {
            $this->error = lang('_USERS_ARE_NOT_ACTIVATED_OR_DISABLED_WITH_EXCLAMATION_'); //应用级别禁用
            return false;
        }

        $step = db('UserRole')->where(['uid' => $uid, 'role_id' => $user['last_login_role']])->value('step');
        if (!empty($step) && $step != 'finish') {
            header('Content-Type:application/json; charset=utf-8');
            $data['status'] = 1;
            //执行步骤在start的时候执行下一步，否则执行此步骤
            $go = $step == 'start' ? get_next_step($step) : check_step($step);
            $data['url'] = url('Ucenter/Member/step', ['step' => $go]);
            if (Request()->isAjax()) {
                exit(json_encode($data));
            } else {
                redirect($data['url']);
            }
            return false;
        }

        /* 登录用户 */
        $this->autoLogin($user, $remember);

        session('temp_login_uid', null);
        session('temp_login_role_id', null);
        //记录行为
        action_log('user_login', 'member', $uid, $uid);
        return true;
    }

    /**
     * 注销当前用户
     * @return void
     */
    public function logout()
    {
        session('_AUTH_LIST_' . is_login() . '1', null);
        session('_AUTH_LIST_' . is_login() . '2', null);
        session('_AUTH_LIST_' . is_login() . 'in,1,2', null);
        session('user_auth', null);
        session('user_auth_sign', null);
        cookie('OX_LOGGED_USER', NULL);
    }

    /**
     * 自动登录用户
     * @param  integer $user 用户信息数组
     */
    private function autoLogin($user, $remember = false, $role_id = 0)
    {

        /* 更新登录信息 */
        $data = [
            'login' => ['exp', '`login`+1'],
            'last_login_time' => time(),
            'last_login_ip' => get_client_ip(1),
            'last_login_role' => $user['last_login_role'],
            'session_id'=>session_id(),
        ];
        $this->save($data,['uid'=>$user['uid']]);
        //判断角色用户是否审核
        $map['uid'] = $user['uid'];
        $map['role_id'] = $user['last_login_role'];
        $audit =  db('UserRole')->where($map)->value('status');
        //判断角色用户是否审核 end

        /* 记录登录SESSION和COOKIES */
        $auth = [
            'uid' => $user['uid'],
            'username' => get_username($user['uid']),
            'last_login_time' => $user['last_login_time'],
            'role_id' => $user['last_login_role'],
            'audit' => $audit,
        ];
        session('user_auth', $auth);
        session('user_auth_sign', data_auth_sign($auth));
        if ($remember) {
            $user1 = db('user_token')->where('uid=' . $user['uid'])->find();
            $token = $user1['token'];
            if ($user1 == null) {
                $token = build_auth_key();
                $data['token'] = $token;
                $data['time'] = time();
                $data['uid'] = $user['uid'];
                db('user_token')->insert($data);
            }
            $expire = 3600 * 24 * 7;
            cookie('OX_LOGGED_USER', $this->jiami($this->change() . ".{$user['uid']}.{$token}"), $expire);
        }
    }

    public function need_login()
    {
        if (!is_login()) {
            if ($uid = $this->getCookieUid()) {
                $this->login($uid);
                return true;
            }
        }

    }

    public function getCookieUid()
    {

        static $cookie_uid = null;
        if (isset($cookie_uid) && $cookie_uid !== null) {
            return $cookie_uid;
        }
        $cookie = cookie('OX_LOGGED_USER');
        $cookie = explode(".", $this->jiemi($cookie));
        $map['uid'] = $cookie[1];
        $user = db('user_token')->where($map)->find();
        $cookie_uid = ($cookie[0] != $this->change()) || ($cookie[2] != $user['token']) ? false : $cookie[1];
        $cookie_uid = $user['time'] - time() >= 3600 * 24 * 7 ? false : $cookie_uid;
        return $cookie_uid;
    }


    /**
     * 加密函数
     * @param string $txt 需加密的字符串
     * @param string $key 加密密钥，默认读取SECURE_CODE配置
     * @return string 加密后的字符串
     */
    private function jiami($txt, $key = null)
    {
        empty($key) && $key = $this->change();

        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-=_";
        $nh = rand(0, 64);
        $ch = $chars[$nh];
        $mdKey = md5($key . $ch);
        $mdKey = substr($mdKey, $nh % 8, $nh % 8 + 7);
        $txt = base64_encode($txt);
        $tmp = '';
        $i = 0;
        $j = 0;
        $k = 0;
        for ($i = 0; $i < strlen($txt); $i++) {
            $k = $k == strlen($mdKey) ? 0 : $k;
            $j = ($nh + strpos($chars, $txt [$i]) + ord($mdKey[$k++])) % 64;
            $tmp .= $chars[$j];
        }
        return $ch . $tmp;
    }

    /**
     * 解密函数
     * @param string $txt 待解密的字符串
     * @param string $key 解密密钥，默认读取SECURE_CODE配置
     * @return string 解密后的字符串
     */
    private function jiemi($txt, $key = null)
    {
        empty($key) && $key = $this->change();

        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-=_";
        $ch = $txt[0];
        $nh = strpos($chars, $ch);
        $mdKey = md5($key . $ch);
        $mdKey = substr($mdKey, $nh % 8, $nh % 8 + 7);
        $txt = substr($txt, 1);
        $tmp = '';
        $i = 0;
        $j = 0;
        $k = 0;
        for ($i = 0; $i < strlen($txt); $i++) {
            $k = $k == strlen($mdKey) ? 0 : $k;
            $j = strpos($chars, $txt[$i]) - $nh - ord($mdKey[$k++]);
            while ($j < 0) {
                $j += 64;
            }
            $tmp .= $chars[$j];
        }

        return base64_decode($tmp);
    }

    private function change()
    {
        preg_match_all('/\w/', config('DATA_AUTH_KEY'), $sss);
        $str1 = '';
        foreach ($sss[0] as $v) {
            $str1 .= $v;
        }
        return $str1;
    }


    /**
     * 设置角色用户默认基本信息
     * @param $role_id
     * @param $uid
     */
    public function initUserRoleInfo($role_id, $uid)
    {
        $roleModel = db('Role');
        $roleConfigModel = db('RoleConfig');
        $authGroupAccessModel = db('AuthGroupAccess');
        db('UserRole')->where(['role_id' => $role_id, 'uid' => $uid])->setField('init', 1);
        //默认权限组设置
        $role = $roleModel->where(['id' => $role_id])->find();
        if ($role['user_groups'] != '') {
            $role = explode(',', $role['user_groups']);

            //查询已拥有权限组
            $have_user_group_ids = $authGroupAccessModel->where(['uid' => $uid])->select();
            $have_user_group_ids = array_column($have_user_group_ids, 'group_id');
            //查询已拥有权限组 end

            $authGroupAccess['uid'] = $uid;
            $authGroupAccess_list = [];
            foreach ($role as $val) {
                if ($val != '' && !in_array($val, $have_user_group_ids)) { //去除已拥有权限组
                    $authGroupAccess['group_id'] = $val;
                    $authGroupAccess_list[] = $authGroupAccess;
                }
            }
            unset($val);
            $authGroupAccessModel->insertAll($authGroupAccess_list);
        }
        //默认权限组设置 end

        $map['role_id'] = $role_id;
        $map['name'] = ['in', ['score', 'rank']];
        $config = $roleConfigModel->where($map)->select();
        $config = array_combine(array_column($config, 'name'), $config);

        //默认头衔设置
        if (isset($config['rank']['value']) && $config['rank']['value'] != '') {
            $ranks = explode(',', $config['rank']['value']);
            if (count($ranks)) {
                //查询已拥有头衔
                $rankUserModel = db('RankUser');
                $have_rank_ids = $rankUserModel->where(['uid' => $uid])->select();
                $have_rank_ids = array_column($have_rank_ids, 'rank_id');
                //查询已拥有头衔 end

                $reason = json_decode($config['rank']['data'], true);
                $rank_user['uid'] = $uid;
                $rank_user['create_time'] = time();
                $rank_user['status'] = 1;
                $rank_user['is_show'] = 1;
                $rank_user['reason'] = $reason['reason'];
                $rank_user_list = [];
                foreach ($ranks as $val) {
                    if ($val != '' && !in_array($val, $have_rank_ids)) { //去除已拥有头衔
                        $rank_user['rank_id'] = $val;
                        $rank_user_list[] = $rank_user;
                    }
                }
                unset($val);
                $rankUserModel->insertAll($rank_user_list);
            }
        }
        //默认头衔设置 end
    }

    //默认显示哪一个角色的个人主页设置
    public function initDefaultShowRole($role_id, $uid)
    {
        $userRoleModel = db('UserRole');

        $roles = $userRoleModel->where(['uid' => $uid, 'status' => 1, 'role_id' =>['neq', $role_id]])->select();
        if (!count($roles)) {
            $data['show_role'] = $role_id;
            //执行member表默认值设置
            $this->where(['uid' => $uid])->save($data);
        }
    }

    //默认显示哪一个角色的个人主页设置 end

    /**
     * addSyncData
     * @param $uid
     * @param $info
     * @return mixed
     */
    public function addSyncData($uid, $info)
    {
        //去除特殊字符。
        $data['nickname'] = preg_replace('/[^A-Za-z0-9_\x80-\xff\s\']/', '', $info['nick']);
        // 截取字数
        $data['nickname'] = mb_substr($data['nickname'], 0, 32, 'utf-8');
        // 为空则随机生成
        if (empty($data['nickname'])) {
            $data['nickname'] = $this->rand_nickname();
        } else {
            if ($this->where(['nickname' => $data['nickname']])->select()) {
                $data['nickname'] .= '_' . $uid;
            }
        }
        $data['sex'] = $info['sex'];
        $data = $this->validate(
            ['signature', '0,100', -1, self::EXISTS_VALIDATE, 'length'],
            /* 验证昵称 */
            ['nickname', 'checkDenyNickname', -31, self::EXISTS_VALIDATE, 'callback'], //昵称禁止注册
            ['nickname', 'checkNickname', -32, self::EXISTS_VALIDATE, 'callback'],
            ['nickname', '', -30, self::EXISTS_VALIDATE, 'unique'])->create($data);

        $data['uid'] = $uid;
        $data = $this->validate(
            [
                'nickname'=>['checkNickname','checkDenyNickname']
            ],
            [
            'nickname.checkNickname' => '名称必须',
            'nickname.checkDenyNickname'     => '名称最多不能超过25个字符',
            ]
        )->save($data);
        $res = $this->save($data);
        return $res;
    }

    private function rand_nickname()
    {
        $nickname = create_rand(4);
        if ($this->where(['nickname' => $nickname])->select()) {
            $this->rand_nickname();
        } else {
            return $nickname;
        }
    }

    public function getNickName($uid){
        return $this->where(['uid'=>(int)$uid])->value('nickname');
    }


}
