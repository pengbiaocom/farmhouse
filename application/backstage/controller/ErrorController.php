<?php
namespace app\backstage\controller;

class ErrorController extends  BackstageController{

    public function _empty($name,$args=[]){
        require_once(APP_PATH . Request()->controller() . '/' . 'controller' . '/' .Request()->controller(). 'Controller.php');
        $controller = controller( 'backstage/'.Request()->controller());
        $action=Request()->action();
        try{
            $method =   new \ReflectionMethod($controller, $name);
            // URL参数绑定检测
            if($method->getNumberOfParameters()>0){
                switch($_SERVER['REQUEST_METHOD']) {
                    case 'POST':
                        $vars    =  array_merge($_GET,$_POST);
                        break;
                    case 'PUT':
                        parse_str(file_get_contents('php://input'), $vars);
                        break;
                    default:
                        $vars  =  $_GET;
                }
                $params =  $method->getParameters();

                $paramsBindType     =   config('url_param_type');
                foreach ($params as $param){
                    $name = $param->getName();
                    if( 1 == $paramsBindType && !empty($vars) ){
                        $args[] =   array_shift($vars);
                    }elseif( 0 == $paramsBindType && isset($vars[$name])){
                        $args[] =   $vars[$name];
                    }elseif($param->isDefaultValueAvailable()){
                        $args[] =   $param->getDefaultValue();
                    }else{
                        exception(lang('_PARAM_ERROR_').':'.$name);
                    }
                }
               return $method->invokeArgs($controller,$args);
            }else{
               return $method->invoke($controller);
            }
        }catch (\ReflectionException $e){
            $this->error(lang('_ERROR_404_'));
        }
    }
}