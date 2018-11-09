<?php
namespace app\home\controller;

use app\common\controller\HomeBaseController;

class IndexController extends HomeBaseController{
    public function index(){
        if(is_mobile()) return $this->fetch('wap');
        
        return $this->fetch('index');
    }
    
    public function role(){
        return $this->fetch();
    }
}