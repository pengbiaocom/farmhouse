<?php
/**
 * Login.php  后台登录控制器
 * @author : pengxuancom@163.com
 * @date : 2017.07.14
 * @version : v1.0.0.0
 */
namespace app\backstage\controller;

use app\common\model\MemberModel;
use app\common\model\ConfigModel;
use app\common\service\UserApiService;

class LoginController extends BackstageController{

    public function _initialize()
    {
        /* 读取数据库中的配置 */
        $config	=	cache('DB_CONFIG_DATA');
        if(!$config){
            $config = new ConfigModel();
            $config	=	$config->lists();
            cache('DB_CONFIG_DATA',$config);
        }
        config($config); //添加配置
    }

    public function index(){
        if(is_login()){
            $this->redirect('Index/index');
        }else {
            return $this->fetch('login');
        }
    }

    public function login(){
        $verify = input('post.verify');
        $username = input('post.username');
        $password = input('post.password');
        /* 检测验证码 TODO: */
        if (APP_DEBUG==false){
            if(!captcha_check($verify)){
                //验证失败
                $this->error(lang('_VERIFICATION_CODE_INPUT_ERROR_'));
            }
        }

        $User = new UserApiService();
        $uid = $User->login($username, $password);
        if($uid>0){
            $member = new MemberModel();
            if($member->login($uid)){  //登录用户
                //TODO:跳转到登录前页面
                $this->success(lang('_LOGIN_SUCCESS_'), url('Index/index'));
            }else{
                $this->error($member->getError());
            }
        }else{  //登录失败
            switch($uid) {
                case -1: $error = lang('_USERS_DO_NOT_EXIST_OR_ARE_DISABLED_'); break; //系统级别禁用
                case -2: $error = lang('_PASSWORD_ERROR_'); break;
                default: $error = lang('_UNKNOWN_ERROR_'); break; // 0-接口参数错误（调试阶段使用）
            }
            $this->error($error);
        }
    }

    /* 退出登录 */
    public function logout(){
        if(is_login()){
            $member = new MemberModel();
            $member->logout();
            session('[destroy]');
            $this->success(lang('_EXIT_SUCCESS_'), url('Login/index'));
        } else {
            $this->redirect('Login/index');
        }
    }

}