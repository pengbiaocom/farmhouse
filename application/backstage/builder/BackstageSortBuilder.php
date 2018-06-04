<?php
namespace app\backstage\builder;

class BackstageSortBuilder extends BackstageBuilder {

    private $_title;
    private $_list;
    private $_buttonList;
    private $_savePostUrl;


    public function title($title) {
        $this->_title = $title;
        return $this;
    }

    public function data($list) {
        $this->_list = $list;
        return $this;
    }

    public function button($title, $attr=[]) {
        $this->_buttonList[] = ['title'=>$title, 'attr'=>$attr];
        return $this;
    }

    public function buttonSubmit($url, $title='确定') {
        $this->savePostUrl($url);

        $attr = [];
        $attr['class'] = "sort_confirm layui-btn submit-btn";
        $attr['type'] = 'button';
        $attr['target-form'] = 'form-horizontal';
        return $this->button($title, $attr);
    }

    public function buttonBack($url=null, $title='返回') {
        //默认返回当前页面
        if(!$url) {
            $url = $_SERVER['HTTP_REFERER'];
        }

        //添加按钮
        $attr = [];
        $attr['href'] = $url;
        $attr['onclick'] = 'javascript: location.href=$(this).attr("href");';
        $attr['class'] = 'sort_cancel layui-btn layui-btn-primary';
        return $this->button($title, $attr);
    }

    public function savePostUrl($url) {
        $this->_savePostUrl = $url;
    }

    public function show() {
        //编译按钮的属性
        foreach($this->_buttonList as &$e) {
            $e['attr'] = $this->compileHtmlAttr($e['attr']);
        }
        unset($e);

        //显示页面
        $this->assign('title', $this->_title);
        $this->assign('meta_title', $this->_title);
        $this->assign('list', $this->_list);
        $this->assign('buttonList', $this->_buttonList);
        $this->assign('savePostUrl', $this->_savePostUrl);
        return $this->fetch(parent::display('admin_sort'));
    }

    public function doSort($table, $ids) {
        $ids = explode(',', $ids);
        $res = 0;
        foreach ($ids as $key=>$value){
            if(db($table)->where(['id'=>$value])->update(['sort'=>$key+1])){
                $res++;
            }
        }
        if(!$res) {
            $this->error(lang('_ERROR_SORT_').lang('_PERIOD_'));
        } else {
            $this->success(lang('_SUCCESS_SORT_').lang('_PERIOD_'), cookie('__SELF__'));
        }
    }
}