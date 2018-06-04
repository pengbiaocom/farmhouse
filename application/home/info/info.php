<?php

return array(
    //模块名
    'name' => 'Home',
    //别名
    'alias' => '网站主页',
    //版本号
    'version' => '2.0.0',
    //是否商业模块,1是，0，否
    'is_com' => 0,
    //是否显示在导航栏内？  1是，0否
    'show_nav' => 1,
    //模块描述
    'summary' => '首页模块，主要用于展示网站内容',
    //开发者
    'developer' => 'think28',
    //开发者网站
    'website' => 'http://www.tours28.com',
    //前台入口，可用U函数
    'entry' => 'Home/index/index',

    'admin_entry' => 'Backstage/Home/config',

    'icon'=>'home',

    'can_uninstall' => 1

);