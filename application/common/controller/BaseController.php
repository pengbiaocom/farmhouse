<?php
namespace app\common\controller;

use think\Controller;
use think\Hook;
use think\Request;
use think\View;
use think\Config;

class BaseController extends Controller
{

    public $_seo = [];

    public function setTitle($title)
    {
        $this->_seo['title'] = $title;
        $this->assign('seo', $this->_seo);
    }

    public function setKeywords($keywords)
    {
        $this->_seo['keywords'] = $keywords;
        $this->assign('seo', $this->_seo);
    }

    public function setDescription($description)
    {
        $this->_seo['description'] = $description;
        $this->assign('seo', $this->_seo);
    }

    /**
     * 构造函数
     * @param Request $request Request对象
     * @access public
     */
    public function __construct(Request $request = null)
    {

        if (!cms_is_installed() && $request->module() != 'install') {
            header('Location: ' . think_get_root() . '/index.php?s=install');
            exit;
        }

        if (is_null($request)) {
            $request = Request::instance();
        }

        $this->request = $request;

        $this->_initializeView();
        $this->view = View::instance(Config::get('template'), Config::get('view_replace_str'));


        // 控制器初始化
        $this->_initialize();

        // 前置操作方法
        if ($this->beforeActionList) {
            foreach ($this->beforeActionList as $method => $options) {
                is_numeric($method) ?
                    $this->beforeAction($options) :
                    $this->beforeAction($method, $options);
            }
        }
    }


    // 初始化视图配置
    protected function _initializeView()
    {
    }

    /**
     *  排序 排序字段为list_orders数组 POST 排序字段为：list_order
     */
    protected function listOrders($model)
    {
        if (!is_object($model)) {
            return false;
        }

        $pk  = $model->getPk(); //获取主键名称
        $ids = $this->request->post("list_orders/a");

        if (!empty($ids)) {
            foreach ($ids as $key => $r) {
                $data['list_order'] = $r;
                $model->where([$pk => $key])->update($data);
            }

        }

        return true;
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