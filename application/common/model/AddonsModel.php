<?php
namespace app\common\model;

/**
 * 插件模型
 */
class AddonsModel extends BaseModel
{

    /**
     * 查找后置操作
     */
    protected function _after_find(&$result, $options)
    {

    }

    protected function _after_select(&$result, $options)
    {

        foreach ($result as &$record) {
            $this->_after_find($record, $options);
        }
    }

    public function install($name)
    {

        $class = get_addon_class($name);
        if (!class_exists($class)) {
            $this->error = lang('_PLUGIN_DOES_NOT_EXIST_');
            return false;
        }
        $addons = new $class;
        $info = $addons->info;
        if (!$info || !$addons->checkInfo())//检测信息的正确性
        {
            $this->error = lang('_PLUGIN_INFORMATION_MISSING_');
            return false;
        }
        session('addons_install_error', null);
        $install_flag = $addons->install();
        if (!$install_flag) {
            $this->error = lang('_PERFORM_A_PLUG__IN__OPERATION_FAILED_') . session('addons_install_error');
            return false;
        }

        $data = $info;

        if ((is_array($addons->admin_list) && $addons->admin_list !== array()) || method_exists(controller('Addons://Mail/Admin'), 'buildList')) {
            $data['has_adminlist'] = 1;
            cache('addons_menu_list',null);
        } else {
            $data['has_adminlist'] = 0;
        }

        if ($this->allowField(true)->save($data)) {
            $config = array('config' => json_encode($addons->getConfig()));
            $this->allowField(true)->save($config,"name='{$name}'");
            $hooksModel = new HooksModel();
            $hooks_update = $hooksModel->updateHooks($name);
            if ($hooks_update) {
                cache('hooks', null);
                return true;
            } else {
                $this->where("name='{$name}'")->delete();
                $this->error = lang('_THE_UPDATE_HOOK_IS_FAILED_PLEASE_TRY_TO_REINSTALL_');
                return false;
            }

        } else {
            $this->error = lang('_WRITE_PLUGIN_DATA_FAILED_');
            return false;
        }
    }

    protected $auto = ['create_time'];

    protected function setCreateTimeAttr(){
        return time();
    }

    /**
     * 获取插件列表
     * @param string $addon_dir
     */
    public function getList($addon_dir = '')
    {
        if (!$addon_dir)
            $addon_dir = ONETHINK_ADDON_PATH;

        $dirs = array_map('basename', glob($addon_dir . '*', GLOB_ONLYDIR));

        if ($dirs === FALSE || !file_exists($addon_dir)) {
            $this->error = lang('_THE_PLUGIN_DIRECTORY_IS_NOT_READABLE_OR_NOT_');
            return FALSE;
        }
        $addons = [];
        $where['name'] = ['in', $dirs];
        $list = $this->where($where)->field(true)->select();
        foreach ($list as $addon) {
            $addon['uninstall'] = 0;
            $addons[$addon['name']] = $addon;
        }
        foreach ($dirs as $value) {

            if (!isset($addons[$value])) {
                $class = get_addon_class($value);
                if (!class_exists($class)) { // 实例化插件失败忽略执行
                    \think\Log::record(lang('_PLUGIN_') . $value . lang('_THE_ENTRY_FILE_DOES_NOT_EXIST_WITH_EXCLAMATION_'));
                    continue;
                }
                $obj = new $class;
                $addons[$value] = $obj->info;
                if ($addons[$value]) {
                    $addons[$value]['uninstall'] = 1;
                    unset($addons[$value]['status']);
                }
            }
        }
        //dump($list);exit;
        int_to_string($addons, ['status' => [-1 => lang('_DAMAGE_'), 0 => lang('_DISABLE_'), 1 => lang('_ENABLE_'), null => lang('_NOT_INSTALLED_')]]);
        $addons = list_sort_by($addons, 'uninstall', 'desc');
        return $addons;
    }

    /**
     * 获取插件的后台列表
     */
    public function getAdminList()
    {
        $admin=cache('addons_menu_list');
        if($admin===false){
            $admin = [];
            $db_addons = $this->where("status=1 AND has_adminlist=1")->field('title,name')->select();
            if ($db_addons) {
                foreach ($db_addons as $value) {
                    $admin[] =  ['title' => $value['title'], 'url' => "Addons/adminList?name={$value['name']}"];
                }
            }
            cache('addons_menu_list',$admin);
        }
        return $admin;
    }
}
