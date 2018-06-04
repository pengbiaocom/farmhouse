<?php
namespace app\common\controller;

use think\Hook;

class HomeBaseController extends BaseController{

    protected $config = [];

    public function _initialize()
    {
        parent::_initialize();
        /*读取站点配置*/
        $config = controller("common/ConfigApi")->lists();
        config($config); //添加配置

        Hook::listen('action_begin', $this->config);

        //导入公共模块语言包
        import_lang("common");

        if (!config('WEB_SITE_CLOSE')) {
            $this->error(lang('_ERROR_WEBSITE_CLOSED_'));
        }
    }


    public function _initializeView()
    {

        $TemplatePath    = config('template_path');
        $DefaultTemplate = config('default_template');
        $templatePath = "{$TemplatePath}{$DefaultTemplate}";

        $viewReplaceStr = [
            '__ROOT__'     => __ROOT__,
            '__TMPL__'     => __ROOT__."/{$templatePath}",
            '__STATIC__'   => __ROOT__."/static",
            '__ZUI__' => __ROOT__ . '/static/zui',
            '__WEB_ROOT__' => __ROOT__
        ];

        config('template.view_base', "$templatePath/");
        config('view_replace_str', $viewReplaceStr);

    }

    /**
     * Ajax方式返回数据到客户端
     * @access protected
     * @param mixed $data 要返回的数据
     * @param String $type AJAX返回数据格式
     * @return void
     */
    protected function ajaxReturn($data, $type = '')
    {
        if ($data['info'] && cookie('score_tip') !== null) {
            $data['info'] .= cookie('score_tip');
            cookie('score_tip', null);
        }
        if (empty($type)) $type = config('DEFAULT_AJAX_RETURN');
        switch (strtoupper($type)) {
            case 'JSON' :
                // 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                exit(json_encode($data));
            case 'XML'  :
                // 返回xml格式数据
                header('Content-Type:text/xml; charset=utf-8');
                exit(xml_encode($data));
            case 'JSONP':
                // 返回JSON数据格式到客户端 包含状态信息
                header('Content-Type:application/json; charset=utf-8');
                $handler = isset($_GET[config('VAR_JSONP_HANDLER')]) ? $_GET[config('VAR_JSONP_HANDLER')] : config('DEFAULT_JSONP_HANDLER');
                exit($handler . '(' . json_encode($data) . ');');
            case 'EVAL' :
                // 返回可执行的js脚本
                header('Content-Type:text/html; charset=utf-8');
                exit($data);
            default     :
                // 用于扩展其他返回格式数据
                Hook::listen('ajax_return', $data);
        }
    }

}