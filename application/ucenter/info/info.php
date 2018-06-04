<?php
return array(
    //模块名
    'name' => 'ucenter',
    //别名
    'alias' => '用户中心',
    //版本号
    'version' => '2.0.0',
    //是否商业模块,1是，0，否
    'is_com' => 0,
    //是否显示在导航栏内？  1是，0否
    'show_nav' => 1,
    //模块描述
    'summary' => '用户中心模块，系统核心模块',
    //开发者
    'developer' => 'think28',
    //开发者网站
    'website' => 'http://www.tours28.com',
    //前台入口，可用U函数
    'entry' => 'Ucenter/index/index',

   'admin_entry' => 'Backstage/User/index',

    'icon'=>'user',

    'can_uninstall' => 0
);