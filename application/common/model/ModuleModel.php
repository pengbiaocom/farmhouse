<?php
namespace app\common\model;


class ModuleModel extends BaseModel
{

    protected $tableName = 'module';
    protected $tokenFile = '/info/token.ini';
    protected $moduleName = '';

    /**获取全部的模块信息
     * @return array|mixed
     */
    public function getAll($is_installed = '')
    {

        $modules = cache('module_all'.$is_installed);
        if ($modules === false) {
            $modules = [];
            $dir = $this->getFile(APP_PATH);
            foreach ($dir as $subdir) {
                if (file_exists(APP_PATH . '/' . $subdir . '/info/info.php') && $subdir != '.' && $subdir != '..') {
                    $info = $this->getModule($subdir);
                    if ($is_installed == 1 && $info['is_setup'] == 0) {
                        continue;
                    }
                    $this->moduleName = $info['name'];
                    //如果token存在的话
                    if (file_exists($this->getRelativePath($this->tokenFile))) {
                        $info['token'] = file_get_contents($this->getRelativePath($this->tokenFile));
                    }
                    $info['auth_role']=explode(',',$info['auth_role']);
                    $modules[] = $info;
                }
            }
            cache('module_all'.$is_installed, $modules);
        }
        return $modules;
    }


    /**
     * 重新通过文件来同步模块
     */
    public function reload()
    {
        $modules = $this->select();
        foreach ($modules as $m) {
            if (file_exists(APP_PATH . '/' . $m['name'] . '/info/info.php')) {
                $info = array_merge($m, $this->getInfo($m['name']));
                $this->isUpdate(true)->where(['id'=>$m['id']])->save($info);
            }
        }
        $this->cleanModulesCache();
    }

    /**重置单个模块信息
     * @param $name
     */
    public function reloadModule($name)
    {
        $module = db("module")->where(['name' => $name])->find();
        if (empty($module)) {
            $this->error = lang('_MODULE_INFORMATION_DOES_NOT_EXIST_WITH_PERIOD_');
            return false;
        } else {
            if (file_exists(APP_PATH . '/' . $module['name'] . '/info/info.php')) {
                $info = array_merge($module, $this->getInfo($module['name']));
                $this->isUpdate(true)->where(['name'=>$name])->save($info);
                $this->cleanModuleCache($name);
                return true;
            }
        }
    }

    /**检查是否可以访问模块，被用于控制器初始化
     * @param $name
     */
    public function checkCanVisit($name)
    {
        check_login_role_authorized($name,true);
    }

    /**
     * 设置禁止访问模块的身份
     * @param $id
     * @param string $auth_role
     * @return bool
     */
    public function setModuleRole($id,$auth_role='')
    {
        if(!$id){
            return false;
        }
        $data['auth_role']=$auth_role;
        $res=$this->isUpdate(true)->where(['id'=>$id])->save($data);
        return $res;
    }

    /**检查模块是否已经安装
     * @param $name
     * @return bool
     */
    public function checkInstalled($name)
    {
        $modules = $this->getAll();

        foreach ($modules as $m) {
            if ($m['name'] == $name && $m['is_setup']) {
                return true;
            }
        }
        return false;
    }

    /**
     * 清理全部模块的缓存
     */
    public function  cleanModulesCache()
    {
        $modules = $this->getAll();

        foreach ($modules as $m) {
            $this->cleanModuleCache($m['name']);
        }
        cache('module_all', null);
        cache('module_all1', null);
        cache('admin_modules', null);
        cache('ALL_MESSAGE_SESSION',null);
        cache('ALL_MESSAGE_TPLS',null);
    }

    /**清理某个模块的缓存
     * @param $name 模块名
     */
    public function cleanModuleCache($name)
    {
        cache('common_module_' . strtolower($name), null);

    }

    /**卸载模块
     * @param $id 模块ID
     * @param int $withoutData 0.不清理数据 1.清理数据
     * @return bool
     */
    public function uninstall($id, $withoutData = 1)
    {
        $module = db("module")->where(['id'=>$id])->find();

        if (!$module || $module['is_setup'] == 0) {
            $this->error = lang('_MODULE_DOES_NOT_EXIST_OR_IS_NOT_INSTALLED_WITH_PERIOD_');
            return false;
        }
        $this->cleanMenus($module['name']);
        $this->cleanAuthRules($module['name']);
        $this->cleanAction($module['name']);
        $this->cleanActionLimit($module['name']);
        if ($withoutData == 0) {
            //如果不保留数据
            if (file_exists(APP_PATH . '/' . $module['name'] . '/info/cleanData.sql')) {
                $uninstallSql = APP_PATH . '/' . $module['name'] . '/info/cleanData.sql';
                $res = $this->executeSqlFile($uninstallSql);
                if ($res === false) {
                    $this->error = lang('_CLEAN_UP_THE_MODULE_DATA_AND_ERROR_MESSAGE_WITH_COLON_') . $res['error_code'];
                    return false;
                }
            }
            //兼容老的卸载方式，执行一边uninstall.sql
            if (file_exists(APP_PATH . '/' . $module['name'] . '/info/uninstall.sql')) {
                $uninstallSql = APP_PATH . '/' . $module['name'] . '/info/uninstall.sql';
                $res =$this->executeSqlFile($uninstallSql);
                if ($res === false) {
                    $this->error = lang('_CLEAN_UP_THE_MODULE_DATA_AND_ERROR_MESSAGE_WITH_COLON_') . $res['error_code'];
                    return false;
                }
            }
        }
        $module['is_setup'] = 0;
        db("module")->where(['id'=>$id])->update($module);

        $this->cleanModulesCache();
        return true;
    }

    /**通过模块名来获取模块信息
     * @param $name 模块名
     * @return array|mixed
     */
    public function getModule($name)
    {
        $module = db("module")->where(['name' => $name])->cache('common_module_' . strtolower($name))->find();
        if ($module === false || $module == null) {
            $m = $this->getInfo($name);
            if ($m != []) {
                if (intval($m['can_uninstall']) == 1) {
                    $m['is_setup'] = 0;//默认设为已安装，防止已安装的模块反复安装。
                } else {
                    $m['is_setup'] = 1;
                }
                $m['id'] = db("module")->insertGetId($m);
                $m['token'] = $this->getToken($m['name']);
                return $m;
            }

        } else {
            $module['token'] = $this->getToken($module['name']);
            return $module;
        }
    }

    /**获取模块的token
     * @param $name 模块名
     * @return string
     */
    public function getToken($name)
    {
        $this->moduleName = $name;
        if (file_exists($this->getRelativePath($this->tokenFile))) {
            $token = file_get_contents($this->getRelativePath($this->tokenFile));
        }
        return $token;
    }

    /**设置模块的token
     * @param $name 模块名
     * @param $token Token
     * @return string
     */
    public function setToken($name, $token)
    {
        $this->moduleName = $name;
        @chmod($this->getRelativePath($this->tokenFile), 0777);
        $result = file_put_contents($this->getRelativePath($this->tokenFile), $token);
        @chmod($this->getRelativePath($this->tokenFile), 0777);
        return $result;
    }

    /**通过ID获取模块信息
     * @param $id
     * @return array|mixed
     */
    public function getModuleById($id)
    {
        $module = db("module")->where(['id' => $id])->find();
        if ($module === false || $module == null) {
            $m = $this->getInfo($module['name']);
            if ($m != []) {
                if ($m['can_uninstall']) {
                    $m['is_setup'] = 0;//默认设为已安装，防止已安装的模块反复安装。
                } else {
                    $m['is_setup'] = 1;
                }
                $m['id'] = db("module")->insertGetId($m);
                $m['token'] = $this->getToken($m['name']);
                return $m;
            }

        } else {
            $module['token'] = $this->getToken($module['name']);
            return $module;
        }
    }


    /**检查某个模块是否已经是安装的状态
     * @param $name
     * @return bool
     */
    public function isInstalled($name)
    {
        $module = $this->getModule($name);
        if ($module['is_setup']) {
            return true;
        } else {
            return false;
        }
    }

    /**安装某个模块
     * @param $id
     * @return bool
     */
    public function install($id)
    {
        $log = '';
        if ($id != 0) {
            $module = db("module")->where(['id'=>$id])->find();
        } else {
            $aName = input('name', '');
            $module = $this->getModule($aName);
        }
        if ($module['is_setup'] == 1) {
            $this->error = lang('_MODULE_INSTALLED_WITH_PERIOD_');
            return false;
        }
        if (file_exists(APP_PATH . '/' . $module['name'] . '/info/guide.json')) {
            //如果存在guide.json
            $guide = file_get_contents(APP_PATH . '/' . $module['name'] . '/info/guide.json');
            $data = json_decode($guide, true);

            //导入菜单项,menu
            $menu = json_decode($data['menu'], true);
            if (!empty($menu)) {
                $this->cleanMenus($module['name']);
                if ($this->addMenus($menu[0]) == true) {
                    $log .= '&nbsp;&nbsp;>菜单成功安装;<br/>';
                }
            }

            //导入前台权限,auth_rule
            $auth_rule = json_decode($data['auth_rule'], true);
            if (!empty($auth_rule)) {
                $this->cleanAuthRules($module['name']);
                if ($this->addAuthRule($auth_rule)) {
                    $log .= '&nbsp;&nbsp;>权限成功导入。<br/>';
                }
                //设置默认的权限
                $default_rule = json_decode($data['default_rule'], true);
                if ($this->addDefaultRule($default_rule, $module['name'])) {
                    $log .= '&nbsp;&nbsp;>默认权限设置成功。<br/>';
                }
            }

            //导入
            $action = json_decode($data['action'], true);
            if (!empty($action)) {
                $this->cleanAction($module['name']);
                if ($this->addAction($action)) {
                    $log .= '&nbsp;&nbsp;>行为成功导入。<br/>';
                }
            }

            $action_limit = json_decode($data['action_limit'], true);
            if (!empty($action_limit)) {
                $this->cleanActionLimit($module['name']);
                if ($this->addActionLimit($action_limit)) {
                    $log .= '&nbsp;&nbsp;>行为限制成功导入。<br/>';
                }
            }

            //seo导入
            $seo = json_decode($data['seo'], true);
            if (!empty($seo)) {
                $this->cleanSeo($module['name']);
                if ($this->addSeo($seo)) {
                    $log .= '&nbsp;&nbsp;>SEO方案成功导入。<br/>';
                }
            }



            if (file_exists(APP_PATH . '/' . $module['name'] . '/info/install.sql')) {
                $install_sql = APP_PATH . '/' . $module['name'] . '/info/install.sql';
                if ($this->executeSqlFile($install_sql) === true) {
                    $log .= '&nbsp;&nbsp;>模块数据添加成功。';
                }
            }
        }

        $module['is_setup'] = 1;
        $module['auth_role']=input('auth_role','','text');


        $rs = db("module")->where(['name'=>$module['name']])->update($module);

        if ($rs === false) {
            $this->error = lang('_MODULE_INFORMATION_MODIFICATION_FAILED_WITH_PERIOD_');
            return false;
        }
        $this->cleanModulesCache();//清除全站缓存
        $this->error = $log;
        return true;
    }



    /*——————————————————————————私有域—————————————————————————————*/

    /**获取模块的相对目录
     * @param $file
     * @return string
     */
    private function getRelativePath($file)
    {
        return APP_PATH . $this->moduleName . $file;
    }

    private function addDefaultRule($default_rule, $module_name)
    {
        foreach ($default_rule as $v) {
            $rule = db('AuthRule')->where(['module' => $module_name, 'name' => $v])->find();
            if ($rule) {
                $default[] = $rule;
            }
        }
        $auth_id = getSubByKey($default, 'id');
        if ($auth_id) {
            $groups = db('AuthGroup')->select();
            foreach ($groups as $g) {
                $old = explode(',', $g['rules']);
                $new = array_merge($old, $auth_id);
                $g['rules'] = implode(',', $new);
                db('AuthGroup')->update($g);
            }
        }
        return true;
    }

    private function addAction($action)
    {
        foreach ($action as $v) {
            unset($v['id']);
            db('Action')->insert($v);
        }
        return true;
    }

    private function addActionLimit($action)
    {
        foreach ($action as $v) {
            unset($v['id']);
            db('ActionLimit')->insert($v);
        }
        return true;
    }

    private function addAuthRule($auth_rule)
    {
        foreach ($auth_rule as $v) {
            unset($v['id']);
            db('AuthRule')->insert($v);
        }
        return true;
    }

    private function cleanActionLimit($module_name)
    {
        $db_prefix = config('database.prefix');
        $sql = "DELETE FROM `{$db_prefix}action_limit` where `module` = '" . $module_name . "'";
        db()->execute($sql);
    }

    private function cleanAction($module_name)
    {
        $db_prefix = config('database.prefix');
        $sql = "DELETE FROM `{$db_prefix}action` where `module` = '" . $module_name . "'";
        db()->execute($sql);
    }

    private function cleanAuthRules($module_name)
    {
        $db_prefix = config('database.prefix');
        $sql = "DELETE FROM `{$db_prefix}auth_rule` where `module` = '" . $module_name . "'";
        db()->execute($sql);
    }

    private function cleanMenus($module_name)
    {
        $db_prefix = config('database.prefix');
        $sql = "DELETE FROM `{$db_prefix}menu` where `url` like '" . $module_name . "/%'";
        db()->execute($sql);
    }

    private function addMenus($menu, $pid = 10018)
    {
        $menu['pid'] = $pid;
        unset($menu['id']);
        $nextmenu = $menu['_'];
        unset($menu['_']);
        $id = db('Menu')->insertGetId($menu);
        $menu['id'] = $id;
        if (!empty($nextmenu))
            foreach ($nextmenu as $v) {
                $this->addMenus($v, $id);
            }
        return true;
    }


    private function getInfo($name)
    {
        if (file_exists(APP_PATH . '/' . $name . '/info/info.php')) {
            $module = require(APP_PATH . '/' . $name . '/info/info.php');
            return $module;
        } else {
            return [];
        }

    }

    /**
     * 获取文件列表
     */
    private function getFile($folder)
    {
        //打开目录
        $fp = opendir($folder);
        //阅读目录
        while (false != $file = readdir($fp)) {
            //列出所有文件并去掉'.'和'..'
            if ($file != '.' && $file != '..') {
                //$file="$folder/$file";
                $file = "$file";

                //赋值给数组
                $arr_file[] = $file;

            }
        }
        //输出结果
        if (is_array($arr_file)) {
            while (list($key, $value) = each($arr_file)) {
                $files[] = $value;
            }
        }
        //关闭目录
        closedir($fp);
        return $files;
    }

    private function cleanSeo($module_name) {
        if($module_name) {
            $map['app'] = strtoupper($module_name) ;
            db('SeoRule')->where($map)->delete() ;
        }
    }

    private function addSeo($seo) {
        foreach ($seo as $v) {
            unset($v['id']);
            db('SeoRule')->insert($v);
        }
        return true;
    }


} 