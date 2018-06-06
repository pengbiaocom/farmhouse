<?php
namespace app\backstage\controller;
use app\common\model\AddonsModel;
use app\common\model\HooksModel;

/**
 * 扩展后台管理页面
 */
class AddonsController extends BackstageController
{

    public function _initialize()
    {
        parent::_initialize();
        $addonsModel = new AddonsModel();
        $this->assign('_extra_menu', [
            lang('_ALREADY_INSTALLED_IN_THE_BACKGROUND_') => $addonsModel->getAdminList(),
        ]);
    }

    //创建向导首页
    public function create()
    {
        if (!is_writable(ONETHINK_ADDON_PATH))
            $this->error(lang('_YOU_DO_NOT_CREATE_A_DIRECTORY_TO_WRITE_PERMISSION_'));

        $hooks = db('Hooks')->field('name,description')->select();
        $this->assign('Hooks', $hooks);
        $this->assign('meta_title',lang('_CREATE_WIZARD_'));
        return $this->fetch('create');
    }

    //预览
    public function preview($output = true)
    {
        $data = $this->request->param();
        $data['info']['status'] = (int)$data['info']['status'];
        $extend = [];
        $custom_config = trim($data['custom_config']);
        if ($data['has_config'] && $custom_config) {
            $custom_config = <<<str


        public \$custom_config = '{$custom_config}';
str;
            $extend[] = $custom_config;
        }

        $admin_list = trim($data['admin_list']);
        if ($data['has_adminlist'] && $admin_list) {
            $admin_list = <<<str


        public \$admin_list = array(
            {$admin_list}
        );
str;
            $extend[] = $admin_list;
        }

        $custom_adminlist = trim($data['custom_adminlist']);
        if ($data['has_adminlist'] && $custom_adminlist) {
            $custom_adminlist = <<<str


        public \$custom_adminlist = '{$custom_adminlist}';
str;
            $extend[] = $custom_adminlist;
        }

        $extend = implode('', $extend);
        $hook = '';
        foreach ($data['hook'] as $value) {
            $hook .= <<<str
        //实现的{$value}钩子方法
        public function {$value}(\$param){

        }

str;
        }

        $tpl = <<<str
<?php

namespace Addons\\{$data['info']['name']};
use app\common\controller\Addon;

/**
 * {$data['info']['title']}插件
 * @author {$data['info']['author']}
 */

    class {$data['info']['name']}Addon extends Addon{

        public \$info = array(
            'name'=>'{$data['info']['name']}',
            'title'=>'{$data['info']['title']}',
            'description'=>'{$data['info']['description']}',
            'status'=>{$data['info']['status']},
            'author'=>'{$data['info']['author']}',
            'version'=>'{$data['info']['version']}'
        );{$extend}

        public function install(){
            return true;
        }

        public function uninstall(){
            return true;
        }

{$hook}
    }
str;
        if ($output)
            exit($tpl);
        else
            return $tpl;
    }

    public function checkForm()
    {
        $data = $this->request->param();
        $data['info']['name'] = trim($data['info']['name']);
        if (!$data['info']['name'])
            $this->error(lang('_PLUGIN_LOGO_MUST_'));
        //检测插件名是否合法
        $addons_dir = ONETHINK_ADDON_PATH;
        if (file_exists("{$addons_dir}{$data['info']['name']}")) {
            $this->error(lang('_PLUGIN_ALREADY_EXISTS_'));
        }
        $this->success(lang('_CAN_CREATE_'));
    }

    public function build()
    {
        $data = $this->request->param();
        $data['info']['name'] = trim($data['info']['name']);
        $addonFile = $this->preview(false);
        $addons_dir = ONETHINK_ADDON_PATH;
        //创建目录结构
        $files = [];
        $addon_dir = "$addons_dir{$data['info']['name']}/";
        $files[] = $addon_dir;
        $addon_name = "{$data['info']['name']}Addon.php";
        $files[] = "{$addon_dir}{$addon_name}";
        if ($data['has_config'] == 1) ;//如果有配置文件
        $files[] = $addon_dir . 'config.php';

        if ($data['has_outurl']) {
            $files[] = "{$addon_dir}controller/";
            $files[] = "{$addon_dir}controller/{$data['info']['name']}Controller.php";
            $files[] = "{$addon_dir}model/";
            $files[] = "{$addon_dir}model/{$data['info']['name']}Model.php";
        }
        $custom_config = trim($data['custom_config']);
        if ($custom_config)
            $data[] = "{$addon_dir}{$custom_config}";

        $custom_adminlist = trim($data['custom_adminlist']);
        if ($custom_adminlist)
            $data[] = "{$addon_dir}{$custom_adminlist}";

        create_dir_or_files($files);

        //写文件
        file_put_contents("{$addon_dir}{$addon_name}", $addonFile);
        if ($data['has_outurl']) {
            $addonController = <<<str
<?php

namespace Addons\\{$data['info']['name']}\Controller;
use app\home\controller\AddonsController;

class {$data['info']['name']}Controller extends AddonsController{

}

str;
            file_put_contents("{$addon_dir}Controller/{$data['info']['name']}Controller.php", $addonController);
            $addonModel = <<<str
<?php

namespace Addons\\{$data['info']['name']}\Model;
use think\Model;

/**
 * {$data['info']['name']}模型
 */
class {$data['info']['name']}Model extends Model{
    public \$model = array(
        'title'=>'',//新增[title]、编辑[title]、删除[title]的提示
        'template_add'=>'',//自定义新增模板自定义html edit.html 会读取插件根目录的模板
        'template_edit'=>'',//自定义编辑模板html
        'search_key'=>'',// 搜索的字段名，默认是title
        'extend'=>1,
    );

    public \$_fields = array(
        'id'=>array(
            'name'=>'id',//字段名
            'title'=>'ID',//显示标题
            'type'=>'num',//字段类型
            'remark'=>'',// 备注，相当于配置里的tip
            'is_show'=>3,// 1-始终显示 2-新增显示 3-编辑显示 0-不显示
            'value'=>0,//默认值
        ),
        'title'=>array(
            'name'=>'title',
            'title'=>lang('_TITLE_'),
            'type'=>'string',
            'remark'=>'',
            'is_show'=>1,
            'value'=>0,
            'is_must'=>1,
        ),
    );
}

str;
            file_put_contents("{$addon_dir}model/{$data['info']['name']}Model.php", $addonModel);
        }

        if ($data['has_config'] == 1)
            file_put_contents("{$addon_dir}config.php", $data['config']);

        $this->success(lang('_CREATE_SUCCESS_'), url('index'));
    }

    /**
     * 插件列表
     */
    public function index()
    {
        $type = $this->request->param('type');
        $addonsModel = new AddonsModel();
        $list = $addonsModel->getList('');
        $request = $this->request->param();

        $listRows = 12;
        if ($type == 'yes') {//已安装的
            foreach ($list as $key => $value) {
                if ($value['uninstall'] != 1) {
                    unset($list[$key]);
                }
            }
        } else if ($type == 'no') {
            foreach ($list as $key => $value) {
                if ($value['uninstall'] == 1) {
                    unset($list[$key]);
                }
            }
        } else {
            $type = 'all';
        }
        $total = $list ? count($list) : 1;
        $this->assign('type', $type);
        $page = new \think\PageBack($total, $listRows, $request);
        $voList = array_slice($list, $page->firstRow, $page->listRows);
        $p = $page->show();
        $this->assign('_list', $voList);
        $this->assign('_page', $p ? $p : '');
        // 记录当前列表页的cookie
        Cookie('__forward__', $_SERVER['REQUEST_URI']);
        $this->assign('meta_title',lang('_PLUGIN_LIST_'));
        return $this->fetch();
    }

    /**
     * 插件后台显示页面
     * @param string $name 插件名
     */
    public function adminList($name)
    {

        // 记录当前列表页的cookie
        Cookie('__forward__', $_SERVER['REQUEST_URI']);
        $class = get_addon_class($name);
        if (!class_exists($class))
            $this->error(lang('_PLUGIN_DOES_NOT_EXIST_'));
        $addon = new $class();
        $this->assign('addon', $addon);
        $param = $addon->admin_list;
        if (!$param)
            $this->error(lang('_THE_PLUGIN_LIST_INFORMATION_IS_NOT_CORRECT_'));
        $this->assign('meta_title',$addon->info['title']);
        extract($param);
        $this->assign('title', $addon->info['title']);
        $this->assign($param);
        if (!isset($fields))
            $fields = '*';
        if (!isset($map))
            $map = [];
        if (isset($model)){
            $objModel = get_Addons_model($model);
            if (class_exists($objModel)) { // 实例化插件失败忽略
                $obj                 = new $objModel;
                $list = $this->lists($obj->field($fields), $map);
            }else{
                $list = [];
            }
        }
        $this->assign('_list', $list['list']);
        if ($addon->custom_adminlist)
            $this->assign('custom_adminlist', $this->fetch($addon->addon_path . $addon->custom_adminlist));
        return $this->fetch();
    }

    /**
     * 启用插件
     */
    public function enable()
    {
        $id =$this->request->param('id');
        $msg = ['success' => lang('_ENABLE_SUCCESS_'), 'error' => lang('_ENABLE_FAILED_')];
        cache('hooks', null);
        $this->resume('Addons', "id={$id}", $msg);
    }

    /**
     * 禁用插件
     */
    public function disable()
    {
        $id =$this->request->param('id');
        $msg = ['success' => lang('_DISABLE_SUCCESS_'), 'error' => lang('_DISABLE_')];
        cache('hooks', null);
        $this->forbid('Addons', "id={$id}", $msg);
    }

    /**
     * 设置插件页面
     */
    public function config()
    {
        $id =intval($this->request->param('id'));
        $addon = db('Addons')->find($id);
        if (!$addon)
            $this->error(lang('_PLUGIN_NOT_INSTALLED_'));
        $addon_class = get_addon_class($addon['name']);
        if (!class_exists($addon_class))
            trace(lang('_FAIL_ADDON_PARAM_',['model'=>$addon['name']]), 'ADDONS', 'ERR');
        $data = new $addon_class;
        $addon['addon_path'] = $data->addon_path;
        $addon['custom_config'] = $data->custom_config;
        $this->assign('meta_title',lang('_ADDONS_SET_') . $data->info['title']);
        $db_config = $addon['config'];
        $addon['config'] = include $data->config_file;
        if ($db_config) {
            $db_config = json_decode($db_config, true);
            foreach ($addon['config'] as $key => $value) {
                if ($value['type'] != 'group') {
                    $addon['config'][$key]['value'] = $db_config[$key];
                } else {
                    foreach ($value['options'] as $gourp => $options) {
                        foreach ($options['options'] as $gkey => $value) {
                            $addon['config'][$key]['options'][$gourp]['options'][$gkey]['value'] = $db_config[$gkey];
                        }
                    }
                }
            }
        }
        $this->assign('data', $addon);
        if ($addon['custom_config'])
            $this->assign('custom_config', $this->fetch($addon['addon_path'] . $addon['custom_config']));
        return $this->fetch();
    }

    /**
     * 保存插件设置
     */
    public function saveConfig()
    {
        $id = intval($this->request->param('id'));
        $config = $this->request->param('config');
        $flag = db('Addons')->where("id={$id}")->setField('config', json_encode($config));
        if (isset($config['addons_cache'])) {//清除缓存
            cache($config['addons_cache'], null);
        }
        if ($flag !== false) {
            $this->success(lang('_SAVE_'), Cookie('__forward__'));
        } else {
            $this->error(lang('_SAVE_FAILED_'));
        }

    }

    /**
     * 安装插件
     */
    public function install()
    {
        $addon_name = trim(input('addon_name'));
        $addonsModel = new AddonsModel();
        $rs = $addonsModel->install($addon_name);
        if ($rs === true) {
            $this->success(lang('_INSTALL_PLUG-IN_SUCCESS_'));
        } else {
            $this->error($addonsModel->getError());
        }
    }

    /**
     * 卸载插件
     */
    public function uninstall()
    {
        $addonsModel = new AddonsModel();
        $id = trim($this->request->param('id'));
        $db_addons = $addonsModel->find($id);
        $class = get_addon_class($db_addons['name']);
        $this->assign('jumpUrl', url('index'));
        if (!$db_addons || !class_exists($class))
            $this->error(lang('_PLUGIN_DOES_NOT_EXIST_'));
        session('addons_uninstall_error', null);
        $addons = new $class;
        $uninstall_flag = $addons->uninstall();
        if (!$uninstall_flag)
            $this->error(lang('_EXECUTE_THE_PLUG-IN_TO_THE_PRE_UNLOAD_OPERATION_FAILED_') . session('addons_uninstall_error'));
        $hooksModel = new HooksModel();
        $hooks_update = $hooksModel->removeHooks($db_addons['name']);
        if ($hooks_update === false) {
            $this->error(lang('_FAILED_HOOK_MOUNTED_DATA_UNINSTALL_PLUG-INS_'));
        }
        cache('hooks', null);
        $delete = $addonsModel->where("name='{$db_addons['name']}'")->delete();
        if ($delete === false) {
            $this->error(lang('_UNINSTALL_PLUG-IN_FAILED_'));
        } else {
            $this->success(lang('_SUCCESS_UNINSTALL_'));
        }
    }

    /**
     * 钩子列表
     */
    public function hooks()
    {
        $map = $fields = [];
        $list = $this->lists(db("Hooks")->field($fields), $map);
        int_to_string($list, ['type' => config('HOOKS_TYPE')]);
        // 记录当前列表页的cookie
        Cookie('__forward__', $_SERVER['REQUEST_URI']);
        $this->assign('list', $list['list']);
        $this->assign('meta_title',lang('_HOOK_LIST_'));
        return $this->fetch();
    }

    public function addhook()
    {
        $this->assign('data', null);
        $this->assign('meta_title',lang('_NEW_HOOK_'));
        return $this->fetch('edithook');
    }

    //钩子出编辑挂载插件页面
    public function edithook($id)
    {
        $hook = db('Hooks')->field(true)->find($id);
        $this->assign('data', $hook);
        $this->assign('meta_title',lang('_EDIT_HOOK_'));
        return $this->fetch('edithook');
    }

    //超级管理员删除钩子
    public function delhook($id)
    {
        if (db('Hooks')->delete($id) !== false) {
            $this->success(lang('_DELETE_SUCCESS_'));
        } else {
            $this->error(lang('_DELETE_FAILED_'));
        }
    }

    public function updateHook()
    {
        $hookModel = new HooksModel();
        $data = $this->request->param();
        if ($data['id']) {
            $flag = $hookModel->allowField(true)->isUpdate(true)->data($data,true)->save();
            if ($flag !== false)
                $this->success(lang('_UPDATE_'), Cookie('__SELF__'));
            else
                $this->error(lang('_UPDATE_FAILED_'));
        } else {
            $flag = $hookModel->allowField(true)->isUpdate(false)->save($data);
            if ($flag)
                $this->success(lang('_NEW_SUCCESS_'), Cookie('__forward__'));
            else
                $this->error(lang('_NEW_FAILURE_'));
        }
    }

    public function execute($_addons = null, $_controller = null, $_action = null)
    {
        if (config('url_case_insensitive')) {
            $_addons = ucfirst(parse_name($_addons, 1));
            $_controller = parse_name($_controller, 1);
        }

        $TMPL_PARSE_STRING = config('view_replace_str');
        $TMPL_PARSE_STRING['__ADDONROOT__'] = __ROOT__ . "/Addons/{$_addons}";
        config('view_replace_str', $TMPL_PARSE_STRING);

        if (!empty($_addons) && !empty($_controller) && !empty($_action)) {

            $objController = get_addons_controller($_addons,$_controller);
            if (class_exists($objController)) {
                $controller  = new $objController;
                $Addons = $controller->$_action();
            }
        } else {
            $this->error(lang('_NO_SPECIFIED_PLUG-IN_NAME,_CONTROLLER_OR_OPERATION_'));
        }
    }

    public function edit($name, $id = 0)
    {
        $this->assign('name', $name);
        $class = get_addon_class($name);
        if (!class_exists($class))
            $this->error(lang('_PLUGIN_DOES_NOT_EXIST_'));
        $addon = new $class();
        $this->assign('addon', $addon);
        $param = $addon->admin_list;
        if (!$param)
            $this->error(lang('_THE_PLUGIN_LIST_INFORMATION_IS_NOT_CORRECT_'));
        extract($param);
        $this->assign('title', $addon->info['title']);
        if (isset($model)) {
            $addonModel = model("Addons://{$name}/{$model}");
            if (!$addonModel)
                $this->error(lang('_MODEL_CANNOT_BE_REAL_'));
            $model = $addonModel->getModel();
            $this->assign('model', $model);
        }
        if ($id) {
            $data = $addonModel->find($id);
            $data || $this->error(lang('_DATA_DOES_NOT_EXIST_'));
            $this->assign('data', $data);
        }

        if (Request()->isPost()) {
            $data = $this->request->param();
            if ($id) {
                $flag = $addonModel->allowField(true)->isUpdate(true)->data($data,true)->save();
                if ($flag !== false)
                    $this->success(lang('_SUCCESS_ADD_PARAM_',['model'=>$model['title']]), Cookie('__forward__'));
                else
                    $this->error($addonModel->getError());
            } else {
                $flag = $addonModel->allowField(true)->save($data);
                if ($flag)
                    $this->success(lang('_FAIL_ADD_PARAM_',['model'=>$model['title']]), Cookie('__forward__'));
            }
            $this->error($addonModel->getError());
        } else {
            $fields = $addonModel->_fields;
            $this->assign('fields', $fields);
            $this->assign('meta_title',$id ? lang('_EDIT_') . $model['title'] : lang('_NEW_') . $model['title']);
            if ($id)
                $template = $model['template_edit'] ? $model['template_edit'] : '';
            else
                $template = $model['template_add'] ? $model['template_add'] : '';
            return $this->fetch($addon->addon_path . $template);
        }
    }

    public function del($id = '', $name)
    {
        $ids  =array_unique($this->request->param('ids'));

        if (empty($ids)) {
            $this->error(lang('_ERROR_DATA_SELECT_'));
        }

        $class = get_addon_class($name);
        if (!class_exists($class))
            $this->error(lang('_PLUGIN_DOES_NOT_EXIST_'));
        $addon = new $class();
        $param = $addon->admin_list;
        if (!$param)
            $this->error(lang('_THE_PLUGIN_LIST_INFORMATION_IS_NOT_CORRECT_'));
        extract($param);
        if (isset($model)) {
            $addonModel = model("Addons://{$name}/{$model}");
            if (!$addonModel)
                $this->error(lang('_MODEL_CANNOT_BE_REAL_'));
        }

        $map = ['id' => ['in', $ids]];
        if ($addonModel->where($map)->delete()) {
            $this->success(lang('_DELETE_SUCCESS_'));
        } else {
            $this->error(lang('_DELETE_FAILED_'));
        }
    }

}
