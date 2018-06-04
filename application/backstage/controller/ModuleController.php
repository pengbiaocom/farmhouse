<?php
namespace app\backstage\controller;
use app\backstage\builder\BackstageConfigBuilder;
use app\common\model\ModuleModel;
use app\common\model\RoleModel;

class ModuleController extends BackstageController
{

    public function lists()
    {
        $aType = input('type', 'installed', 'text');
        $this->assign('type', $aType);

         $moduleModel = new ModuleModel();
        /*刷新模块列表时清空缓存*/
        $aRefresh = input('refresh', 0, 'intval');

        if ($aRefresh == 1) {
            cache('admin_modules', null);
            $moduleModel->reload();
            cache('admin_modules', null);
        } else if ($aRefresh == 2) {
            cache('admin_modules', null);
            $moduleModel->cleanModulesCache();
        }

        /*刷新模块列表时清空缓存 end*/
        $modules = cache('admin_modules');
        if ($modules === false) {
            $modules = $moduleModel->getAll();
            cache('admin_modules', $modules);
        }

        foreach ($modules as $key => $m) {
            switch ($aType) {
                case 'all':
                    break;
                case 'installed':
                    if ($m['can_uninstall'] && $m['is_setup']) {
                    } else unset($modules[$key]);
                    break;
                case 'uninstalled':
                    if ($m['can_uninstall'] && $m['is_setup'] == 0) {
                    } else unset($modules[$key]);
                    break;
                case 'core':
                    if ($m['can_uninstall'] == 0) {
                    } else unset($modules[$key]);
                    break;
            }
        }
        unset($m);
        // dump($modules);exit;
        $this->assign('modules', $modules);
        $this->assign('meta_title',lang('_MODULE_MANAGEMENT_'));
        return $this->fetch();
    }

    /**
     * 编辑模块
     */
    public function edit()
    {
        $moduleModel = new ModuleModel();
        if (Request()->isPost()) {
            $aName = input('name', '', 'text');
            $module['id'] = input('id', 0, 'intval');
            $module['name'] = empty($aName) ? $this->error(lang('_MODULE_NAME_CAN_NOT_BE_EMPTY_')) : $aName;
            $aAlias = input('alias', '', 'text');
            $module['alias'] = empty($aAlias) ? $this->error(lang('_MODULE_CHINESE_NAME_CAN_NOT_BE_EMPTY_')) : $aAlias;
            $aIcon = input('icon', '', 'text');
            $module['icon'] = empty($aIcon) ? $this->error(lang('_ICONS_CANT_BE_EMPTY_')) : $aIcon;
            $aSummary = input('summary', '', 'text');
            $module['summary'] = empty($aSummary) ? $this->error(lang('_THE_INTRODUCTION_CAN_NOT_BE_EMPTY_')) : $aSummary;
            $module['title'] = input('name', '', 'text');
            $module['menu_hide'] = input('menu_hide', 0, 'intval');
            $aToken = input('token', '', 'text');
            $module['auth_role']=input('auth_role','','text');
            $aToken = trim($aToken);
            if ($aToken != '') {
                if ($moduleModel->setToken($module['name'], $aToken)) {
                    $tokenStr = lang('_TOKEN_WRITE_SUCCESS_');
                } else {
                    $tokenStr = lang('_TOKEN_WRITE_FAILURE_');
                }

            }

            if ($moduleModel->save($module) === false) {
                $this->error(lang('_EDIT_MODULE_FAILED_') . $tokenStr);
            } else {
                $moduleModel->cleanModuleCache($aName);
                $moduleModel->cleanModulesCache();
                $this->success(lang('_EDIT_MODULE_') . $tokenStr);
            }
        } else {
            $aName = input('name', '', 'text');
            $module = $moduleModel->getModule($aName);
            $module['token'] = $moduleModel->getToken($module['name']);
            !isset($module['menu_hide']) && $module['menu_hide'] = 0;
            $roleModel = new RoleModel();
            $role_list = $roleModel->selectByMap(['status' => 1]);
            $auth_role_array=array_combine(array_column($role_list,'id'),array_column($role_list,'title'));
            $this->assign('role_list', $role_list);

            $builder = new BackstageConfigBuilder();
            $builder->title(lang('_MODULE_EDIT_') . $module['alias']);
            $builder->keyId()->keyReadOnly('name', lang('_MODULE_NAME_'))->keyText('alias', lang('_MODULE_CHINESE_NAME_'))->keyReadOnly('version', lang('_VERSION_'))
                ->keyText('icon', lang('_ICON_'))
                ->keyTextArea('summary', lang('_MODULE_INTRODUCTION_'))
                ->keyReadOnly('developer', lang('_DEVELOPER_'))
                ->keyText('entry', lang('_FRONT_ENTRANCE_'))
                ->keyText('admin_entry', lang('_BACKGROUND_ENTRY_'))
                ->keyRadio('menu_hide', '管理入口是否隐藏', '默认隐藏', [0 => '不隐藏', 1 => '隐藏'])
                ->keyText('token', lang('_MODULE_KEY_TOKEN_'), lang('_MODULE_KEY_TOKEN_VICE_'))
                ->keyCheckBox('auth_role', '允许身份前台访问', '都不选表示非登录状态也可访问', $auth_role_array);

            $builder->data($module);
            return  $builder->buttonSubmit()->buttonBack()->show();
        }

    }

    public function uninstall()
    {
        $aId = input('id', 0, 'intval');
        $aNav = input('remove_nav', 0, 'intval');
        $moduleModel = new ModuleModel();

        $module = $moduleModel->getModuleById($aId);

        if (Request()->isPost()) {
            $aWithoutData = input('withoutData', 1, 'intval');//是否保留数据
            $res = $moduleModel->uninstall($aId, $aWithoutData);
            if ($res == true) {
                if (file_exists(APP_PATH . '/' . $module['name'] . '/info/uninstall.php')) {
                    require_once(APP_PATH . '/' . $module['name'] . '/info/uninstall.php');
                }
                if ($aNav) {
                    db('Channel')->where(['url' => $module['entry']])->delete();
                    cache('common_nav', null);
                }
                cache('admin_modules', null);
                $this->success(lang('_THE_SUCCESS_OF_THE_UNLOADING_MODULE_'), url('lists'));
            } else {
                $this->error(lang('_FAILURE_OF_THE_UNLOADING_MODULE_') . $moduleModel->getError());
            }

        }


        $builder = new BackstageConfigBuilder();
        $builder->title($module['alias'] . lang('_DASH_') . lang('_UNLOADING_MODULE_'));
        $module['remove_nav'] = 1;
        $builder->keyReadOnly('id', lang('_MODULE_NUMBER_'));
        $builder->suggest('<span class="text-danger">' . lang('_OPERATE_CAUTION_') . '</span>');
        $builder->keyReadOnly('alias', lang('_UNINSTALL_MODULE_'));
        $builder->keyBool('withoutData', lang('_KEEP_DATA_MODULE_') . '?', lang('_DEFAULT_RESERVATION_MODULE_DATA_'))->keyBool('remove_nav', lang('_REMOVE_NAVIGATION_'), lang('_UNINSTALL_AUTO_UNINSTALL_MENU_', ['link' => url('channel/index')]));

        $module['withoutData'] = 1;
        $builder->data($module);
        $builder->buttonSubmit();
        $builder->buttonBack();
        return $builder->show();
    }


    public function install()
    {
        $aName = input('get.name', '', 'text');
        $aNav = input('add_nav', 0, 'intval');

        $moduleModel = new ModuleModel();

        $module = $moduleModel->getModule($aName);

        if (Request()->isPost()) {
            //执行guide中的内容
            $res = $moduleModel->install($module['id']);

            if ($res === true) {
                if ($aNav) {
                    $channel['title'] = $module['alias'];
                    $channel['url'] = $module['entry'];
                    $channel['sort'] = 100;
                    $channel['status'] = 1;
                    $channel['icon'] = $module['icon'];
                    db('Channel')->insert($channel);
                    cache('common_nav', null);
                }
                cache('ADMIN_MODULES_' . is_login(), null);
                $this->success(lang('_INSTALLATION_MODULE_SUCCESS_'), url('lists'));
            } else {
                $this->error(lang('_SETUP_MODULE_FAILED_') . $moduleModel->getError());
            }

        } else {
            $roleModel = new RoleModel();
            $role_list = $roleModel->selectByMap(['status' => 1]);
            $auth_role_array=array_combine(array_column($role_list,'id'),array_column($role_list,'title'));
            $this->assign('role_list', $role_list);

            $builder = new BackstageConfigBuilder();

            $builder->title($module['alias'] . lang('_DASH_') . lang('_GUIDE_MODULE_INSTALL_'));

            $builder->keyId()->keyReadOnly('name', lang('_MODULE_NAME_'))->keyText('alias', lang('_MODULE_CHINESE_NAME_'))->keyReadOnly('version', lang('_VERSION_'))
                ->keyText('icon', lang('_ICON_'))
                ->keyTextArea('summary', lang('_MODULE_INTRODUCTION_'))
                ->keyReadOnly('developer', lang('_DEVELOPER_'))
                ->keyText('entry', lang('_FRONT_ENTRANCE_'))
                ->keyText('admin_entry', lang('_BACKGROUND_ENTRY_'))
                ->keyCheckBox('auth_role', '允许身份前台访问', '都不选表示非登录状态也可访问', $auth_role_array);

            $builder->keyRadio('mode', lang('_INSTALLATION_MODE_'), '', ['install' => lang('_COVER_INSTALLATION_MODE_')]);
            if ($module['entry']) {
                $builder->keyBool('add_nav', lang('_ADD_NAVIGATION_'), lang('_INSTALL_AUTO_ADD_MENU_', ['link' => url('channel/index')]));
            }

            $builder->group(lang('_INSTALL_OPTION_'), 'mode,add_nav,auth_role');

            $module['mode'] = 'install';
            $module['add_nav'] = '1';
            $builder->data($module);
            $builder->buttonSubmit();
            $builder->buttonBack();
            return $builder->show();
        }

    }

} 