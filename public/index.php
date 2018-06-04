<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]
// 检测PHP环境
if (version_compare(PHP_VERSION, '5.4.0', '<'))
    die('require PHP > 5.4.0 !');

/**
 * 系统调试设置
 * 项目正式部署后请设置为false
 */
define ('APP_DEBUG', true);

// 定义插件目录
define('ONETHINK_ADDON_PATH', __DIR__ . '/addons/');

define ('TP_THEME_PATH', './theme/');


// 定义CMS根目录,可更改此目录
define('CMS_ROOT', __DIR__ . '/../');

// 定义 版本号
define('THINKOS_VERSION', '5.0.170808');

// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');

// 加载框架引导文件
try{
    require __DIR__ . '/../thinkphp/start.php';
}catch (\Exception $exception){
    if($exception->getCode()==0){
        send_http_status(404);
        $string=file_get_contents(__DIR__.'/404/404.html');
        $string=str_replace('$ERROR_MESSAGE',$exception->getMessage(),$string);
        $string=str_replace('HTTP_HOST','http://'.$_SERVER['HTTP_HOST'],$string);
        echo $string;
    }else{
        exception($exception->getMessage(),$exception->getCode());
    }
}
