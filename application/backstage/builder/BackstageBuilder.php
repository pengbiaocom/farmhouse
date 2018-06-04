<?php
namespace app\backstage\builder;

use app\common\controller\BaseController;
/**
 * BackstageBuilder：快速建立管理页面。
 *
 * 为什么要继承Backstage？
 * 因为Backstage的初始化函数中读取了顶部导航栏和左侧的菜单，
 * 如果不继承的话，只能复制Backstage中的代码来读取导航栏和左侧的菜单。
 * 这样做会导致一个问题就是当Backstage被官方修改后BackstageBuilder不会同步更新，从而导致错误。
 * 所以综合考虑还是继承比较好。
 *
 * Class BackstageBuilder
 * @package backstage\builder
 */
class BackstageBuilder extends BaseController{

    public function _initialize()
    {
         parent::_initialize();
    }

    public function _initializeView()
    {
        $AdminTemplatePath    = config('admin_template_path');
        $AdminDefaultTemplate = config('admin_default_template');
        $templatePath = "{$AdminTemplatePath}{$AdminDefaultTemplate}";
        $root = think_get_root();
        $viewReplaceStr = [
            '__PUBLIC__' => $root.'/',
            '__STATIC__' =>$root.'/static',
            '__B_IMG__' =>$root.'/static/backstage/images',
            '__B_CSS__' =>$root.'/static/backstage/css',
            '__B_JS__' =>$root.'/static/backstage/js',
            '__TEMP__' => $root.'/template',
            '__ROOT__'=>$root,
            '__ZUI__' => $root . '/static/zui',
            'UPLOAD_URL' =>__URL__.'/'.BACKSTAGE_MODULE,
            'BACKSTAGE_MAIN'=>__URL__.'/'.BACKSTAGE_MODULE,
            '__UPLOAD__' => $root,
            '__MODULE__' => '/'.BACKSTAGE_MODULE,

        ];
        config('template.view_base', "$templatePath/");
        config('view_replace_str', $viewReplaceStr);
    }

    public function display($templateFile='',$charset='',$contentType='',$content='',$prefix='') {
        //获取模版的名称
        $template = '/builder/'.$templateFile;
        //显示页面
        return parent::display($template);
    }

    protected function compileHtmlAttr($attr) {
        $result = [];
        foreach($attr as $key=>$value) {
            $value = htmlspecialchars($value);
            $result[] = "$key=\"$value\"";
        }
        $result = implode(' ', $result);
        return $result;
    }
}