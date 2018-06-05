<?php

// 应用公共文件
const ONETHINK_VERSION = '1.0.131218';
use app\common\model\UserModel;
use app\common\model\UcenterMemberModel;
use app\common\model\ConfigModel;
use app\common\model\AuthRuleModel;
use think\Request;

// 异常错误报错级别,
error_reporting(E_ERROR | E_PARSE );


/**
 * 判断 cms 核心是否安装
 * @return bool
 */
function cms_is_installed()
{
    static $cmsIsInstalled;
    if (empty($cmsIsInstalled)) {
        $cmsIsInstalled = file_exists(CMS_ROOT . 'data/install.lock');
    }
    return $cmsIsInstalled;
}

/**
 * 获取网站根目录
 * @return string 网站根目录
 */
function think_get_root()
{
    $request = Request::instance();
    $root    = $request->root();
    $root    = str_replace('/index.php', '', $root);
    return $root;
}


/**
 * 切分SQL文件成多个可以单独执行的sql语句
 * @param $file sql文件路径
 * @param $tablePre 表前缀
 * @param string $charset 字符集
 * @param string $defaultTablePre 默认表前缀
 * @param string $defaultCharset 默认字符集
 * @return array
 */
function think_split_sql($file, $tablePre, $charset = 'utf8', $defaultTablePre = 'cms_', $defaultCharset = 'utf8')
{
    if (file_exists($file)) {
        //读取SQL文件
        $sql = file_get_contents($file);
        $sql = str_replace("\r", "\n", $sql);
        $sql = str_replace("BEGIN;\n", '', $sql);//兼容 navicat 导出的 insert 语句
        $sql = str_replace("COMMIT;\n", '', $sql);//兼容 navicat 导出的 insert 语句
        $sql = str_replace($defaultCharset, $charset, $sql);
        $sql = trim($sql);
        //替换表前缀
        $sql  = str_replace(" `{$defaultTablePre}", " `{$tablePre}", $sql);
        $sqls = explode(";\n", $sql);
        return $sqls;
    }

    return [];
}


function think_ucenter_md5($str, $key = 'ThinkUCenter')
{
    return '' === $str ? '' : md5(sha1($str) . $key);
}

/**
 * 系统加密方法
 * @param string $data 要加密的字符串
 * @param string $key 加密密钥
 * @param int $expire 过期时间 (单位:秒)
 * @return string
 */
function think_ucenter_encrypt($data, $key, $expire = 0)
{
    $key = md5($key);
    $data = base64_encode($data);
    $x = 0;
    $len = strlen($data);
    $l = strlen($key);
    $char = '';
    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) $x = 0;
        $char .= substr($key, $x, 1);
        $x++;
    }
    $str = sprintf('%010d', $expire ? $expire + time() : 0);
    for ($i = 0; $i < $len; $i++) {
        $str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1))) % 256);
    }
    return str_replace('=', '', base64_encode($str));
}

/**
 * 系统解密方法
 * @param string $data 要解密的字符串 （必须是think_encrypt方法加密的字符串）
 * @param string $key 加密密钥
 * @return string
 */
function think_ucenter_decrypt($data, $key)
{
    $key = md5($key);
    $x = 0;
    $data = base64_decode($data);
    $expire = substr($data, 0, 10);
    $data = substr($data, 10);
    if ($expire > 0 && $expire < time()) {
        return '';
    }
    $len = strlen($data);
    $l = strlen($key);
    $char = $str = '';
    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) $x = 0;
        $char .= substr($key, $x, 1);
        $x++;
    }
    for ($i = 0; $i < $len; $i++) {
        if (ord(substr($data, $i, 1)) < ord(substr($char, $i, 1))) {
            $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
        } else {
            $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
        }
    }
    return base64_decode($str);
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
 * @return string
 */
function get_client_ip($type = 0, $adv = false)
{
    return request()->ip($type, $adv);
}


/**获取模块的后台设置
 * @param        $key 获取模块的配置
 * @param string $default 默认值
 * @param string $module 模块名，不设置用当前模块名
 * @return string
 */
function modC($key, $default = '', $module = '')
{
    $mod = $module ? $module : Request()->module();
    if (Request()->module() == "Install" && $key == "NOW_THEME") {
        return $default;
    }
    $tag = 'conf_' . strtoupper($mod) . '_' . strtoupper($key);
    $result = cache($tag);
    $configModel = new ConfigModel();
    if ($result === false) {
        $config = $configModel->field('value')->where(['name' => '_' . strtoupper($mod) . '_' . strtoupper($key)])->find();
        if (!$config) {
            $result = $default;
        } else {
            $result = $config['value'];
        }
        cache($tag, $result);
    }
    return $result;
}

/**
 * 取一个二维数组中的每个数组的固定的键知道的值来形成一个新的一维数组
 * @param $pArray 一个二维数组
 * @param $pKey 数组的键的名称
 * @return 返回新的一维数组
 */
function getSubByKey($pArray, $pKey = "", $pCondition = "")
{
    $result = [];
    if (is_object($pArray)) {
        foreach ($pArray as $temp_array) {
            if (is_object($temp_array)) {
                $temp_array = (array)$temp_array;
            }
            if (("" != $pCondition && $temp_array[$pCondition[0]] == $pCondition[1]) || "" == $pCondition) {
                $result[] = ("" == $pKey) ? $temp_array : isset($temp_array[$pKey]) ? $temp_array[$pKey] : "";
            }
        }
        return $result;
    } else {
        return false;
    }
}

/**
 * 检测用户是否登录
 * @return integer 0-未登录，大于0-当前登录用户ID
 */
function is_login()
{

    $user = session('user_auth');
    if (empty($user)) {
        return 0;
    } else {
        return session('user_auth_sign') == data_auth_sign($user) ? $user['uid'] : 0;
    }
}
/**
 * 数据签名认证
 * @param  array $data 被认证的数据
 * @return string       签名
 */
function data_auth_sign($data)
{
    //数据类型检测
    if (!is_array($data)) {
        $data = (array)$data;
    }
    ksort($data); //排序
    $code = http_build_query($data); //url编码并生成query字符串
    $sign = sha1($code); //生成签名
    return $sign;
}

/**
 * 根据用户ID获取用户昵称
 * @param  integer $uid 用户ID
 * @return string       用户昵称
 */
function get_nickname($uid = null)
{
    $user = query_user('nickname', $uid);
    return $user['nickname'];
}

/**
 * 记录行为日志，并执行该行为的规则
 * @param string $action 行为标识
 * @param string $model 触发行为的模型名
 * @param int $record_id 触发行为的记录id
 * @param int $user_id 执行行为的用户id
 * @return boolean
 */
function action_log($action = null, $model = null, $record_id = null, $user_id = null)
{
    //参数检查
    if (empty($action) || empty($model) || empty($record_id)) {
        return lang('_PARAMETERS_CANT_BE_EMPTY_');
    }
    if (empty($user_id)) {
        $user_id = is_login();
    }

    //查询行为,判断是否执行
    $action_info = db('Action')->where("name='".$action."'")->find();

    if ($action_info['status'] != 1) {
        return lang('_THE_ACT_IS_DISABLED_OR_DELETED_');
    }

    //插入行为日志
    $data['action_id'] = $action_info['id'];
    $data['user_id'] = $user_id;
    $data['action_ip'] = ip2long(get_client_ip());
    $data['model'] = $model;
    $data['record_id'] = $record_id;
    $data['create_time'] = time();

    //解析日志规则,生成日志备注
    if (!empty($action_info['log'])) {
        if (preg_match_all('/\[(\S+?)\]/', $action_info['log'], $match)) {
            $log['user'] = $user_id;
            $log['record'] = $record_id;
            $log['model'] = $model;
            $log['time'] = time();
            $log['data'] = ['user' => $user_id, 'model' => $model, 'record' => $record_id, 'time' => time()];
            foreach ($match[1] as $value) {
                $param = explode('|', $value);
                if (isset($param[1])) {
                    $replace[] = call_user_func($param[1], $log[$param[0]]);
                } else {
                    $replace[] = $log[$param[0]];
                }
            }
            $data['remark'] = str_replace($match[0], $replace, $action_info['log']);
        } else {
            $data['remark'] = $action_info['log'];
        }
    } else {
        //未定义日志规则，记录操作url
        $data['remark'] = '操作url：' . $_SERVER['REQUEST_URI'];
    }


    $log_id = db('ActionLog')->insert($data);
    $log_score = '';
    hook('handleAction', ['action_id' => $action_info['id'], 'user_id' => $user_id, 'log_id' => $log_id, 'log_score' => &$log_score]);
    if (!empty($action_info['rule'])) {
        //解析行为
        $rules = parse_action($action, $user_id);
        //执行行为
        $res = execute_action($rules, $action_info['id'], $user_id, $log_id, $log_score);
    } else {
        if ($log_score) {
            cookie('score_tip', $log_score, 30);
            db('ActionLog')->where(['id' => $log_id])->setField('remark', ['exp', "CONCAT(remark,'" . $log_score . "')"]);
        }
    }
}

/**
 * 解析行为规则
 * 规则定义  table:$table|field:$field|condition:$condition|rule:$rule[|cycle:$cycle|max:$max][;......]
 * 规则字段解释：table->要操作的数据表，不需要加表前缀；
 *              field->要操作的字段；
 *              condition->操作的条件，目前支持字符串，默认变量{$self}为执行行为的用户
 *              rule->对字段进行的具体操作，目前支持四则混合运算，如：1+score*2/2-3
 *              cycle->执行周期，单位（小时），表示$cycle小时内最多执行$max次
 *              max->单个周期内的最大执行次数（$cycle和$max必须同时定义，否则无效）
 * 单个行为后可加 ； 连接其他规则
 * @param string $action 行为id或者name
 * @param int $self 替换规则里的变量为执行用户的id
 * @return boolean|array: false解析出错 ， 成功返回规则数组
 */
function parse_action($action = null, $self)
{
    if (empty($action)) {
        return false;
    }

    //参数支持id或者name
    if (is_numeric($action)) {
        $map = ['id' => $action];
    } else {
        $map = ['name' => $action];
    }

    //查询行为信息
    $info = db('Action')->where($map)->find();

    if (!$info || $info['status'] != 1) {
        return false;
    }

    $rules = unserialize($info['rule']);
    foreach ($rules as $key => &$rule) {
        foreach ($rule as $k => &$v) {
            if (empty($v)) {
                unset($rule[$k]);
            }
        }
        unset($k, $v);
    }
    unset($key, $rule);

    return $rules;
}

/**
 * 执行行为
 * @param bool $rules 解析后的规则数组
 * @param null $action_id (int)行为id
 * @param null $user_id (array)执行的用户id
 * @param null $log_id
 * @param $log_score
 * @return bool false 失败 ， true 成功
 */
function execute_action($rules = false, $action_id = null, $user_id = null, $log_id = null, $log_score = '')
{
    if (!$rules || empty($action_id) || empty($user_id)) {
        return false;
    }
    $return = true;

    foreach ($rules as $rule) {
        //检查执行周期
        $map = ['action_id' => $action_id, 'user_id' => $user_id];
        $map['create_time'] = ['gt', time() - intval($rule['cycle']) * 3600];
        $exec_count = db('ActionLog')->where($map)->count();
        if ($exec_count > $rule['max']) {
            continue;
        }
        //执行数据库操作
        $Model = model(ucfirst($rule['table']));
        $field = 'score' . $rule['field'];


        $rule['rule'] = (is_bool(strpos($rule['rule'], '+')) ? '+' : '') . $rule['rule'];
        $rule['rule'] = is_bool(strpos($rule['rule'], '-')) ? $rule['rule'] : substr($rule['rule'], 1);
        $res = $Model->where(['uid' => is_login(), 'status' => 1])->setField($field, ['exp', $field . $rule['rule']]);

        if (!$res) {
            $return = false;
        }
    }

    return $return;
}

/**
 * 处理插件钩子
 * @param string $hook 钩子名称
 * @param mixed $params 传入参数
 * @return void
 */
function hook($hook, $params = [])
{
    \Think\Hook::listen($hook, $params);
}

/**
 * 获取插件类的类名
 * @param strng $name 插件名
 */
function get_addon_class($name)
{
    $class = "addons\\{$name}\\{$name}Addon";
    return $class;
}

/**
 * 根据用户ID获取用户名
 * @param  integer $uid 用户ID
 * @return string       用户名
 */
function get_username($uid = 0)
{
    static $list;
    if (!($uid && is_numeric($uid))) { //获取当前登录用户名
        return $_SESSION['ocenter']['user_auth']['username'];
    }

    /* 获取缓存数据 */
    if (empty($list)) {
        $list = cache('sys_active_user_list');
    }

    /* 查找用户信息 */
    $key = "u{$uid}";
    if (isset($list[$key])) { //已缓存，直接使用
        $name = $list[$key];
    } else { //调用接口获取用户信息
        $User = new app\common\service\UserApiService();
        $info = $User->info($uid);
        if ($info && isset($info[1])) {
            $name = $list[$key] = $info[1];
            /* 缓存用户 */
            $count = count($list);
            $max = config('USER_MAX_CACHE');
            while ($count-- > $max) {
                array_shift($list);
            }
            cache('sys_active_user_list', $list);
        } else {
            $name = '';
        }
    }
    return $name;
}

/**
 * 生成系统AUTH_KEY
 */
function build_auth_key()
{
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $chars = str_shuffle($chars);
    return substr($chars, 0, 40);
}
/**
 * 对查询结果集进行排序
 * @access public
 * @param array $list 查询结果
 * @param string $field 排序的字段名
 * @param array $sortby 排序类型
 * asc正向排序 desc逆向排序 nat自然排序
 * @return array
 */
function list_sort_by($list, $field, $sortby = 'asc')
{
    if (is_array($list)) {
        $refer = $resultSet = [];
        foreach ($list as $i => $data)
            $refer[$i] = &$data[$field];
        switch ($sortby) {
            case 'asc': // 正向排序
                asort($refer);
                break;
            case 'desc': // 逆向排序
                arsort($refer);
                break;
            case 'nat': // 自然排序
                natcasesort($refer);
                break;
        }
        foreach ($refer as $key => $val)
            $resultSet[] = &$list[$key];
        return $resultSet;
    }
    return false;
}

/**
 * create_rand随机生成一个字符串
 * @param int $length 字符串的长度
 * @param string $type 类型
 * @return string
 */
function create_rand($length = 8, $type = 'all')
{
    $num = '0123456789';
    $letter = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    if ($type == 'num') {
        $chars = $num;
    } elseif ($type == 'letter') {
        $chars = $letter;
    } else {
        $chars = $letter . $num;
    }

    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $str;

}

/**清理用户数据缓存，即时更新query_user返回结果。
 * @param $uid
 * @param $field
 */
function clean_query_user_cache($uid, $field)
{
    $user = new UserModel();
    $user->clean_query_user_cache($uid, $field);
}

/**
 * 支持的字段有
 * member表中的所有字段，ucenter_member表中的所有字段
 * 等级：title
 * 头像：avatar32 avatar64 avatar128 avatar256 avatar512
 * 个人中心地址：space_url
 *
 * @param      $fields array|string 如果是数组，则返回数组。如果不是数组，则返回对应的值
 * @param null $uid
 * @return array|null
 */
function query_user($fields = null, $uid = null)
{
    $uid = $uid == null ? is_login():$uid;
    $user = new UserModel();
    $info = $user->query_user($fields, $uid);
    return $info;
}

/**
 * 时间戳格式化
 * @param null $time
 * @param string $format
 * @return bool|string 完整的时间显示
 */
function time_format($time = NULL, $format = 'Y-m-d H:i:s')
{
    $time = $time === NULL ? time() : intval($time);
    return date($format, $time);
}

function UCenterMember()
{
    $ucenterMember = new UcenterMemberModel();
    return $ucenterMember;
}

function UMember(){
    $memberModel = new \app\common\model\MemberModel();
    return $memberModel;
}



function write_query_user_cache($uid, $field, $value)
{
    $user = new UserModel();
    return $user->write_query_user_cache($uid, $field, $value);
}

/**
 * cut_str  截取字符串
 * @param $search
 * @param $str
 * @param string $place
 * @return mixed
 */
function cut_str($search, $str, $place = '')
{
    switch ($place) {
        case 'l':
            $result = preg_replace('/.*?' . addcslashes(quotemeta($search), '/') . '/', '', $str);
            break;
        case 'r':
            $result = preg_replace('/' . addcslashes(quotemeta($search), '/') . '.*/', '', $str);
            break;
        default:
            $result = preg_replace('/' . addcslashes(quotemeta($search), '/') . '/', '', $str);
    }
    return $result;
}

function real_strip_tags($str, $allowable_tags = "")
{
    return strip_tags($str, $allowable_tags);
}

/**
 * t函数用于过滤标签，输出没有html的干净的文本
 * @param string text 文本内容
 * @return string 处理后内容
 */
function op_t($text, $addslanshes = false)
{
    $text = nl2br($text);
    $text = real_strip_tags($text);
    if ($addslanshes)
        $text = addslashes($text);
    $text = trim($text);
    return $text;
}

/**
 * h函数用于过滤不安全的html标签，输出安全的html
 * @param string $text 待过滤的字符串
 * @param string $type 保留的标签格式
 * @return string 处理后内容
 */
function op_h($text, $type = 'html')
{
    // 无标签格式
    $text_tags = '';
    //只保留链接
    $link_tags = '<a>';
    //只保留图片
    $image_tags = '<img>';
    //只存在字体样式
    $font_tags = '<i><b><u><s><em><strong><font><big><small><sup><sub><bdo><h1><h2><h3><h4><h5><h6>';
    //标题摘要基本格式
    $base_tags = $font_tags . '<p><br><hr><a><img><map><area><pre><code><q><blockquote><acronym><cite><ins><del><center><strike>';
    //兼容Form格式
    $form_tags = $base_tags . '<form><input><textarea><button><select><optgroup><option><label><fieldset><legend>';
    //内容等允许HTML的格式
    $html_tags = $base_tags . '<ul><ol><li><dl><dd><dt><table><caption><td><th><tr><thead><tbody><tfoot><col><colgroup><div><span><object><embed><param>';
    //专题等全HTML格式
    $all_tags = $form_tags . $html_tags . '<!DOCTYPE><meta><html><head><title><body><base><basefont><script><noscript><applet><object><param><style><frame><frameset><noframes><iframe>';
    //过滤标签
    $text = real_strip_tags($text, ${$type . '_tags'});
    // 过滤攻击代码
    if ($type != 'all') {
        // 过滤危险的属性，如：过滤on事件lang js
        while (preg_match('/(<[^><]+)(ondblclick|onclick|onload|onerror|unload|onmouseover|onmouseup|onmouseout|onmousedown|onkeydown|onkeypress|onkeyup|onblur|onchange|onfocus|action|background[^-]|codebase|dynsrc|lowsrc)([^><]*)/i', $text, $mat)) {
            $text = str_ireplace($mat[0], $mat[1] . $mat[3], $text);
        }
        while (preg_match('/(<[^><]+)(window\.|javascript:|js:|about:|file:|document\.|vbs:|cookie)([^><]*)/i', $text, $mat)) {
            $text = str_ireplace($mat[0], $mat[1] . $mat[3], $text);
        }
    }
    return $text;
}

/**过滤函数，别名函数，op_t的别名
 * @param $text
 */
function text($text, $addslanshes = false)
{
    return op_t($text, $addslanshes);
}

/**过滤函数，别名函数，op_h的别名
 * @param $text
 */
function html($text)
{
    return op_h($text);
}

/**
 * 构造用户配置表 D('UserConfig')查询条件
 * @param string $name 表中name字段的值(配置标识)
 * @param string $model 表中model字段的值(模块标识)
 * @param int $uid 用户uid
 * @param int $role_id 登录的角色id
 * @return array 查询条件 $map
 */
function getUserConfigMap($name = '', $model = '', $uid = 0, $role_id = 0)
{
    $uid = $uid ? $uid : is_login();
    $role_id = $role_id ? $role_id : get_role_id($uid);
    $map = [];
    //构造查询条件
    $map['uid'] = $uid;
    $map['name'] = $name;
    if ($role_id != -1) {
        $map['role_id'] = $role_id;
    }
    $map['model'] = $model;
    return $map;
}


/**
 * check_sms_hook_is_exist  判断短信服务插件是否存在，不存在则返回none
 * @param $driver
 * @return string
 */
function check_sms_hook_is_exist($driver)
{
    if ($driver == 'none') {
        return $driver;
    } else {
        $name = get_addon_class($driver);
        if (class_exists($name)) {
            return $driver;
        } else {
            return 'none';
        }
    }
}

/**
 * 加载配置文件 支持格式转换 仅支持一级配置
 * @param string $file 配置文件名
 * @param string $parse 配置解析方法 有些格式需要用户自己解析
 * @return void
 */
function load_config($file, $parse = CONF_PARSE)
{
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    switch ($ext) {
        case 'php':
            return include $file;
        case 'ini':
            return parse_ini_file($file);
        case 'yaml':
            return yaml_parse_file($file);
        case 'xml':
            return (array)simplexml_load_file($file);
        case 'json':
            return json_decode(file_get_contents($file), true);
        default:
            if (function_exists($parse)) {
                return $parse($file);
            } else {
                exception(lang('_NOT_SUPPERT_') . ':' . $ext);
            }
    }
}

/**
 * 解析yaml文件返回一个数组
 * @param string $file 配置文件名
 * @return array
 */
if (!function_exists('yaml_parse_file')) {
    function yaml_parse_file($file)
    {
        vendor('spyc.Spyc');
        return Spyc::YAMLLoad($file);
    }
}

/**
 * 检测当前用户是否为管理员
 * @return boolean true-管理员，false-非管理员
 */
function is_administrator($uid = null)
{
    $uid = is_null($uid) ? is_login() : $uid;
    $admin_uids = explode(',', config('user_administrator'));//调整验证机制，支持多管理员，用,分隔
    return $uid && (in_array(intval($uid), $admin_uids));//调整验证机制，支持多管理员，用,分隔
}

/**
 * 友好的时间显示
 *
 * @param int    $sTime 待显示的时间
 * @param string $type  类型. normal | mohu | full | ymd | other
 * @param string $alt   已失效
 * @return string
 */
function friendlyDate($sTime,$type = 'normal',$alt = 'false') {
    if (!$sTime)
        return '';
    //sTime=源时间，cTime=当前时间，dTime=时间差
    $cTime      =   time();
    $dTime      =   $cTime - $sTime;
    $dDay       =   intval(date("z",$cTime)) - intval(date("z",$sTime));
    $dYear      =   intval(date("Y",$cTime)) - intval(date("Y",$sTime));
    //normal：n秒前，n分钟前，n小时前，日期
    if($type=='normal'){
        if( $dTime < 60 ){
            if($dTime < 10){
                return lang('_JUST_');    //by yangjs
            }else{
                return intval(floor($dTime / 10) * 10).lang('_SECONDS_AGO_');
            }
        }elseif( $dTime < 3600 ){
            return intval($dTime/60).lang('_MINUTES_AGO_');
            //今天的数据.年份相同.日期相同.
        }elseif( $dYear==0 && $dDay == 0  ){
            return lang('_TODAY_').date('H:i',$sTime);
        }elseif($dYear==0){
            return date("m月d日 H:i",$sTime);
        }else{
            return date("Y-m-d H:i",$sTime);
        }
    }elseif($type=='mohu'){
        if( $dTime < 60 ){
            return $dTime.lang('_SECONDS_AGO_');
        }elseif( $dTime < 3600 ){
            return intval($dTime/60).lang('_MINUTES_AGO_');
        }elseif( $dTime >= 3600 && $dDay == 0  ){
            return intval($dTime/3600).lang('_HOURS_AGO_');
        }elseif( $dDay > 0 && $dDay<=7 ){
            return intval($dDay).lang('_DAYS_AGO_');
        }elseif( $dDay > 7 &&  $dDay <= 30 ){
            return intval($dDay/7) . lang('_WEEK_AGO_');
        }elseif( $dDay > 30 ){
            return intval($dDay/30) . lang('_A_MONTH_AGO_');
        }
        //full: Y-m-d , H:i:s
    }elseif($type=='full'){
        return date("Y-m-d , H:i:s",$sTime);
    }elseif($type=='ymd'){
        return date("Y-m-d",$sTime);
    }else{
        if( $dTime < 60 ){
            return $dTime.lang('_SECONDS_AGO_');
        }elseif( $dTime < 3600 ){
            return intval($dTime/60).lang('_MINUTES_AGO_');
        }elseif( $dTime >= 3600 && $dDay == 0  ){
            return intval($dTime/3600).lang('_HOURS_AGO_');
        }elseif($dYear==0){
            return date("Y-m-d H:i:s",$sTime);
        }else{
            return date("Y-m-d H:i:s",$sTime);
        }
    }
}

// 不区分大小写的in_array实现
function in_array_case($value, $array)
{
    return in_array(strtolower($value), array_map('strtolower', $array));
}

/**
 * 把返回的数据集转换成Tree
 * @param array $list 要转换的数据集
 * @param string $pid parent标记字段
 * @param string $level level标记字段
 * @return array
 */
function list_to_tree($list, $pk = 'id', $pid = 'pid', $child = '_child', $root = 0)
{


    // 创建Tree
    $tree = [];
    if (is_array($list)) {
        // 创建基于主键的数组引用
        $refer = [];
        foreach ($list as $key => $data) {
            $refer[$data[$pk]] =& $list[$key];
        }
        foreach ($list as $key => $data) {
            // 判断是否存在parent
            $parentId = $data[$pid];
            if ($root == $parentId) {
                $tree[] =& $list[$key];
            } else {
                if (isset($refer[$parentId])) {
                    $parent =& $refer[$parentId];
                    $parent[$child][] =& $list[$key];
                }
            }
        }
    }

    return $tree;
}

/**
 * 将list_to_tree的树还原成列表
 * @param  array $tree 原来的树
 * @param  string $child 孩子节点的键
 * @param  string $order 排序显示的键，一般是主键 升序排列
 * @param  array $list 过渡用的中间数组，
 * @return array        返回排过序的列表数组
 */
function tree_to_list($tree, $child = '_child', $order = 'id', &$list = [])
{
    if (is_array($tree)) {
        $refer = [];
        foreach ($tree as $key => $value) {
            $reffer = $value;
            if (isset($reffer[$child])) {
                unset($reffer[$child]);
                tree_to_list($value[$child], $child, $order, $list);
            }
            $list[] = $reffer;
        }
        $list = list_sort_by($list, $order, $sortby = 'asc');
    }
    return $list;
}

/**
 * XML编码
 * @param mixed $data 数据
 * @param string $root 根节点名
 * @param string $item 数字索引的子节点名
 * @param string $attr 根节点属性
 * @param string $id 数字索引子节点key转换的属性名
 * @param string $encoding 数据编码
 * @return string
 */
function xml_encode($data, $root = 'think', $item = 'item', $attr = '', $id = 'id', $encoding = 'utf-8')
{
    if (is_array($attr)) {
        $_attr = [];
        foreach ($attr as $key => $value) {
            $_attr[] = "{$key}=\"{$value}\"";
        }
        $attr = implode(' ', $_attr);
    }
    $attr = trim($attr);
    $attr = empty($attr) ? '' : " {$attr}";
    $xml = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>";
    $xml .= "<{$root}{$attr}>";
    $xml .= data_to_xml($data, $item, $id);
    $xml .= "</{$root}>";
    return $xml;
}

/**
 * 数据XML编码
 * @param mixed $data 数据
 * @param string $item 数字索引时的节点名称
 * @param string $id 数字索引key转换为的属性名
 * @return string
 */
function data_to_xml($data, $item = 'item', $id = 'id')
{
    $xml = $attr = '';
    foreach ($data as $key => $val) {
        if (is_numeric($key)) {
            $id && $attr = " {$id}=\"{$key}\"";
            $key = $item;
        }
        $xml .= "<{$key}{$attr}>";
        $xml .= (is_array($val) || is_object($val)) ? data_to_xml($val, $item, $id) : $val;
        $xml .= "</{$key}>";
    }
    return $xml;
}

if (!function_exists('array_column')) {
    function array_column(array $input, $columnKey, $indexKey = null)
    {
        $result = array();
        if (null === $indexKey) {
            if (null === $columnKey) {
                $result = array_values($input);
            } else {
                foreach ($input as $row) {
                    $result[] = $row[$columnKey];
                }
            }
        } else {
            if (null === $columnKey) {
                foreach ($input as $row) {
                    $result[$row[$indexKey]] = $row;
                }
            } else {
                foreach ($input as $row) {
                    $result[$row[$indexKey]] = $row[$columnKey];
                }
            }
        }
        return $result;
    }
}

/**
 * 字符串截取，支持中文和其他编码
 * @param $str 需要转换的字符串
 * @param int $start 开始位置
 * @param $length 截取长度
 * @param string $charset 编码格式
 * @param bool|true $suffix 截断显示字符
 * @return string
 */
function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = true)
{
    if (function_exists("mb_substr"))
        $slice = mb_substr($str, $start, $length, $charset);
    elseif (function_exists('iconv_substr')) {
        $slice = iconv_substr($str, $start, $length, $charset);
        if (false === $slice) {
            $slice = '';
        }
    } else {
        $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("", array_slice($match[0], $start, $length));
    }
    return $suffix ? $slice . '...' : $slice;
}


/**
 * 根据条件字段获取指定表的数据
 * @param null $value 条件，可用常量或者数组
 * @param string $condition 条件字段
 * @param null $field 需要返回的字段，不传则返回整个数据
 * @param null $table 需要查询的表
 * @return bool
 */
function get_table_field($value = null, $condition = 'id', $field = null, $table = null)
{
    if (empty($value) || empty($table)) {
        return false;
    }

    //拼接参数
    $map[$condition] = $value;
    $info = db(ucfirst($table))->where($map);
    if (empty($field)) {
        $info = $info->field(true)->find();
    } else {
        $info = $info->value($field);
    }
    return $info;
}
/**
 * select返回的数组进行整数映射转换
 *
 * @param array $map 映射关系二维数组  array(
 *                                          '字段名1'=>array(映射关系数组),
 *                                          '字段名2'=>array(映射关系数组),
 *                                           ......
 *                                       )
 * @return array
 *
 *  array(
 *      array('id'=>1,'title'=>'标题','status'=>'1','status_text'=>'正常')
 *      ....
 *  )
 *
 */
function int_to_string(&$data, $map = ['status' => [1 => '正常', -1 => '删除', 0 => '禁用', 2 => '未审核', 3 => '草稿']])
{
    if ($data === false || $data === null) {
        return $data;
    }
    foreach ($data as $key => $row) {
        foreach ($map as $col => $pair) {
            if (isset($row[$col]) && isset($pair[$row[$col]])) {
                $data[$key][$col . '_text'] = $pair[$row[$col]];
            }
        }
    }
    return $data;
}

function lists_plus(&$data)
{
    $alias = db('module')->select();
    $alias_set = [];
    foreach ($alias as $value) {
        $alias_set[$value['name']] = $value['alias'];
    }
    foreach ($data as $key => $value) {
        if(!empty($data[$key]['module'])){
            $data[$key]['alias'] = $alias_set[$data[$key]['module']];
        }

        $mid = db('action_log')->field("max(create_time),remark")->where('action_id=' . $data[$key]['id'])->select();
        $mid_s = $mid[0]['remark'];
        if( isset($mid_s) && strpos($mid_s , lang('_INTEGRAL_')) !== false)
        {
            $data[$key]['vary'] = $mid_s;
        }

    }
    return $data;
}

/**
 * 获取行为类型
 * @param intger $type 类型
 * @param bool $all 是否返回全部类型
 */
function get_action_type($type, $all = false)
{
    $list = array(
        1 => lang('_SYSTEM_'),
        2 => lang('_USER_'),
    );
    if ($all) {
        return $list;
    }
    return $list[$type];
}


/**
 * curl_get_headers 获取链接header
 * @param $url
 * @return array
 */
function curl_get_headers($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    $f = curl_exec($ch);
    curl_close($ch);
    $h = explode("\n", $f);
    $r = [];
    foreach ($h as $t) {
        $rr = explode(":", $t, 2);
        if (count($rr) == 2) {
            $r[$rr[0]] = trim($rr[1]);
        }
    }
    return $r;
}

/**
 * 敏感词过滤
 * @param $content
 * @return string
 */
function sensitive_text($content)
{
    $is_open = modC('OPEN_SENSITIVE', 0, 'Sensitive');
    if ($is_open) {
        $replace_words = cache('replace_sensitive_words');
        if (empty($replace_words)) {
            $words = db('Sensitive')->where(['status' => 1])->select();
            $words = getSubByKey($words, 'title');
            $replace_words = array_combine($words, array_fill(0, count($words), '***'));
            cache('replace_sensitive_words', $replace_words);
        }
        !empty($replace_words) && $content = strtr($content, $replace_words);
    }
    return $content;
}

function get_content_by_url($url){
    $md5 = md5($url);
    $content = cache('file_content_'.$md5);
    if(is_bool($content)){
        $content = curl_file_get_contents($url);
        cache('file_content_'.$md5,$content,60*60);
    }
    return $content;
}

function curl_file_get_contents($durl){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $durl);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, '');
    curl_setopt($ch, CURLOPT_REFERER,'b');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $file_contents = curl_exec($ch);
    curl_close($ch);
    return $file_contents;
}

/**
 * 获取行为数据
 * @param string $id 行为id
 * @param string $field 需要获取的字段
 */
function get_action($id = null, $field = null)
{
    if (empty($id) && !is_numeric($id)) {
        return false;
    }
    $list = cache('action_list');
    if (empty($list[$id])) {
        $map = ['status' => ['gt', -1], 'id' => $id];
        $list[$id] = db('Action')->where($map)->field(true)->find();
    }
    return empty($field) ? $list[$id] : $list[$id][$field];
}

/**
 * 对象转数组
 * @param $array
 * @return array
 */
function object_array($array) {
    if(is_object($array)) {
        $array = (array)$array;
    } if(is_array($array)) {
        foreach($array as $key=>$value) {
            $array[$key] = object_array($value);
        }
    }
    return $array;
}

/**
 * 系统加密方法
 * @param string $data 要加密的字符串
 * @param string $key 加密密钥
 * @param int $expire 过期时间 单位 秒
 * @return string
 */
function think_encrypt($data, $key = '', $expire = 0)
{
    $key = md5(empty($key) ? config('data_auth_key') : $key);
    $data = base64_encode($data);
    $x = 0;
    $len = strlen($data);
    $l = strlen($key);
    $char = '';

    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) $x = 0;
        $char .= substr($key, $x, 1);
        $x++;
    }

    $str = sprintf('%010d', $expire ? $expire + time() : 0);

    for ($i = 0; $i < $len; $i++) {
        $str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1))) % 256);
    }
    return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($str));
}

/**
 * 系统解密方法
 * @param  string $data 要解密的字符串 （必须是think_encrypt方法加密的字符串）
 * @param  string $key 加密密钥
 * @return string
 */
function think_decrypt($data, $key = '')
{
    $key = md5(empty($key) ? config('data_auth_key') : $key);
    $data = str_replace(['-', '_'], ['+', '/'], $data);
    $mod4 = strlen($data) % 4;
    if ($mod4) {
        $data .= substr('====', $mod4);
    }
    $data = base64_decode($data);
    $expire = substr($data, 0, 10);
    $data = substr($data, 10);

    if ($expire > 0 && $expire < time()) {
        return '';
    }
    $x = 0;
    $len = strlen($data);
    $l = strlen($key);
    $char = $str = '';

    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) $x = 0;
        $char .= substr($key, $x, 1);
        $x++;
    }

    for ($i = 0; $i < $len; $i++) {
        if (ord(substr($data, $i, 1)) < ord(substr($char, $i, 1))) {
            $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
        } else {
            $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
        }
    }
    return base64_decode($str);
}

/**
 * check_driver_is_exist 判断上传驱动插件是否存在
 * @param $driver
 * @return string
 */
function check_driver_is_exist($driver)
{
    if ($driver == 'local') {
        return $driver;
    } else {
        $name = get_addon_class($driver);
        if (class_exists($name)) {
            return $driver;
        } else {
            return 'local';
        }
    }
}

/**
 * get_upload_config  获取上传驱动配置
 * @param $driver
 * @return mixed
 */
function get_upload_config($driver)
{
    if ($driver == 'local') {
        $uploadConfig = config("upload_{$driver}_config");
    } else {
        $name = get_addon_class($driver);
        $class = new $name();
        $uploadConfig = $class->uploadConfig();
    }
    return $uploadConfig;
}

function render_picture_path($path)
{
    $path = get_pic_src($path);
    return is_bool(strpos($path, 'http://')) ? 'http://' . str_replace('//', '/', $_SERVER['HTTP_HOST'] . '/' . $path) : $path;
}

/**
 * 发送HTTP状态
 * @param integer $code 状态码
 * @return void
 */
function send_http_status($code)
{
    static $_status = array(
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        // Success 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Moved Temporarily ', // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        // 306 is deprecated but reserved
        307 => 'Temporary Redirect',
        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        509 => 'Bandwidth Limit Exceeded'
    );
    if (isset($_status[$code])) {
        header('HTTP/1.1 ' . $code . ' ' . $_status[$code]);
        // 确保FastCGI模式下正常
        header('Status:' . $code . ' ' . $_status[$code]);
    }
}

/**
 * 获取模版文件 格式 资源://模块@主题/控制器/操作
 * @param string $template 模版资源地址
 * @param string $layer 视图层（目录）名称
 * @return string
 */
function T($template = '', $layer = 'view')
{
    // 解析模版资源地址
    if (false === strpos($template, '://')) {
        $template = 'http://' . str_replace(':', '/', $template);
    }

    $info = parse_url($template);

    $file = $info['host'] . (isset($info['path']) ? $info['path'] : '');
    $module = isset($info['user']) ? $info['user'] . '/' : Request()->module() . '/';
    $extend = $info['scheme'];

    // 分析模板文件规则
    $depr = '/';
    if ('' == $file) {
        // 如果模板文件名为空 按照默认规则定位
        $file = Request()->controller() . $depr . Request()->action();
    } elseif (false === strpos($file, '/')) {
        $file = Request()->controller() . $depr . $file;
    } elseif ('/' != $depr) {
        $file = substr_count($file, '/') > 1 ? substr_replace($file, $depr, strrpos($file, '/'), 1) : str_replace('/', $depr, $file);
    }

    // 获取当前主题的模版路径
    $auto = config('autoload_namespace');
    if ($auto && isset($auto[$extend])) { // 扩展资源
        $baseUrl = $auto[$extend] . $module . $layer . '/';
    }

    if (!isset($baseUrl)) {
        /**
         * 增加模板地址解析机制 start
         */
        $file_theme=$file;
        if(substr_count($file_theme, '/') > 2 ){
            $file_theme=substr($file_theme,strpos($file_theme,'/')+1);
        }
        $TO_LOOK_THEME = cookie('TO_LOOK_THEME', '', array('prefix' => 'TP5.0.10'));
        if ($TO_LOOK_THEME) {
            if ($TO_LOOK_THEME != 'default') {
                $file_path = TP_THEME_PATH . $TO_LOOK_THEME . '/' . $module . $layer . '/' . $file_theme.'.' . config('template.view_suffix');
            }
        } else {
            $now_theme = modC('NOW_THEME', 'default', 'Theme');
            if ($now_theme != 'default') {
                $file_path = TP_THEME_PATH . $now_theme . '/' . $module . $layer . '/' . $file_theme.'.' . config('template.view_suffix');
            }
        }
        if (isset($file_path) && is_file($file_path)) {
            return $file_path;
        }
        /**
         * 增加模板地址解析机制 end
         */
        if (config('view_path')) {
            // 改变模块视图目录
            $baseUrl = config('view_path');
        } elseif (defined('TMPL_PATH')) {
            // 指定全局视图目录
            $baseUrl = TMPL_PATH . $module;
        } else {
            $baseUrl = APP_PATH . $module . $layer . '/';
        }
    }
    // 获取主题
    $theme = substr_count($file, '/') < 2 ? config('DEFAULT_THEME') : '';

    //如果模版存在，则返回该模版
    $result = $baseUrl . ($theme ? $theme . '/' : '') . $file . '.' . config('template.view_suffix');
    if (is_file($result)) {
        return $result;
    }
    /**
     * 如果模版存在，则返回主题公共目录下的模版 start
     */
    $TO_LOOK_THEME = cookie('TO_LOOK_THEME', '', array('prefix' => 'TP5.0.10'));
    if ($TO_LOOK_THEME) {
        if ($TO_LOOK_THEME != 'default') {
            $common_file_path = TP_THEME_PATH . $TO_LOOK_THEME . '/common/'.$layer.'/'. $file . '.' . config('template.view_suffix');
        }
    } else {
        $now_theme = modC('NOW_THEME', 'default', 'theme');
        if ($now_theme != 'default') {
            $common_file_path = TP_THEME_PATH . $now_theme . '/common/' .$layer.'/'.$file . '.' . config('template.view_suffix');
        }
    }
    if (isset($common_file_path) && is_file($common_file_path)) {
        return $common_file_path;
    }
    /**
     * 如果模版存在，则返回主题公共目录下的模版 end
     */
    //如果模版不存在，则返回公共目录下的模版
    $baseUrl = APP_PATH . 'common/view/' . ($theme ? $theme . '/' : '');
    $result = $baseUrl . $file . '.' . config('template.view_suffix');
    return $result;
}

// 获取数据的状态操作
function show_status_op($status)
{
    switch ($status) {
        case 0  :
            return lang('_ENABLE_');
            break;
        case 1  :
            return lang('_DISABLE_');
            break;
        case 2  :
            return lang('_AUDIT_');
            break;
        default :
            return false;
            break;
    }
}

/**
 * 格式化字节大小
 * @param  number $size 字节数
 * @param  string $delimiter 数字和单位分隔符
 * @return string            格式化后的带单位的大小
 */
function format_bytes($size, $delimiter = '')
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    for ($i = 0; $size >= 1024 && $i < 5; $i++) $size /= 1024;
    return round($size, 2) . $delimiter . $units[$i];
}

/**
 * 分析枚举类型配置值 格式 a:名称1,b:名称2
 * @param $string
 * @return array
 */
function parse_config_attr($string)
{
    $array = preg_split('/[,;\r\n]+/', trim($string, ",;\r\n"));
    if (strpos($string, ':')) {
        $value = array();
        foreach ($array as $val) {
            list($k, $v) = explode(':', $val);
            $value[$k] = $v;
        }
    } else {
        $value = $array;
    }
    return $value;
}

/**
 * 获取配置的类型
 * @param int $type 配置类型
 * @return string
 */
function get_config_type($type = 0)
{
    $list = config('CONFIG_TYPE_LIST');
    return $list[$type];
}

/**
 * 获取配置的分组
 * @param int $group 配置分组
 * @return string
 */
function get_config_group($group = 0)
{
    $list = config('CONFIG_GROUP_LIST');
    return $group ? $list[$group] : '';
}

/**
 * 删除驱动上传的文件
 * @param $file_name 文件名，不带前面路径
 * @param $driver 七牛、sae等
 * @return bool
 */
function delete_driver_upload_file($file_name,$driver)
{
    $return= ['status'=>0,'info'=>'本地文件，不能调用该函数'];
    if($driver=='local'){
        return $return;
    }
    $class = get_addon_class($driver);
    if (class_exists($class)) {
        $class=new $class;
        if(method_exists($class,'deleteFile')){
            //todo 七牛删除文件方法已经做了，sae的没有做，要做到sae插件中
            if($class->deleteFile($file_name)){//执行删除远端文件
                $return['info']='删除成功！';
                $return['status']=1;
            }else{
                $return['info']='删除失败！';
            }
        }else{
            $return['info']='删除远端文件方法不存在！';
        }
    } else {
        $return['info']='删除远端文件驱动不存在！';
    }
    return $return;
}

function array_subtract($a, $b)
{
    return array_diff($a, array_intersect($a, $b));
}

function check_auth($rule = '', $except_uid = -1, $type = AuthRuleModel::RULE_URL)
{
    if (is_administrator()) {
        return true;//管理员允许访问任何页面
    }
    if ($except_uid != -1) {
        if (!is_array($except_uid)) {
            $except_uid = explode(',', $except_uid);
        }
        if (in_array(is_login(), $except_uid)) {
            return true;
        }
    }
    $rule = empty($rule) ? Request()->module() . '/' . Request()->controller() . '/' . Request()->action() : $rule;
    // 检测是否有该权限
    if (!db('auth_rule')->where(['name' => $rule, 'status' => 1])->find()) {
        return false;
    }
    static $Auth = null;
    if (!$Auth) {
        $Auth = new \think\Auth();
    }
    if (!$Auth->check($rule, is_login(), $type)) {
        return false;
    }
    return true;

}

function check_verify_open($open)
{
    $config = config('VERIFY_OPEN');

    if ($config) {
        $config = explode(',', $config);
        if (in_array($open, $config)) {
            return true;
        }
    }
    return false;
}

/**
 * 字符串转换为数组，主要用于把分隔符调整到第二个参数
 * @param  string $str 要分割的字符串
 * @param  string $glue 分割符
 * @return array
 */
function str2arr($str, $glue = ',')
{
    return explode($glue, $str);
}

/**
 * 数组转换为字符串，主要用于把分隔符调整到第二个参数
 * @param  array $arr 要连接的数组
 * @param  string $glue 分割符
 * @return string
 */
function arr2str($arr, $glue = ',')
{
    return implode($glue, $arr);
}

//基于数组创建目录和文件
function create_dir_or_files($files)
{
    foreach ($files as $key => $value) {
        if (substr($value, -1) == '/') {
            mkdir($value);
        } else {
            @file_put_contents($value, '');
        }
    }
}

/**
 * 字符串命名风格转换
 * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
 * @param string $name 字符串
 * @param integer $type 转换类型
 * @return string
 */
function parse_name($name, $type = 0)
{
    if ($type) {
        return ucfirst(preg_replace_callback('/_([a-zA-Z])/', function ($match) {
            return strtoupper($match[1]);
        }, $name));
    } else {
        return strtolower(trim(preg_replace("/[A-Z]/", "_\\0", $name), "_"));
    }
}

function is_mobile()
{
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $mobile_agents = ["240x320", "acer", "acoon", "acs-", "abacho", "ahong", "airness", "alcatel", "amoi", "android", "anywhereyougo.com", "applewebkit/525", "applewebkit/532", "asus", "audio", "au-mic", "avantogo", "becker", "benq", "bilbo", "bird", "blackberry", "blazer", "bleu", "cdm-", "compal", "coolpad", "danger", "dbtel", "dopod", "elaine", "eric", "etouch", "fly ", "fly_", "fly-", "go.web", "goodaccess", "gradiente", "grundig", "haier", "hedy", "hitachi", "htc", "huawei", "hutchison", "inno", "ipad", "ipaq", "ipod", "jbrowser", "kddi", "kgt", "kwc", "lenovo", "lg ", "lg2", "lg3", "lg4", "lg5", "lg7", "lg8", "lg9", "lg-", "lge-", "lge9", "longcos", "maemo", "mercator", "meridian", "micromax", "midp", "mini", "mitsu", "mmm", "mmp", "mobi", "mot-", "moto", "nec-", "netfront", "newgen", "nexian", "nf-browser", "nintendo", "nitro", "nokia", "nook", "novarra", "obigo", "palm", "panasonic", "pantech", "philips", "phone", "pg-", "playstation", "pocket", "pt-", "qc-", "qtek", "rover", "sagem", "sama", "samu", "sanyo", "samsung", "sch-", "scooter", "sec-", "sendo", "sgh-", "sharp", "siemens", "sie-", "softbank", "sony", "spice", "sprint", "spv", "symbian", "tablet", "talkabout", "tcl-", "teleca", "telit", "tianyu", "tim-", "toshiba", "tsm", "up.browser", "utec", "utstar", "verykool", "virgin", "vk-", "voda", "voxtel", "vx", "wap", "wellco", "wig browser", "wii", "windows ce", "wireless", "xda", "xde", "zte"];
    $is_mobile = false;
    foreach ($mobile_agents as $device) {
        if (stristr($user_agent, $device)) {
            $is_mobile = true;
            break;
        }
    }
    return $is_mobile;
}

/**
 * 动态扩展左侧插件菜单,base.html里用到
 * @param $base_menu
 */
function extra_addons_menu(&$base_menu)
{
    $addonsModel = new \app\common\model\AddonsModel();
    $extra_menu=[lang('_ALREADY_INSTALLED_IN_THE_BACKGROUND_') => $addonsModel->getAdminList(),];
    foreach ($extra_menu as $key => $group) {
        if (isset($base_menu['children'][$key])) {
            $base_menu['children'][$key] = array_merge($base_menu['children'][$key], $group);
        } else {
            $base_menu['children'][$key] = $group;
        }
    }
}

/**
 * get_some_day  获取n天前0点的时间戳
 * @param int $some n天
 * @param null $day 当前时间
 * @return int|null
 */
function get_some_day($some = 30, $day = null)
{
    $time = $day ? $day : time();
    $some_day = $time - 60 * 60 * 24 * $some;
    $btime = date('Y-m-d' . ' 00:00:00', $some_day);
    $some_day = strtotime($btime);
    return $some_day;
}

/**
 * 获取插件类的配置文件数组
 * @param string $name 插件名
 */
function get_addon_config($name)
{
    $class = get_addon_class($name);
    if (class_exists($class)) {
        $addon = new $class();
        return $addon->getConfig();
    } else {
        return [];
    }
}

/**
 * get_ip_lookup  获取ip地址所在的区域
 * @param null $ip
 * @return bool|mixed
 * @author:xjw129xjt(肖骏涛) xjt@ourstu.com
 */
function get_ip_lookup($ip = null)
{
    if (empty($ip)) {
        $ip = get_client_ip(0);
    }
    $res = @file_get_contents('http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=js&ip=' . $ip);
    if (empty($res)) {
        return false;
    }
    $jsonMatches = [];
    preg_match('#\{.+?\}#', $res, $jsonMatches);
    if (!isset($jsonMatches[0])) {
        return false;
    }
    $json = json_decode($jsonMatches[0], true);
    if (isset($json['ret']) && $json['ret'] == 1) {
        $json['ip'] = $ip;
        unset($json['ret']);
    } else {
        return false;
    }
    return $json;
}

/**
 * 插件显示内容里生成访问插件的url
 * @param $url
 * @param array $param
 * @param bool|true $suffix
 * @param bool|false $domain
 * @return bool|mixed
 */
function addons_url($url, $param = array(), $suffix = true, $domain = false)
{
    $url = parse_url($url);
    $case = config('url_case_insensitive');
    $addons = $case ? parse_name($url['scheme']) : $url['scheme'];
    $controller = $case ? parse_name($url['host']) : $url['host'];
    $action = trim($case ? strtolower($url['path']) : $url['path'], '/');

    /* 解析URL带的参数 */
    if (isset($url['query'])) {
        parse_str($url['query'], $query);
        $param = array_merge($query, $param);
    }

    /* 基础参数 */
    $params = [
        '_addons' => $addons,
        '_controller' => $controller,
        '_action' => $action,
    ];
    $params = array_merge($params, $param); //添加额外参数
    if (strtolower(Request()->module()) == 'backstage') {
        return url('backstage/Addons/execute', $params, $suffix, $domain);
    } else {
        $urls = url('home/Addons/execute', $params, $suffix, $domain);
        $addons_url = str_replace("/execute", "", $urls);
        return $addons_url;

    }

}

function tox_addons_url($url, $param)
{
    // 拆分URL
    $url = explode('/', $url);
    $addon = $url[0];
    $controller = $url[1];
    $action = $url[2];

    // 调用u函数
    $param['_addons'] = $addon;
    $param['_controller'] = $controller;
    $param['_action'] = $action;
    $urls = url("home/Addons/execute", $param);
    $addons_url = str_replace("/execute", "", $urls);
    return $addons_url;
}
/**
 * 自定义异常处理
 * @param string $msg 异常消息
 * @param string $type 异常类型 默认为Think\Exception
 * @param integer $code 异常代码 默认为0
 * @return void
 */
function throw_exception($msg, $type = 'think\\Exception', $code = 0)
{
    \think\Log::record('建议使用exception方法替代throw_exception', Think\Log::NOTICE);
    if (class_exists($type, false))
        throw new $type($msg, $code);
}

/**
 * 获取插件类的类名
 * @param $name
 * @param string $addonsname
 * @return string
 */
function get_Addons_model($name,$addonsname='')
{
    $name      = ucwords($name);
    $addonsname = ucwords($addonsname);
    if(empty($addonsname)) $addonsname = $name;
    $class     = "addons\\{$addonsname}\\model\\{$name}Model";
    return $class;
}

/**
 * 获取插件类的控制器
 * @param $addons
 * @param $name
 * @return string
 */
function get_addons_controller($addons,$name){
    $name = ucwords($name);
    $addons = ucwords($addons);
    $controller = "addons\\{$addons}\\controller\\{$name}Controller";
    return $controller;
}

/**
 * 获取导航URL
 * @param  string $url 导航URL
 * @return string      解析或的url
 */
function get_nav_url($url)
{
    switch ($url) {
        case 'http://' === substr($url, 0, 7):
        case 'https://' === substr($url, 0, 8):
        case '#' === substr($url, 0, 1):
            break;
        default:
            $url = url($url);
            break;
    }
    return $url;
}

/**
 * @param $url 检测当前url是否被选中
 * @return bool|string
 */
function get_nav_active($url)
{
    switch ($url) {
        case 'http://' === substr($url, 0, 7):
            if (strtolower($url) === strtolower($_SERVER['HTTP_REFERER'])) {
                return 1;
            }
        case '#' === substr($url, 0, 1):
            return 0;
            break;
        default:
            $url_array = explode('/', $url);
            if ($url_array[0] == '') {
                $MODULE_NAME = $url_array[1];
            } else {
                $MODULE_NAME = $url_array[0]; //发现模块就是当前模块即选中。

            }
            if (strtolower($MODULE_NAME) === strtolower(MODULE_NAME)) {
                return 1;
            };
            break;

    }
    return 0;
}

/**
 * 从数组中删除空白的元素（包括只有空白字符的元素）
 * @param $arr
 * @param bool|true $trim
 * @return mixed
 */
function array_remove_empty(&$arr, $trim = true){
    foreach ($arr as $key => $value) {
        if (is_array($value)) {
            array_remove_empty($arr[$key]);
        } else {
            $value = trim($value);
            if ($value=='' ) {unset($arr[$key]); }
        }
    }
    return $arr;
}

/**
 * ,15,5,,5,18,=>15,5,18
 * @param $str
 * @param string $adition
 * @param string $par
 * @return string
 */
function sliper($str,$adition='',$par=','){
    $tmp = explode($par,$str);
    array_remove_empty($tmp);

    if($adition!='') $tmp[]=$adition;
    $tmp = array_flip(array_flip($tmp));//去重复
    return implode($par,$tmp);
}


if(!function_exists('mysql_pconnect')){
    function mysql_pconnect($dbhost, $dbuser, $dbpass){
        global $dbport;
        global $dbname;
        global $mysqli;
        $mysqli = mysqli_connect("$dbhost:$dbport", $dbuser, $dbpass, $dbname);
        return $mysqli;
    }
    function mysql_select_db($dbname){
        global $mysqli;
        return mysqli_select_db($mysqli,$dbname);
    }
    function mysql_fetch_array($result){
        return mysqli_fetch_array($result);
    }
    function mysql_fetch_assoc($result){
        return mysqli_fetch_assoc($result);
    }
    function mysql_fetch_row($result){
        return mysqli_fetch_row($result);
    }
    function mysql_query($cxn){
        global $mysqli;
        return mysqli_query($mysqli,$cxn);
    }
    function mysql_escape_string($data){
        global $mysqli;
        return mysqli_real_escape_string($mysqli, $data);
    }
    function mysql_real_escape_string($data){
        return mysql_real_escape_string($data);
    }
    function mysql_close(){
        global $mysqli;
        return mysqli_close($mysqli);
    }
}
