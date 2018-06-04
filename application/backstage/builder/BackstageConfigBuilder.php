<?php
namespace app\backstage\builder;

class BackstageConfigBuilder extends BackstageBuilder
{
    private $_title;
    private $_suggest;
    private $_keyList = [];
    private $_data = [];
    private $_buttonList = [];
    private $_savePostUrl = '';
    private $_group = [];
    private $_callback = null;
    private $_filter = [];

    /**
     * 设置页面标题
     * @param $title
     * @return $this
     */
    public function title($title)
    {
        $this->_title = $title;
        return $this;
    }

    /**
     * suggest  页面标题边上的提示信息
     * @param $suggest
     * @return $this
     */
    public function suggest($suggest)
    {
        $this->_suggest = $suggest;
        return $this;
    }

    public function filter($filter)
    {
        $filter = is_array($filter) ? $filter : explode(',', $filter);
        $this->_filter = $filter;
        return $this;
    }


    public function callback($callback)
    {
        $this->_callback = $callback;
        return $this;
    }

    /**键，一般用于内部调用
     * @param      $name
     * @param      $title
     * @param null $subtitle
     * @param      $type
     * @param null $opt
     * @return $this
     */
    public function key($name, $title, $subtitle = null, $type, $opt = null)
    {
        $key = ['name' => $name, 'title' => $title, 'subtitle' => $subtitle, 'type' => $type, 'opt' => $opt];
        $this->_keyList[] = $key;
        return $this;
    }

    /**隐藏文本
     * @param      $name
     * @param      $title
     * @param null $subtitle
     * @return BackstageConfigBuilder
     */
    public function keyHidden($name, $title, $subtitle = null)
    {
        return $this->key($name, $title, $subtitle, 'hidden');
    }

    /**只读文本
     * @param      $name
     * @param      $title
     * @param null $subtitle
     * @return BackstageConfigBuilder
     */
    public function keyReadOnly($name, $title, $subtitle = null)
    {
        return $this->key($name, $title, $subtitle, 'readonly');
    }

    /**
     * 只读文本域
     * @param $name
     * @param $title
     * @param null $subtitle
     * @return BackstageConfigBuilder
     */
    public function keyAreaReadOnly($name, $title, $subtitle = null)
    {
        return $this->key($name, $title, $subtitle, 'area_readonly');
    }

    /**文本输入框
     * @param      $name
     * @param      $title
     * @param null $subtitle
     * @return BackstageConfigBuilder
     */
    public function keyText($name, $title, $subtitle = null)
    {
        return $this->key($name, $title, $subtitle, 'text');
    }

    /**
     * 密码输入框
     * @param $name
     * @param $title
     * @param null $subtitle
     * @return BackstageConfigBuilder
     */
    public function keyPassword($name,$title,$subtitle=null){
        return $this->key($name,$title,$subtitle,'password');
    }

    /**颜色选择器
     * @param $name
     * @param $title
     * @param null $subtitle
     * @return $this
     */
    public function keyColor($name, $title, $subtitle = null)
    {
        return $this->key($name, $title, $subtitle, 'colorPicker');
    }


    /**
     * label标签
     * @param $name
     * @param $title
     * @param null $subtitle
     * @return BackstageConfigBuilder
     */
    public function keyLabel($name, $title, $subtitle = null)
    {
        return $this->key($name, $title, $subtitle, 'label');
    }

    /**
     * 文本域
     * @param $name
     * @param $title
     * @param null $subtitle
     * @return BackstageConfigBuilder
     */
    public function keyTextArea($name, $title, $subtitle = null)
    {
        return $this->key($name, $title, $subtitle, 'textarea');
    }

    /**
     * 文本框  输入数字
     * @param $name
     * @param $title
     * @param null $subtitle
     * @return BackstageConfigBuilder
     */
    public function keyInteger($name, $title, $subtitle = null)
    {
        return $this->key($name, $title, $subtitle, 'integer');
    }

    /**
     * 显示用户id
     * @param $name
     * @param $title
     * @param null $subtitle
     * @return BackstageConfigBuilder
     */
    public function keyUid($name, $title, $subtitle = null)
    {
        return $this->key($name, $title, $subtitle, 'uid');
    }

    /**
     * 状态下拉框
     * @param string $name
     * @param string $title
     * @param null $subtitle
     * @return BackstageConfigBuilder
     */
    public function keyStatus($name = 'status', $title = '状态', $subtitle = null)
    {
        $map = [-1 => '删除', 0 =>'禁用', 1 => '启用'];
        return $this->keySelect($name, $title, $subtitle, $map);
    }

    /**
     * 下拉框
     * @param $name
     * @param $title
     * @param null $subtitle
     * @param $options
     * @return BackstageConfigBuilder
     */
    public function keySelect($name, $title, $subtitle = null, $options)
    {
        return $this->key($name, $title, $subtitle, 'select', $options);
    }

    /**
     * 单选按钮
     * @param $name
     * @param $title
     * @param null $subtitle
     * @param $options
     * @return BackstageConfigBuilder
     */
    public function keyRadio($name, $title, $subtitle = null, $options)
    {
        return $this->key($name, $title, $subtitle, 'radio', $options);
    }

    /**
     * 复选按钮
     * @param $name
     * @param $title
     * @param null $subtitle
     * @param $options
     * @return BackstageConfigBuilder
     */
    public function keyCheckBox($name, $title, $subtitle = null, $options)
    {
        return $this->key($name, $title, $subtitle, 'checkbox', $options);
    }

    /**
     * 富文本编辑框
     * @param $name
     * @param $title
     * @param null $subtitle
     * @param string $config
     * @param array $style
     * @return $this
     */
    public function keyEditor($name, $title, $subtitle = null, $config = '', $style = ['width' => '600px', 'height' => '400px'])
    {
        $toolbars = "toolbars:[[" . $config . "]]";
        if (empty($config)) {
            $toolbars = "toolbars:[['source','|','bold','italic','underline','fontsize','forecolor','justifyleft','fontfamily','|','map','emotion','insertimage','insertcode']]";
        }
        if ($config == 'all') {
            $toolbars = 'all';
        }
        $key = ['name' => $name, 'title' => $title, 'subtitle' => $subtitle, 'type' => 'editor', 'config' => $toolbars, 'style' => $style];
        $this->_keyList[] = $key;
        return $this;
    }

    /**
     * 日期选择器：支持三种类型
     * @param $name
     * @param $title
     * @param null $subtitle
     * @param string $type 类型：支持（time）（datetime，默认）(date)
     * @return $this
     */
    public function keyTime($name, $title, $subtitle = null, $type = 'datetime')
    {
        return $this->key($name, $title, $subtitle, $type);
    }

    public function keyCreateTime($name = 'create_time', $title = '创建时间', $subtitle = null)
    {
        return $this->keyTime($name, $title, $subtitle);
    }

    public function keyBool($name, $title, $subtitle = null)
    {
        $map = [1 => lang('_YES_'), 0 => lang('_NO_')];
        return $this->keyRadio($name, $title, $subtitle, $map);
    }

    public function keySwitch($name, $title, $subtitle = null)
    {
        $map = [1 => lang('_OPEN_'), 0 => lang('_CLOSE_')];
        return $this->keyRadio($name, $title, $subtitle, $map);
    }

    public function keyUpdateTime($name = 'update_time', $title = '修改时间', $subtitle = null)
    {
        return $this->keyTime($name, $title, $subtitle);
    }

    public function keyTitle($name = 'title', $title = '标题', $subtitle = null)
    {
        return $this->keyText($name, $title, $subtitle);
    }

    public function keyId($name = 'id', $title = '编号', $subtitle = null)
    {
        return $this->keyReadOnly($name, $title, $subtitle);
    }

    public function keyMultiUserGroup($name, $title, $subtitle = null)
    {
        $options = $this->readUserGroups();
        return $this->keyCheckBox($name, $title, $subtitle, $options);
    }

    /**
     * 单文件上传
     * @param $name
     * @param $title
     * @param null $subtitle
     * @return BackstageConfigBuilder
     */
    public function keySingleFile($name, $title, $subtitle = null)
    {
        return $this->key($name, $title, $subtitle, 'singleFile');
    }

    /**
     * 多文件上传
     * @param $name
     * @param $title
     * @param null $subtitle
     * @return BackstageConfigBuilder
     */
    public function keyMultiFile($name, $title, $subtitle = null)
    {
        return $this->key($name, $title, $subtitle, 'multiFile');
    }

    /**
     * 单图片上传
     * @param $name
     * @param $title
     * @param null $subtitle
     * @return BackstageConfigBuilder
     */
    public function keySingleImage($name, $title, $subtitle = null)
    {
        return $this->key($name, $title, $subtitle, 'singleImage');
    }

    /**
     * 多图片上传
     * @param $name
     * @param $title
     * @param null $subtitle
     * @param string $limit
     * @return BackstageConfigBuilder
     */
    public function keyMultiImage($name, $title, $subtitle = null, $limit = '')
    {
        return $this->key($name, $title, $subtitle, 'multiImage', $limit);
    }

    public function keySingleUserGroup($name, $title, $subtitle = null)
    {
        $options = $this->readUserGroups();
        return $this->keySelect($name, $title, $subtitle, $options);
    }

    /** 添加城市选择（需安装城市联动插件）
     * @param array $name
     * @param $title
     * @param $subtitle
     * @return BackstageConfigBuilder
     * @author LaoYang
     */
    public function keyCity($name = array('province', 'city', 'district'), $title, $subtitle)
    {
        //修正在编辑信息时无法正常显示已经保存的地区信息
        return $this->key($name, $title, $subtitle, 'city');
    }


    /**
     * 增加数据时通过列表页选择相应的关联数据ID  -_-。sorry！表述不清楚..
     * @param  unknown $name 字段名
     * @param  unknown $title 标题
     * @param  string $subtitle 副标题（说明）
     * @param  unknown $url 选择数据的列表页地址，U方法地址'index/index'
     * @return $this
     */
    public function keyDataSelect($name, $title, $subtitle = null, $url)
    {
        $urls = url($url, ['inputid' => $name]);
        return $this->key($name, $title, $subtitle, 'dataselect', $urls);
    }

    public function button($title, $attr =[])
    {
        $this->_buttonList[] = ['title' => $title, 'attr' => $attr];
        return $this;
    }

    public function buttonSubmit($url = '', $title = '确定')
    {
        if ($url == '') {
            $url = url(Request()->action(),$_GET);
        }
        $this->savePostUrl($url);

        $attr = [];
        $attr['class'] = "layui-btn ajax-post"; //ajax-post
        $attr['id'] = 'submit';
        $attr['type'] = 'submit';
        $attr['target-form'] = 'form-horizontal';
        return $this->button($title, $attr);
    }

    public function buttonBack($title = '返回')
    {
        $attr = [];
        $attr['onclick'] = 'javascript:history.back(-1);return false;';
        $attr['class'] = 'layui-btn layui-btn-primary';
        return $this->button($title, $attr);
    }

    public function buttonLink($title = '按钮', $attr)
    {
        $attr['onclick'] = 'javascript:location.href=\'' . $attr['href'] . '\';return false;';
        return $this->button($title, $attr);
    }

    public function data($list)
    {
        $this->_data = $list;
        return $this;
    }

    public function savePostUrl($url)
    {
        if ($url) {
            $this->_savePostUrl = $url;
        }
    }

    public function show()
    {

        //将数据融入到key中
        foreach ($this->_keyList as &$e) {
            if ($e['type'] == 'multiInput') {
                $e['name'] = explode('|', $e['name']);
            }

            //修正在编辑信息时无法正常显示已经保存的地区信息/***修改的代码****/
            if (is_array($e['name'])) {
                $i = 0;
                $n = count($e['name']);
                while ($n > 0) {
                    $e['value'][$i] = $this->_data[$e['name'][$i]];
                    $i++;
                    $n--;
                }
            } else {
                $e['value'] = $this->_data[$e['name']];
            }
        }

        //编译按钮的html属性
        foreach ($this->_buttonList as &$button) {
            $button['attr'] = $this->compileHtmlAttr($button['attr']);
        }

        //显示页面
        $this->assign('group', $this->_group);
        $this->assign('title', $this->_title);
        $this->assign('meta_title', $this->_title);
        $this->assign('suggest', $this->_suggest);
        $this->assign('keyList', $this->_keyList);
        $this->assign('buttonList', $this->_buttonList);
        $this->assign('savePostUrl', $this->_savePostUrl);

        return $this->fetch(parent::display('admin_config'));
    }

    /**
     * keyChosen  多选菜单
     * @param $name
     * @param $title
     * @param null $subtitle
     * @param $options
     * @return $this
     */
    public function keyChosen($name, $title, $subtitle = null, $options)
    {
        // 解析option数组
        if (key($options) === 0) {
            if (!is_array(reset($options))) {
                foreach ($options as $key => &$val) {
                    $val = [$val, $val];
                }
                unset($key, $val);
            }
        } else {
            foreach ($options as $key => &$val) {
                foreach ($val as $k => &$v) {
                    if (!is_array($v)) {
                        $v = [$v, $v];
                    }
                }
                unset($k, $v);
            }
            unset($key, $val);
        }
        return $this->key($name, $title, $subtitle, 'chosen', $options);
    }


    /**
     * keyMultiInput  输入组组件
     * @param $name
     * @param $title
     * @param $subtitle
     * @param $config
     * @param null $style
     * @return $this
     */
    public function keyMultiInput($name, $title, $subtitle, $config, $style = null)
    {
        empty($style) && $style = 'width:400px;';
        $key = ['name' => $name, 'title' => $title, 'subtitle' => $subtitle, 'type' => 'multiInput', 'config' => $config, 'style' => $style];
        $this->_keyList[] = $key;
        return $this;
    }

    /**插入配置分组
     * @param       $name 组名
     * @param array $list 组内字段列表
     * @return $this
     */
    public function group($name, $list = [])
    {
        !is_array($list) && $list = explode(',', $list);
        $this->_group[$name] = $list;
        return $this;
    }

    public function groups($list = [])
    {
        foreach ($list as $key => $v) {
            $this->_group[$key] = is_array($v) ? $v : explode(',', $v);
        }
        return $this;
    }


    /**自动处理配置存储事件，配置项必须全大写
     */
    public function handleConfig()
    {
        if (Request()->isPost()) {
            $data = $this->request->param();
            $success = false;
            $configModel = model('Config');

            foreach ($data as $k => $v) {
                if (in_array($k, $this->_filter)) {
                    $success = 1;
                    continue;
                }
                $config['name'] = '_' . strtoupper(Request()->controller()) . '_' . strtoupper($k);
                $config['type'] = 0;
                $config['title'] = '';
                $config['group'] = 0;
                $config['extra'] = '';
                $config['remark'] = '';
                $config['create_time'] = time();
                $config['update_time'] = time();
                $config['status'] = 1;
                $config['value'] = is_array($v) ? implode(',', $v) : htmlspecialchars_decode($v);
                $config['sort'] = 0;

                if($configModel->where(['name' => ['like', $config['name']]])->count()>0){
                    if ($configModel->allowField(true)->isUpdate(true)->save($config,['name' => ['like', $config['name']]])) {
                        $success = 1;
                    }
                }else{
                    if ($configModel->allowField(true)->save($config)) {
                        $success = 1;
                    }
                }
                $tag = 'conf_' . strtoupper(request()->controller()) . '_' . strtoupper($k);
                cache($tag, null);
            }

            if ($success) {
                if ($this->_callback) {
                    $str = $this->_callback;
                    controller(Request()->controller())->$str($this->request->param());
                }
                header('Content-type: application/json');
                exit(json_encode(['msg' => lang('_SUCCESS_CONF_SAVE_') . lang('_PERIOD_'), 'code' => 1, 'url' => url(Request()->controller()."/".Request()->action())]));
            } else {
                header('Content-type: application/json');
                exit(json_encode(['msg' => lang('_FAIL_CONF_SAVE_') . lang('_PERIOD_'), 'code' => 0, 'url' => url(Request()->controller()."/".Request()->action())]));
            }


        } else {
            $configs = model('Config')->where(['name' => ['like', '_' . strtoupper(Request()->controller()) . '_' . '%']])->limit(999)->select();
            $data = [];
            foreach ($configs as $k => $v) {
                $key = str_replace('_' . strtoupper(Request()->controller()) . '_', '', strtoupper($v['name']));
                if(is_string($v['value']))  $v['value'] = htmlspecialchars_decode($v['value']);
                $data[$key] = $v['value'];
            }
            return $data;
        }
    }

    private function readUserGroups()
    {
        $list = model('AuthGroup')->where(['status' => 1])->order('id asc')->select();
        $result = [];
        foreach ($list as $group) {
            $result[$group['id']] = $group['title'];
        }
        return $result;
    }

    /**
     * parseNestableArray  解析看板数组
     * @param $data
     * @param array $item
     * @param array $default
     * @return array
     */
    public function parseNestableArray($data, $item = [], $default = [])
    {
        if (empty($data)) {
            $head = reset($default);
            if (!array_key_exists("items", $head)) {
                $temp = [];
                foreach ($default as $k => $v) {
                    $temp[] = ['data-id' => $k, 'title' => $k, 'items' => $v];
                }
                $default = $temp;
            }
            $result = $default;
        } else {
            $data = json_decode($data, true);
            $item_d = getSubByKey($item, 'data-id');
            $all = [];
            foreach ($data as $key => $v) {
                $data_id = getSubByKey($v['items'], 'data-id');
                $data_d[$key] = $v;
                unset($data_d[$key]['items']);
                $data_d[$key]['items'] = $data_id ? $data_id : [];
                $all = array_merge($all, $data_id);
            }
            unset($v);
            foreach ($item_d as $val) {
                if (!in_array($val, $all)) {
                    $data_d[0]['items'][] = $val;
                }
            }
            unset($val);
            foreach ($all as $v) {
                if (!in_array($v, $item_d)) {
                    foreach ($data_d as $key => $val) {
                        $key_search = array_search($v, $val['items']);
                        if (!is_bool($key_search)) {
                            unset($data_d[$key]['items'][$key_search]);
                        }
                    }
                    unset($val);
                }
            }
            unset($v);
            $item_t = [];
            foreach ($item as $val) {
                $item_t[$val['data-id']] = $val['title'];
            }
            unset($v);
            foreach ($data_d as &$v) {
                foreach ($v['items'] as &$val) {
                    $t = $val;
                    $val = array();
                    $val['data-id'] = $t;
                    $val['title'] = $item_t[$t];
                }
                unset($val);
            }
            unset($v);

            $result = $data_d;
        }
        return $result;

    }

    public function setDefault($data, $key, $value)
    {
        $data[$key] = $data[$key] != null ? $data[$key] : $value;
        return $data;
    }

    public function keyDefault($key, $value)
    {
        $data = $this->_data;
        $data[$key] = $data[$key] !== null ? $data[$key] : $value;
        $this->_data = $data;
        return $this;
    }

    /**
     * groupLocalComment
     * @param $group_name    组名
     * @param $mod    mod名。path的第二个参数。
     * @return $this
     */
    public function groupLocalComment($group_name, $mod)
    {
        $mod = strtoupper($mod);
        $this->keyDefault($mod . '_LOCAL_COMMENT_CAN_GUEST', 1);
        $this->keyDefault($mod . '_LOCAL_COMMENT_ORDER', 0);
        $this->keyDefault($mod . '_LOCAL_COMMENT_COUNT', 10);
        $this->keyRadio($mod . '_LOCAL_COMMENT_CAN_GUEST', lang('_COMMENTS_ALLOW_VISITOR_IF_'), lang('_ALLOW_DEFAULT_'), [0 => lang('_DISALLOW_'), 1 => lang('_ALLOW_')])
            ->keyRadio($mod . '_LOCAL_COMMENT_ORDER', lang('_COMMENTS_SORT_'), lang('_DESC_DEFAULT_'), [0 => lang('_DESC_'), 1 => lang('_ASC_')])
            ->keyText($mod . '_LOCAL_COMMENT_COUNT', lang('_COMMENTS_PAGE_DISPLAY_COUNT_'), lang('_COMMENTS_PAGE_DISPLAY_COUNT_DESC'));
        $this->group($group_name, $mod . '_LOCAL_COMMENT_CAN_GUEST,' . $mod . '_LOCAL_COMMENT_ORDER,' . $mod . '_LOCAL_COMMENT_COUNT');
        return $this;
    }


    public function keyUserDefined($name, $title, $subtitle, $display = '', $param = '')
    {
        $this->assign('param', $param);
        $this->assign('name', $name);
        $html = $this->fetch($display);

        $key = ['name' => $name, 'title' => $title, 'subtitle' => $subtitle, 'type' => 'userDefined', 'definedHtml' => $html];
        $this->_keyList[] = $key;
        return $this;
    }

    public function addCustomJs($script)
    {
        $this->assign('myJs', $script);
    }


    public function keyAutoComplete($name, $title, $subtitle = null, $options=[]){
        return $this->key($name, $title, $subtitle, 'autoComplete', $options);
    }

}