<?php
namespace app\backstage\controller;

use app\common\controller\BaseController;
use app\common\model\AddonsModel;
use app\common\model\AuthRuleModel;
use app\common\model\ModuleModel;
use app\common\model\MenuModel;

class backstageController extends BaseController{

    public function _initialize(){
        header("Content-type: text/html; charset=utf-8");
        // 获取当前用户ID
        define('UID', is_login());

        if (!UID) { // 还没登录 跳转到登录页面
            $this->redirect('Login/index');
        }
        /* 读取数据库中的配置 */
        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置

        // 是否是超级管理员
        define('IS_ROOT', is_administrator());
        if (!IS_ROOT && config('ADMIN_ALLOW_IP')) {
            // 检查IP地址访问
            if (!in_array(get_client_ip(), explode(',', config('ADMIN_ALLOW_IP')))) {
                $this->error(lang('_FORBID_403_'));
            }
        }
        // 检测访问权限
        $access = $this->accessControl();
        if ($access === false) {
            $this->error(lang('_FORBID_403_'));
        } elseif ($access === null) {
            $dynamic = $this->checkDynamic();//检测分类栏目有关的各项动态权限
            if ($dynamic === null) {
                //检测非动态权限
                $rule = strtolower(Request()->module() . '/' . Request()->controller() . '/' . Request()->action());
                if (!$this->checkRule($rule, ['in', '1,2'])) {
                    $this->error(lang('_VISIT_NOT_AUTH_'));
                }
            } elseif ($dynamic === false) {
                $this->error(lang('_VISIT_NOT_AUTH_'));
            }
        }

        $this->assign('__MANAGE_COULD__', $this->checkRule('backstage/module/lists', ['in', '1,2']));

        $this->assign('__MENU__', $this->getMenus());
        $this->assign('__MODULE_MENU__', $this->getModules());
        $this->assign('__ADDONS_MENU__', $this->getAddons());

        $this->assign('__CONTROLLER__',strtolower(Request()->controller()));

        //导入模块语言包
        import_lang(ucfirst(Request()->controller()));

        //导入公共模块语言包
        import_lang("common");

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

    /**
     * 权限检测
     * @param $rule 检测的规则
     * @param string $type
     * @param string $mode check模式
     * @return bool
     */
    final protected function checkRule($rule, $type ='', $mode = 'url')
    {
        if (IS_ROOT) {
            return true;//管理员允许访问任何页面
        }
        static $Auth = null;
        if (!$Auth) {
            $Auth = new \think\Auth();
        }
        if(empty($type))  $type = AuthRuleModel::RULE_URL;
        if (!$Auth->check($rule, UID, $type, $mode)) {
            return false;
        }
        return true;
    }

    /**
     * 检测是否是需要动态判断的权限
     * @return boolean|null
     *      返回true则表示当前访问有权限
     *      返回false则表示当前访问无权限
     *      返回null，则会进入checkRule根据节点授权判断权限
     */
    protected function checkDynamic()
    {
        if (IS_ROOT) {
            return true;//管理员允许访问任何页面
        }
        return null;//不明,需checkRule
    }
    /**
     * action访问控制,在 **登陆成功** 后执行的第一项权限检测任务
     *
     * @return boolean|null  返回值必须使用 `===` 进行判断
     *
     *   返回 **false**, 不允许任何人访问(超管除外)
     *   返回 **true**, 允许任何管理员访问,无需执行节点权限检测
     *   返回 **null**, 需要继续执行节点权限检测决定是否允许访问
     */
    final protected function accessControl()
    {
        if (IS_ROOT) {
            return true;//管理员允许访问任何页面
        }
        $allow = config('ALLOW_VISIT');
        $deny = config('DENY_VISIT');
        $check = strtolower(Request()->controller() . '/' . Request()->action());
        if (!empty($deny) && in_array_case($check, $deny)) {
            return false;//非超管禁止访问deny中的方法
        }
        if (!empty($allow) && in_array_case($check, $allow)) {
            return true;
        }
        return null;//需要检测节点权限
    }

    /**
     * 对数据表中的单行或多行记录执行修改 GET参数id为数字或逗号分隔的数字
     *
     * @param string $model 模型名称,供M函数使用的参数
     * @param array $data 修改的数据
     * @param array $where 查询时的where()方法的参数
     * @param array $msg 执行正确和错误的消息 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
     *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
     */
    final protected function editRow($model, $data, $where, $msg)
    {
        $param = $this->request->param();
        $id = array_unique($param['ids']);
        $id = is_array($id) ? implode(',', $id) : $id;
        $where = array_merge(['id' => ['in', $id]], (array)$where);
        $msg = array_merge(['success' => lang('_OPERATION_SUCCESS_'), 'error' => lang('_OPERATION_FAILED_'), 'url' => '', 'ajax' => Request()->isAjax()], (array)$msg);
        if (model($model)->where($where)->save($data) !== false) {
            $this->success($msg['success'], $msg['url'], $msg['ajax']);
        } else {
            $this->error($msg['error'], $msg['url'], $msg['ajax']);
        }

    }

    /**
     * 禁用条目
     * @param string $model 模型名称,供D函数使用的参数
     * @param array $where 查询时的 where()方法的参数
     * @param array $msg 执行正确和错误的消息,可以设置四个元素 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
     *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
     */
    protected function forbid($model, $where = [], $msg = ['success' => '状态禁用成功！', 'error' => '状态禁用失败！'])
    {
        $data = ['status' => 0];
        $this->editRow($model, $data, $where, $msg);
    }

    /**
     * 恢复条目
     * @param string $model 模型名称,供D函数使用的参数
     * @param array $where 查询时的where()方法的参数
     * @param array $msg 执行正确和错误的消息 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
     *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
     */
    protected function resume($model, $where = [], $msg = ['success' => '状态恢复成功！', 'error' => '状态恢复失败！'])
    {
        $data = ['status' => 1];
        $this->editRow($model, $data, $where, $msg);
    }

    /**
     * 还原条目
     * @param string $model 模型名称,供D函数使用的参数
     * @param array $where 查询时的where()方法的参数
     * @param array $msg 执行正确和错误的消息 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
     *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
     */
    protected function restore($model, $where = [], $msg = ['success' => '状态还原成功！', 'error' => '状态还原失败！'])
    {
        $data = ['status' => 1];
        $where = array_merge(['status' => -1], $where);
        $this->editRow($model, $data, $where, $msg);
    }

    /**
     * 条目假删除
     * @param string $model 模型名称,供D函数使用的参数
     * @param array $where 查询时的where()方法的参数
     * @param array $msg 执行正确和错误的消息 array('success'=>'','error'=>'', 'url'=>'','ajax'=>false)
     *                     url为跳转页面,ajax是否ajax方式(数字则为倒数计时秒数)
     */
    protected function delete($model, $where = [], $msg = ['success' => '删除成功！', 'error' => '删除失败！'])
    {
        $data = ['status'=>-1];
        $this->editRow($model, $data, $where, $msg);
    }

    /**
     * 设置一条或者多条数据的状态
     */
    public function setStatus($Model = '')
    {
        $Model = !empty($Model) ? $Model:Request()->controller();

        $param = $this->request->param();
        $ids = $param['ids'];
        $status = $param['status'];
        if (empty($ids)) {
            $this->error(lang('_PLEASE_CHOOSE_THE_DATA_TO_BE_OPERATED_'));
        }

        $map['id'] = array('in', $ids);
        switch ($status) {
            case -1 :
                $this->delete($Model, $map, ['success' => lang('_DELETE_SUCCESS_EXCLAMATION_'), 'error' => lang('_DELETE_FAILED_EXCLAMATION_')]);
                break;
            case 0  :
                $this->forbid($Model, $map, ['success' => lang('_DISABLE_SUCCESS_'), 'error' => lang('_DISABLE_FAIL_')]);
                break;
            case 1  :
                $this->resume($Model, $map, ['success' => lang('_ENABLE_SUCCESS_'), 'error' => lang('_ENABLE_FAILED_')]);
                break;
            default :
                $this->error(lang('_PARAMETER_ERROR_'));
                break;
        }
    }

    public function getAddons()
    {
        $color_block = array(0 => '#76cac3', 1 => '#85aecc', 2 => '#eea97f');
        $tag = 'ADMIN_ADDONS_' . is_login();
        $addons = cache($tag);
        if ($addons === false) {
            $addonsModel = new AddonsModel();
            $addons = $addonsModel->getAdminList();
            $i = 0;
            foreach ($addons as &$v) {
                $i++;
                $v['word'] = mb_substr($v['title'], 0, 1, 'utf8');
                $v['color'] = $color_block[$i % 3];

            }

            cache($tag, $addons);
        }
        return $addons;
    }

    /**获取模块列表，用于显示在左侧
     */
    public function getModules()
    {
        $tag = 'ADMIN_MODULES_' . is_login();
        $modules = cache($tag);
        if ($modules === false) {
            $moduleModel = new ModuleModel();
            $modules = $moduleModel->getAll();
            if(!empty($modules)){
                foreach ($modules as $key => &$v) {
                    if($v['menu_hide']==1){
                        unset($modules[$key]);
                        continue;
                    }
                    $rule = strtolower($v['admin_entry']);
                    if ($rule) {
                        $menuModel = new MenuModel();
                        $menus = $menuModel->where(['module' => $v['name'], 'pid' => 0])->find();
                        if ($menus) {
                            $v['children'] = $this->getSubMenus($menus['id']);
                        }
                    }
                }
                cache($tag, $modules);
            }
        }
        return $modules;
    }

    public function getSubMenus($pid)
    {
        $menus = [];
        //生成child树
        $groups = db("menu")->where("pid = {$pid}")->distinct(true)->field("`groups`")->order('sort asc')->select();

        if ($groups) {
            $groups = array_column($groups, 'groups');
        } else {
            $groups = [];
        }
        //获取二级分类的合法url
        $where = [];
        $where['pid'] = $pid;
        $where['hide'] = 0;
        if (!config('DEVELOP_MODE')) { // 是否开发者模式
            $where['is_dev'] = 0;
        }
        $second_urls = db("menu")->where($where)->find();

        if (!IS_ROOT) {
            // 检测菜单权限
            $to_check_urls = [];
            foreach ($second_urls as $key => $to_check_url) {
                if (stripos($to_check_url, Request()->module()) !== 0) {
                    $rule = Request()->module() . '/' . $to_check_url;
                } else {
                    $rule = $to_check_url;
                }
                if ($this->checkRule($rule, AuthRuleModel::RULE_URL, null))
                    $to_check_urls[] = $to_check_url;
            }
        }
        // 按照分组生成子菜单树
        foreach ($groups as $g) {
            $map = ['groups' => $g];
            if (isset($to_check_urls)) {
                if (empty($to_check_urls)) {
                    // 没有任何权限
                    continue;
                } else {
                    $map['url'] = ['in', $to_check_urls];
                }
            }
            $map['pid'] = $pid;
            $map['hide'] = 0;
            if (!config('DEVELOP_MODE')) { // 是否开发者模式
                $map['is_dev'] = 0;
            }
            $menuList = db("menu")->where($map)->field('id,pid,title,url,tip')->order('sort asc')->select();
            $menus[$g] = list_to_tree($menuList, 'id', 'pid', 'operater', $pid);
            if (empty($menus[$g])) {
                unset($menus[$g]);
            }
        }
        return $menus;

    }

    /**
     * 获取控制器菜单数组,二级菜单元素位于一级菜单的'_child'元素中
     * @param string $controller
     * @return bool|mixed
     */
    final public function getMenus($controller = '')
    {
        $menuModel = new MenuModel();
        if(empty($controller)) $controller = Request()->controller();
        $tag = 'ADMIN_MENU_LIST' . is_login() . $controller;
//        $menus = cache($tag);
        $menus = false;
        if ($menus === false) {
            // 获取主菜单
            $where['pid'] = 0;

            if (!config('DEVELOP_MODE')) { // 是否开发者模式
                $where['is_dev'] = 0;
            }
            $menus['main'] = db("menu")->where($where)->order('sort asc')->select();
            foreach ($menus['main'] as &$v) {
                $v['children'] = $this->getSubMenus($v['id']);
                if ($v['url'] == 'Addons/index') {
                    extra_addons_menu($v);
                }
            }
            unset($v);
            $menus['child'] = []; //设置子节点

            //高亮主菜单
            $current = db("menu")->where("url like '{$controller}/" . Request()->action() . "%' OR url like '%/{$controller}/" . Request()->action() . "%'  ")->field('id')->find();

            if ($current) {
                $nav = $menuModel->getPath($current['id']);
                $nav_first_title = $nav[0]['title'];

                foreach ($menus['main'] as $key => $item) {
                    if (!is_array($item) || empty($item['title']) || empty($item['url'])) {
                        $this->error(lang('_CLASS_CONTROLLER_ERROR_PARAM_', ['menus' => $menus]));
                    }
                    if (stripos($item['url'], Request()->module()) !== 0) {
                        $item['url'] = Request()->module() . '/' . $item['url'];
                    }
                    // 判断主菜单权限
                    if (!IS_ROOT && !$this->checkRule($item['url'], AuthRuleModel::RULE_MAIN, null)) {
                        unset($menus['main'][$key]);
                        continue;//继续循环
                    }
                    // 获取当前主菜单的子菜单项
                    if ($item['title'] == $nav_first_title) {
                        $menus['main'][$key]['class'] = 'layui-this';
                        //生成child树
                        $groups = db("menu")->where("pid = {$item['id']}")->distinct(true)->field("`groups`")->order('sort asc')->select();

                        if ($groups) {
                            $groups = array_column($groups, 'groups');
                        } else {
                            $groups = [];
                        }

                        //获取二级分类的合法url
                        $where = [];
                        $where['pid'] = $item['id'];
                        $where['hide'] = 0;
                        if (!config('DEVELOP_MODE')) { // 是否开发者模式
                            $where['is_dev'] = 0;
                        }
                        $second_urls = db("menu")->where($where)->order('sort asc')->select();

                        if (!IS_ROOT) {
                            // 检测菜单权限
                            $to_check_urls = [];
                            foreach ($second_urls as $key => $to_check_url) {
                                if (stripos($to_check_url['url'], Request()->module()) !== 0) {
                                    $rule = Request()->module() . '/' . $to_check_url['url'];
                                } else {
                                    $rule = $to_check_url['url'];
                                }

                                if ($this->checkRule($rule, AuthRuleModel::RULE_URL, null))
                                    $to_check_urls[] = $to_check_url['url'];
                            }
                        }
                        // 按照分组生成子菜单树
                        foreach ($groups as $g) {
                            $map = ['groups' => $g];
                            if (isset($to_check_urls)) {
                                if (empty($to_check_urls)) {
                                    // 没有任何权限
                                    continue;
                                } else {
                                    $map['url'] = ['in', $to_check_urls];
                                }
                            }
                            $map['pid'] = $item['id'];
                            $map['hide'] = 0;
                            if (!config('DEVELOP_MODE')) { // 是否开发者模式
                                $map['is_dev'] = 0;
                            }
                            $menuList = db("menu")->where($map)->field('id,pid,title,url,tip')->order('sort asc')->select();

                            $menus['child'][$g] = list_to_tree($menuList, 'id', 'pid', 'operater', $item['id']);

                        }
                    }

                }
            }
            cache($tag, $menus);
        }
        return $menus;
    }

    /**
     * 返回后台节点数据
     * @param boolean $tree 是否返回多维数组结构(生成菜单时用到),为false返回一维数组(生成权限节点时用到)
     * @retrun array
     *
     * 注意,返回的主菜单节点数组中有'controller'元素,以供区分子节点和主节点
     */
    final protected function returnNodes($tree = true)
    {
        $menu = new MenuModel();
        static $tree_nodes = [];
        if ($tree && !empty($tree_nodes[(int)$tree])) {
            return $tree_nodes[$tree];
        }
        if ((int)$tree) {
            $list = $menu->field('id,pid,title,url,tip,hide')->order('sort asc')->select()->toArray();
            foreach ($list as $key => $value) {
                if (stripos($value['url'], Request()->module()) !== 0) {
                    $list[$key]['url'] = Request()->module() . '/' . $value['url'];
                }
            }
            $nodes = list_to_tree($list, $pk = 'id', $pid = 'pid', $child = 'operator', $root = 0);
            foreach ($nodes as $key => $value) {
                if (!empty($value['operator'])) {
                    $nodes[$key]['child'] = $value['operator'];
                    unset($nodes[$key]['operator']);
                }
            }
        } else {
            $nodes = $menu->field('title,url,tip,pid')->order('sort asc')->select()->toArray();
            foreach ($nodes as $key => $value) {
                if (stripos($value['url'], Request()->module()) !== 0) {
                    $nodes[$key]['url'] = Request()->module() . '/' . $value['url'];
                }
            }
        }
        $tree_nodes[(int)$tree] = $nodes;
        return $nodes;
    }

    /**
     * 通用分页列表数据集获取方法
     *
     *  可以通过url参数传递where条件,例如:  userList.html?name=asdfasdfasdfddds
     *  可以通过url空值排序字段和方式,例如: userList.html?_field=id&_order=asc
     *  可以通过url参数r指定每页数据条数,例如: userList.html?r=5
     *
     * @param sting|Model $model 模型名或模型实例
     * @param array $where where查询条件(优先级: $where>$_REQUEST>模型设定)
     * @param array|string $order 排序条件,传入null时使用sql默认排序或模型属性(优先级最高);
     *                              请求参数中如果指定了_order和_field则据此排序(优先级第二);
     *                              否则使用$order参数(如果$order参数,且模型也没有设定过order,则取主键降序);
     * @param boolean $field 单表模型用不到该参数,要用在多表join时为field()方法指定参数
     *
     * @return array|false
     * 返回数据集
     */
    protected function lists($model, $where = array(), $order = '', $field = true)
    {
        $options = [];
        $REQUEST = $this->request->param();
        if (is_string($model)) {
            $model = model($model);
        }

        $pk = $model->getPk();

        if ($order === null) {
            $options['order'] = $pk . ' desc';
        } else if (isset($REQUEST['_order']) && isset($REQUEST['_field']) && in_array(strtolower($REQUEST['_order']), ['desc', 'asc'])) {
            $options['order'] = '`' . $REQUEST['_field'] . '` ' . $REQUEST['_order'];
        } elseif ($order === '' && empty($options['order']) && !empty($pk)) {
            $options['order'] = $pk . ' desc';
        } elseif ($order) {
            $options['order'] = $order;
        }
        unset($REQUEST['_order'], $REQUEST['_field']);


        $total = $model->where($where)->count();

        if (isset($REQUEST['r'])) {
            $listRows = (int)$REQUEST['r'];
        } else {
            $listRows = config('LIST_ROWS') > 0 ? config('LIST_ROWS') : 10;
        }
        $list = $model->field($field)->where($where)->order($options['order'])->paginate($listRows,false,['query'=>$REQUEST]);
        $page = $list->render();

        $this->assign('_page', $page ? $page : '');
        $this->assign('_total', $total);

        $data_array =['total'=>$total,'listRows'=>$listRows,'page'=>$page ? $page : '','list'=>$list];
        return $data_array;
    }

    public function _empty()
    {
        $this->error(lang('_ERROR_404_2_'));
    }

}