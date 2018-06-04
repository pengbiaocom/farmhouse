<?php
namespace app\install\controller;
use think\Controller;
use think\Db;

class IndexController extends Controller
{

    public function _initialize()
    {
        if (cms_is_installed()) {
            $this->error('网站已经安装', think_get_root() . '/');
        }
    }

    // 安装首页
    public function index()
    {
        return $this->fetch(":index");
    }

    public function step2()
    {
        $data               = [];
        $data['phpversion'] = @phpversion();
        $data['os']         = PHP_OS;
        $tmp                = function_exists('gd_info') ? gd_info() : [];

        $err = 0;
        if (empty($tmp['GD Version'])) {
            $gd = '<font color=red>[×]Off</font>';
            $err++;
        } else {
            $gd = '<font color=green>[√]On</font> ' . $tmp['GD Version'];
        }

        if (class_exists('pdo')) {
            $data['pdo'] = '<i class="fa fa-check correct"></i> 已开启';
        } else {
            $data['pdo'] = '<i class="fa fa-remove error"></i> 未开启';
            $err++;
        }

        if (extension_loaded('pdo_mysql')) {
            $data['pdo_mysql'] = '<i class="fa fa-check correct"></i> 已开启';
        } else {
            $data['pdo_mysql'] = '<i class="fa fa-remove error"></i> 未开启';
            $err++;
        }

        if (extension_loaded('curl')) {
            $data['curl'] = '<i class="fa fa-check correct"></i> 已开启';
        } else {
            $data['curl'] = '<i class="fa fa-remove error"></i> 未开启';
            $err++;
        }

        if (extension_loaded('gd')) {
            $data['gd'] = '<i class="fa fa-check correct"></i> 已开启';
        } else {
            $data['gd'] = '<i class="fa fa-remove error"></i> 未开启';
            if (function_exists('imagettftext')) {
                $data['gd'] .= '<br><i class="fa fa-remove error"></i> FreeType Support未开启';
            }
            $err++;
        }

        if (extension_loaded('mbstring')) {
            $data['mbstring'] = '<i class="fa fa-check correct"></i> 已开启';
        } else {
            $data['mbstring'] = '<i class="fa fa-remove error"></i> 未开启';
            if (function_exists('imagettftext')) {
                $data['mbstring'] .= '<br><i class="fa fa-remove error"></i> FreeType Support未开启';
            }
            $err++;
        }

        if (extension_loaded('fileinfo')) {
            $data['fileinfo'] = '<i class="fa fa-check correct"></i> 已开启';
        } else {
            $data['fileinfo'] = '<i class="fa fa-remove error"></i> 未开启';
            $err++;
        }

        if (ini_get('file_uploads')) {
            $data['upload_size'] = '<i class="fa fa-check correct"></i> ' . ini_get('upload_max_filesize');
        } else {
            $data['upload_size'] = '<i class="fa fa-remove error"></i> 禁止上传';
        }

        if (function_exists('session_start')) {
            $data['session'] = '<i class="fa fa-check correct"></i> 支持';
        } else {
            $data['session'] = '<i class="fa fa-remove error"></i> 不支持';
            $err++;
        }

        if (version_compare(phpversion(), '5.6.0', '>=') && version_compare(phpversion(), '7.0.0', '<') && ini_get('always_populate_raw_post_data') != -1) {
            $data['always_populate_raw_post_data']          = '<i class="fa fa-remove error"></i> 未关闭';
            $data['show_always_populate_raw_post_data_tip'] = true;
            $err++;
        } else {

            $data['always_populate_raw_post_data'] = '<i class="fa fa-check correct"></i> 已关闭';
        }

        $folders    = [
            realpath(CMS_ROOT . 'data') . DS,
            realpath('./upload') . DS,
        ];
        $newFolders = [];
        foreach ($folders as $dir) {
            $testDir = $dir;
            sp_dir_create($testDir);
            if (sp_testwrite($testDir)) {
                $newFolders[$dir]['w'] = true;
            } else {
                $newFolders[$dir]['w'] = false;
                $err++;
            }
            if (is_readable($testDir)) {
                $newFolders[$dir]['r'] = true;
            } else {
                $newFolders[$dir]['r'] = false;
                $err++;
            }
        }
        $data['folders'] = $newFolders;

        $this->assign($data);
        return $this->fetch(":step2");
    }

    public function step3()
    {
        return $this->fetch(":step3");
    }

    public function step4()
    {
        session(null);
        if ($this->request->isPost()) {
            //创建数据库
            $dbConfig             = [];
            $dbConfig['type']     = "mysql";
            $dbConfig['hostname'] = $this->request->param('dbhost');
            $dbConfig['username'] = $this->request->param('dbuser');
            $dbConfig['password'] = $this->request->param('dbpw');
            $dbConfig['hostport'] = $this->request->param('dbport');
            $dbConfig['charset']  = $this->request->param('dbcharset', 'utf8');
            $db                   = Db::connect($dbConfig);
            $dbName               = $this->request->param('dbname');
            $sql                  = "CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET " . $dbConfig['charset'];
            $db->execute($sql) || $this->error($db->getError());

            $dbConfig['database'] = $dbName;

            $dbConfig['prefix'] = $this->request->param('dbprefix', '', 'trim');

            session('install.db_config', $dbConfig);

            $sql = think_split_sql(APP_PATH . 'install/data/database.sql', $dbConfig['prefix'], $dbConfig['charset']);
            session('install.sql', $sql);

            $this->assign('sql_count', count($sql));

            session('install.error', 0);

            $userLogin = $this->request->param('manager');
            $userPass  = $this->request->param('manager_pwd');
            $repassword = $this->request->param('manager_ckpwd');
            $userEmail = $this->request->param('manager_email');

            session('install.admin_info', [
                'username' => $userLogin,
                'password'  => $userPass,
                'repassword'=>$repassword,
                'email' => $userEmail
            ]);

            return $this->fetch(":step4");

        } else {
            exit;
        }
    }

    public function install()
    {
        $dbConfig = session('install.db_config');
        $sql      = session('install.sql');

        if (empty($dbConfig) || empty($sql)) {
            $this->error("非法安装!");
        }

        $sqlIndex = $this->request->param('sql_index', 0, 'intval');

        $db = Db::connect($dbConfig);

        if ($sqlIndex >= count($sql)) {
            $installError = session('install.error');
            $this->success("安装完成!", '', ['done' => 1, 'error' => $installError]);
        }

        $sqlToExec = $sql[$sqlIndex] . ';';

        $result = sp_execute_sql($db, $sqlToExec);

        if (!empty($result['error'])) {
            $installError = session('install.error');
            $installError = empty($installError) ? 0 : $installError;

            session('install.error', $installError + 1);
            $this->error($result['message'], '', [
                'sql'       => $sqlToExec,
                'exception' => $result['exception']
            ]);
        } else {
            $this->success($result['message'], '', [
                'sql' => $sqlToExec
            ]);
        }

    }

    public function setDbConfig()
    {
        $dbConfig = session('install.db_config');

        $dbConfig['authcode'] = create_rand(18);

        $result = sp_create_db_config($dbConfig);

        if ($result) {
            $this->success("数据配置文件写入成功!");
        } else {
            $this->error("数据配置文件写入失败!");
        }
    }

    public function setSite()
    {
        $dbConfig = session('install.db_config');

        if (empty($dbConfig)) {
            $this->error("非法安装!");
        }

        try {
            $admin = session('install.admin_info');
            $db = Db::connect($dbConfig);
            register_administrator($db, $dbConfig['prefix'], $admin, UC_AUTH_KEY);
        } catch (\Exception $e) {
            $this->error("网站创建失败!");
        }

        session("install.step", 4);
        $this->success("网站创建完成!");

    }

    public function step5()
    {
        if (session("install.step") == 4) {
            @touch(CMS_ROOT . 'data/install.lock');
            return $this->fetch(":step5");
        } else {
            $this->error("非法安装！");
        }
    }

    public function testDbPwd()
    {
        if ($this->request->isPost()) {
            $dbConfig         = $this->request->param();
            $dbConfig['type'] = "mysql";

            try {
                Db::connect($dbConfig)->query("SELECT VERSION();");
            } catch (\Exception $e) {
                die("");
            }
            exit("1");
        } else {
            exit("need post!");
        }

    }

}

