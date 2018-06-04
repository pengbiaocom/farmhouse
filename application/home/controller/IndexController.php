<?php
namespace app\home\controller;

use app\common\controller\HomeBaseController;

class IndexController extends HomeBaseController{



    public function index(){
        $indexType=modC('HOME_INDEX_TYPE','static_home','Home');
        if(is_mobile()) {
            redirect(url('wap/index/index'));
        }
        if($indexType=='static_home'){
            return $this->fetch('static_home');
            exit();
        }

        if($indexType=='login'){
            if(!is_login()){
                redirect(url('ucenter/member/login'));
                exit();
            }
        }

        hook('homeIndex');
        $default_url = config('DEFUALT_HOME_URL');//获得配置，如果为空则显示聚合，否则跳转
        if ($default_url != ''&&strtolower($default_url)!='home/index/index') {
            redirect(get_nav_url($default_url));
            exit();
        }

        $show_blocks = get_kanban_config('BLOCK', 'enable', [], 'Home');

        $this->assign('showBlocks', $show_blocks);


        $enter = modC('ENTER_URL', '', 'Home');
        $this->assign('enter', get_nav_url($enter));

        return $this->fetch('index');

    }
}