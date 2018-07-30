<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageConfigBuilder;
use app\backstage\builder\BackstageListBuilder;
use app\common\model\ActionModel;
use app\common\model\MemberModel;
use app\common\model\ModuleModel;
use app\common\service\UserApiService;

class UserController extends BackstageController{
    /**
     * 用户管理首页
     */
    public function index()
    {
        $r = config("LIST_ROWS");
        $nickname = input('nickname', '', 'text');
        $aSeek = input('seek', 0, 'text');
        $map = [];
        switch ($aSeek) {
            case '0':
                break;
            case '1':
                if(!empty($nickname)){
                    $map['uid'] = intval($nickname);
                }
                break;
            case '2':
                if(!empty($nickname)){
                    $map['nickname'] = ['like', '%' . (string)$nickname . '%'];
                }
                break;
            case '3':
                if(!empty($nickname)){
                    $mapEmail['email'] = ['like', '%' . $nickname . '%'];
                    $temp = UCenterMember()->where($mapEmail)->field('id')->select();
                    foreach($temp as $val) {
                        $temp1[] = implode(',', $val);
                    }
                    $map['uid'] = ['in', $temp1];
                }
                break;
            case '4':
                if(!empty($nickname)){
                    $mapMobile['mobile'] = ['like', '%' . $nickname . '%'];
                    $temp = UCenterMember()->where($mapMobile)->field('id')->select();
                    foreach($temp as $val) {
                        $temp1[] = implode(',', $val);
                    }
                    $map['uid'] = ['in', $temp1];
                }
                break;
            default:
        }

        $map['is_admin'] = 1;

        $memberModel = new MemberModel();

        list($list,$page)=$memberModel->getListPage($map,'',$r);

        int_to_string($list);
        foreach($list as $key=>$v){
            $ext=query_user(['username','mobile','email'],$v['uid']);
            $list[$key]['username'] = $ext['username'];
            $list[$key]['mobile'] = $ext['mobile'];
            $list[$key]['email'] = $ext['email'];
            $list[$key]['id'] = $v['uid'];
        }

        $seek_array = [
            ['id'=>0,'value'=>lang('_SELECT_WAY_')],
            ['id'=>1,'value'=>lang('_UID_')],
            ['id'=>2,'value'=>lang('_NICKNAME_')],
            ['id'=>3,'value'=>lang('_MAILBOX_')],
            ['id'=>4,'value'=>lang('_CELL_PHONE_NUMBER_')],
        ];
        $builder = new BackstageListBuilder();
        return $builder->title(lang('_USER_LIST_'))
            ->buttonNew(url('user/add'))
            ->suggest(lang('_PW_RESET_TIP_'))
            ->ajaxButton(url('User/changestatus'),['method'=>'resumeUser'],lang('_ENABLED_'))
            ->ajaxButton(url('User/changestatus'),['method'=>'forbidUser'],lang('_DISABLE_'))
            ->button(lang('_DELETE_'),['class'=>'layui-btn layui-btn-danger ajax-post confirm','url'=>url('User/changestatus',['method'=>'forbidUser']),'target-form'=>'ids'])
            ->button(lang('_USER_GROUP_SELECT_'),['class'=>'layui-btn','onclick'=>'post_select_form()','target-form'=>'ids'])
            ->button(lang('_PW_RESET_'),['class'=>'layui-btn layui-btn-warm ajax-post confirm','data-confirm'=>lang('_PW_RESET_CONFIRM_'),'url'=>url('User/initpass'),'target-form'=>'ids'])
            ->setSearchPostUrl(url('index'))
            ->searchText('','nickname','text',lang('_PLACEHOLDER_NONE_'))
            ->searchSelect('','seek','select','','lay-filter="seek"',$seek_array)
            ->keyText('uid',lang('_UID_'))
            ->keyText('nickname',lang('_NICKNAME_'))
            ->keyText('username',lang('_USERNAME_'))
            ->keyText('mobile',lang('_MOBILE_PHONE_'))
            ->keyText('email',lang('_MAILBOX_'))
            ->keyTime('last_login_time',lang('_LAST_LOGIN_TIME_'))
            ->keyText('last_login_ip',lang('_LOGIN_IP_LAST_TIME_'))
            ->keyStatus()
            ->keyDoActionAjax('User/changestatus?method=forbidUser&ids=###',lang('_DISABLE_'),['class'=>'ajax-get confirm'])
            ->keyDoActionAjax('User/changestatus?method=resumeUser&ids=###',lang('_ENABLE_'),['class'=>'ajax-get confirm'])
            ->keyDoAction('AuthManager/group?uid=###',lang('_ACCREDIT_'))
            ->keyDoActionAjax('User/changestatus?method=deleteUser&ids=###',lang('_DELETE_'),['class'=>'ajax-get confirm'])
            ->keyDoActionAjax('User/initpass?id=###',lang('_PW_RESET_'),['class'=>'ajax-get confirm'])
            ->data($list)
            ->pagination($page)->show();
    }

    public function add(){
        if($this->request->isPost()){
            $data['username'] = trim($this->request->param('username'));
            $data['password'] = trim($this->request->param('password'));
            $data['mobile'] = trim($this->request->param('mobile'));
            $passwd = trim($this->request->param('passwd'));
            $nickname = trim($this->request->param('nickname'));
            if(empty($data['username']))  return json(['code'=>1,'msg'=>'请填写用户名']);
            if(empty($data['password']) || empty($passwd)) return json(['code'=>1,'msg'=>'请填写密码']);
            if($data['password']!=$passwd)  return json(['code'=>1,'msg'=>'两次输入的密码不一致']);
            $data['password'] = think_ucenter_md5($data['password'],UC_AUTH_KEY);
            if(empty($nickname)) return json(['code'=>1,'msg'=>'请填写用户昵称']);

            if($retult = db("member")->insertGetId(['nickname'=>$nickname,'is_admin'=>1])){
                $data['id'] = $retult;
                $data['reg_time'] = time();
                $data['reg_ip'] = get_client_ip(1);
                $data['status'] = 1;
                if(db("ucenter_member")->insert($data)){
                    return json(['code'=>0,'msg'=>'添加成功']);
                }else{
                    //如果注册失败，则回去Memeber表删除掉错误的记录
                    db("member")->where(['uid' => $retult])->delete();
                }
            }else{
                return json(['code'=>1,'msg'=>'添加管理员失败']);
            }
        }else{
            $data=[];
            $builder = new BackstageConfigBuilder();
            $builder->title("新增管理员")
                ->data($data)
                ->keyText('username', "用户名")
                ->keyText('nickname', "用户昵称")
                ->keyText('mobile',lang('_MOBILE_PHONE_'))
                ->keyPassword('password',"登录密码")
                ->keyPassword('passwd',"重复密码")
                ->buttonSubmit(url('user/add'))->buttonBack();
            return $builder->show();
        }
    }

    /**
     * 重置用户密码
     */
    public function initpass()
    {
        $uids = input('id');
        !is_array($uids) && $uids = explode(',', $uids);
        foreach ($uids as $key => $val) {
            if (!query_user('uid', $val)) {
                unset($uids[$key]);
            }
        }
        if (!count($uids)) {
            $this->error(lang('_ERROR_USER_RESET_SELECT_').lang('_EXCLAMATION_'));
        }
        $data['password'] = "123456";
        $res = UCenterMember()->allowField(['password'])->save(['password' => $data['password']],['id' =>['in', $uids]]);
        if ($res) {
            $this->success(lang('_SUCCESS_PW_RESET_').lang('_EXCLAMATION_'));
        } else {
            $this->error(lang('_ERROR_PW_RESET_'));
        }
    }

    /**
     * 修改分组
     * @return \think\response\View
     * @throws \think\Exception
     */
    public function changegroup()
    {
        if(Request()->isPost()){
            //清空group
            $aAll = input('all', 0, 'intval');
            $aUids = input('uid/a', []);
            $aGids = input('gid/a', []);

            if ($aAll) {//设置全部用户
                $prefix = config('database.prefix');
                db('')->execute("TRUNCATE TABLE {$prefix}auth_group_access");
                $aUids = UCenterMember()->value('id');

            } else {
                db('AuthGroupAccess')->where(['uid' => ['in', implode(',', $aUids)]])->delete();
            }
            foreach ($aUids as $uid) {
                foreach ($aGids as $gid) {
                    db('AuthGroupAccess')->insert(['uid' => $uid, 'group_id' => $gid]);
                }
            }

            $this->success(lang('_SUCCESS_'));
        }else{
            $aId = input('id');
            if(!empty($aId)){
                $aId = explode(',',$aId);
                foreach ($aId as $uid) {
                    $user[] = query_user(['nickname', 'uid'], $uid);
                }
            }else{
                $user[] = [];
            }

            $groups = db('AuthGroup')->where(['status' => 1])->select();
            $this->assign('groups', $groups);
            $this->assign('users', $user);
            return $this->fetch();
        }

    }

    /**
     * 修改昵称
     */
    public function updatenickname()
    {
        $memberModel = new MemberModel();
        if(Request()->isPost()){
            //获取参数
            $nickname = input('nickname');
            $password = input('password');
            empty($nickname) && $this->error(lang('_PLEASE_ENTER_A_NICKNAME_'));
            empty($password) && $this->error(lang('_PLEASE_ENTER_THE_PASSWORD_'));

            //密码验证
            $User = new UserApiService();
            $uid = $User->login(UID, $password, 4);
            ($uid == -2) && $this->error(lang('_INCORRECT_PASSWORD_'));

            $data['nickname'] = $nickname;
            $res = $memberModel->allowField(['nickname'])->save($data,['uid'=>$uid]);

            if ($res) {
                $user = session('user_auth');
                $user['username'] = $data['nickname'];
                session('user_auth', $user);
                session('user_auth_sign', data_auth_sign($user));
                $this->success(lang('_MODIFY_NICKNAME_SUCCESS_'),url('index/index'));
            } else {
                $this->error(lang('_MODIFY_NICKNAME_FAILURE_'));
            }
        }else{
            $nickname = $memberModel->where("uid=".UID)->value('nickname');
            $data = [];
            $data['uid'] = UID;
            $data['nickname'] = $nickname;
            $builder=new BackstageConfigBuilder();

            return $builder->title(lang('_MODIFY_NICKNAME_'))
                ->keyHidden('uid','')
                ->keyPassword('password',lang('_PASSWORD_').lang('_COLON_'),lang('_PLEASE_ENTER_THE_PASSWORD_'))
                ->keyText('nickname',lang('_NICKNAME_').lang('_COLON_'),lang('_PLEASE_ENTER_A_NEW_NICKNAME_'))
                ->buttonSubmit()
                ->buttonBack()
                ->data($data)->show();
        }

    }

    /**
     * 修改密码
     * @return \think\response\View
     */
    public function updatepassword(){
        if(Request()->isPost()){
           //获取参数
            $password = input('old');
            empty($password) && $this->error(lang('_PLEASE_ENTER_THE_ORIGINAL_PASSWORD_'));
            $data['password'] = input('password');
            empty($data['password']) && $this->error(lang('_PLEASE_ENTER_A_NEW_PASSWORD_'));
            $repassword = input('repassword');
            empty($repassword) && $this->error(lang('_PLEASE_ENTER_THE_CONFIRMATION_PASSWORD_'));

            if ($data['password'] !== $repassword) {
                $this->error(input('_YOUR_NEW_PASSWORD_IS_NOT_CONSISTENT_WITH_THE_CONFIRMATION_PASSWORD_'));
            }

            $res = UCenterMember()->changePassword($password, $data['password']);
            if ($res) {
                $this->success(lang('_CHANGE_PASSWORD_SUCCESS_'));
            } else {
                $this->error(UCenterMember()->getErrorMessage($res['info']));
            }
        }else{
            $data = [];
            $data['uid'] = UID;
            $builder=new BackstageConfigBuilder();

            return $builder->title(lang('_CHANGE_PASSWORD_'))
                ->keyHidden('uid','')
                ->keyPassword('old',lang('_ORIGINAL_PASSWORD_').lang('_COLON_'))
                ->keyPassword('password',lang('_NEW_PASSWORD_').lang('_COLON_'))
                ->keyPassword('repassword',lang('_CONFIRM_PASSWORD_').lang('_COLON_'))
                ->buttonSubmit()
                ->buttonBack()
                ->data($data)->show();
        }
    }

    /**
     * 用户行为列表
     */
    public function action()
    {
        $r = config("LIST_ROWS");
        $module = $this->request->param('module');
        if (!empty($module) && $module != -1) {
            $map['module'] = $module;
        }
        $map['status'] = ['gt', -1];

        $actionModel = new ActionModel();

        list($list,$page)=$actionModel->getListPage($map,'',$r);

        lists_plus($list);
        int_to_string($list);
        // 记录当前列表页的cookie
        Cookie('__forward__', $_SERVER['REQUEST_URI']);
        if(!empty($list)){
            foreach($list as $key=>$row){
                $list[$key]['type'] =  get_action_type($row['type']);
            }
        }
        $moduleModel = new ModuleModel();
        $module =$moduleModel->getAll();

        $seek_array = [];
        if(!empty($module)){
            foreach ($module as $key => $v) {
                if ($v['is_setup'] == false) {
                    unset($module[$key]);
                }else{
                    $seek_array[$key]['id'] = $v['name'];
                    $seek_array[$key]['value'] = $v['alias'];
                }
            }
        }

        $seek_array = array_merge([['id' => '-1', 'value' => lang('_SYSTEM_')]], $seek_array);

        $this->assign('$moduleModel',lang('_USER_BEHAVIOR_'));
        $builder = new BackstageListBuilder();

        return $builder->title(lang('_BEHAVIOR_LIST_'))
            ->buttonNew(url('user/addaction'))
            ->ajaxButton(url('setstatus'),['Model'=>'action','status'=>1],lang('_KAI_WITH_SPACE_'))
            ->ajaxButton(url('setstatus'),['Model'=>'action','status'=>0],lang('_BAN_WITH_SPACE_'))
            ->buttonDeleteTrue(url('user/delaction'))

            ->setSearchPostUrl(url('action'))
            ->searchSelect(lang('_THE_MODULE_').lang('_COLON_'),'module','select','','',$seek_array)
            ->keyText('id',lang('_ID_'))
            ->keyText('name',lang('_IDENTIFICATION_'))
            ->keyText('title',lang('_NAME_'))
            ->keyText('alias',lang('_THE_MODULE_'))
            ->keyText('type',lang('_TYPE_'))
            ->keyText('remark',lang('_RULE_'))
            ->keyText('status_text',lang('_STATE_'))
            ->keyDoAction('User/editaction?id=###',lang('_EDIT_'))
            ->keyDoActionAjax('User/setstatus?Model=action&status=-1&ids=###',lang('_DELETE_'),['class'=>'ajax-get confirm'])
            ->data($list)
            ->pagination($page)->show();
    }

    public function delaction(){
        $param = $this->request->param();
        $ids =$param['ids'];
        $map['id'] = array('in', $ids);
        $actionModel = new ActionModel();
        if($actionModel->where($map)->delete()){
            $this->success(lang('_DELETE_SUCCESS_EXCLAMATION_'));
        }else{
            $this->error(lang('_DELETE_FAILED_EXCLAMATION_'));
        }
    }

    /**
     * 新增行为
     */
    public function addaction()
    {
        $moduleModel = new ModuleModel();
        $module = $moduleModel->getAll();

        $this->assign('module', $module);
        $this->assign('data', ['name'=>'','title'=>'','module'=>'','remark'=>'','rule'=>'','log'=>'','id'=>'']);
        $this->assign('meta_title',lang('_NEW_BEHAVIOR_'));
        return $this->fetch('editaction');
    }

    /**
     * 编辑行为
     */
    public function editaction(){
        $id = input('id');
        empty($id) && $this->error(lang('_PARAMETERS_CANT_BE_EMPTY_'));
        $data = db('Action')->field(true)->find($id);
        $moduleModel = new ModuleModel();
        $module = $moduleModel->getAll();

        $this->assign('module', $module);
        $this->assign('data', $data);
        $this->assign('meta_title',lang('_EDITING_BEHAVIOR_'));
        return $this->fetch('editaction');
    }

    /**
     * 更新行为
     */
    public function saveaction()
    {
        $actionModel = new ActionModel();
        $res = $actionModel->updates();
        if (!$res) {
            $this->error($actionModel->getError());
        } else {
            $this->success($res['id'] ? lang('_UPDATE_SUCCESS_') : lang('_NEW_SUCCESS_'), Cookie('__forward__'));
        }
    }

    /**
     * 会员状态修改
     * @param null $method
     */
    public function changestatus($method = null)
    {
        $id = array_unique($this->request->param('ids/a'));
        if (count(array_intersect(explode(',', config('user_administrator')), $id)) > 0) {
            $this->error(lang('_DO_NOT_ALLOW_THE_SUPER_ADMINISTRATOR_TO_PERFORM_THE_OPERATION_'));
        }
        $id = is_array($id) ? implode(',', $id) : $id;
        if (empty($id)) {
            $this->error(lang('_PLEASE_CHOOSE_TO_OPERATE_THE_DATA_'));
        }
        $map['uid'] = ['in', $id];
        $map1['id'] = ['in', $id];
        $memberModel = new MemberModel();
        switch (strtolower($method)) {
            case 'forbiduser':
                $data = ['status' => 0];
                if(UCenterMember()->save($data,$map1)){
                    $memberModel->save($data,$map);
                    $this->success("禁用成功！");
                }else{
                    $this->error("禁用失败！");
                }
                break;
            case 'resumeuser':
                $data = ['status' => 1];
                if(UCenterMember()->save($data,$map1)){
                    $memberModel->save($data,$map);
                    $this->success("启用成功！");
                }else{
                    $this->error("启用失败！");
                }
                break;
            case 'deleteuser':
                if(UCenterMember()->where($map1)->delete()){
                    $memberModel->where($map)->delete();
                    $this->success("删除成功！");
                }else{
                    $this->error("删除失败！");
                }
                break;
            default:
                $this->error( '参数非法');

        }
    }

    /**
     * 获取用户注册错误信息
     * @param  integer $code 错误编码
     * @return string        错误信息
     */
    private function showRegError($code = 0)
    {
        switch ($code) {
            case -1:
                $error = lang('_USER_NAME_MUST_BE_IN_LENGTH_') . modC('USERNAME_MIN_LENGTH', 2, 'USERCONFIG') . '-' . modC('USERNAME_MAX_LENGTH', 32, 'USERCONFIG') . lang('_BETWEEN_CHARACTERS_');
                break;
            case -2:
                $error = lang('_USER_NAME_IS_FORBIDDEN_TO_REGISTER_');
                break;
            case -3:
                $error = lang('_USER_NAME_IS_OCCUPIED_');
                break;
            case -4:
                $error = lang('_PASSWORD_LENGTH_MUST_BE_BETWEEN_6-30_CHARACTERS_');
                break;
            case -5:
                $error = lang('_MAILBOX_FORMAT_IS_NOT_CORRECT_');
                break;
            case -6:
                $error = lang('_MAILBOX_LENGTH_MUST_BE_BETWEEN_1-32_CHARACTERS_');
                break;
            case -7:
                $error = lang('_MAILBOX_IS_PROHIBITED_TO_REGISTER_');
                break;
            case -8:
                $error = lang('_MAILBOX_IS_OCCUPIED_');
                break;
            case -9:
                $error = lang('_MOBILE_PHONE_FORMAT_IS_NOT_CORRECT_');
                break;
            case -10:
                $error = lang('_MOBILE_PHONES_ARE_PROHIBITED_FROM_REGISTERING_');
                break;
            case -11:
                $error = lang('_PHONE_NUMBER_IS_OCCUPIED_');
                break;
            case -12:
                $error = lang('_USER_NAME_MY_RULE_').lang('_EXCLAMATION_');
                break;
            default:
                $error = lang('_UNKNOWN_ERROR_');
        }
        return $error;
    }

    public function getnickname()
    {
        $uid = input('uid', 0, 'intval');
        if ($uid) {
            $user = query_user(null, $uid);
            return $user;
        } else {
            return null;
        }

    }

}