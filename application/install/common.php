<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 老猫 <thinkcmf@126.com>
// +----------------------------------------------------------------------
function sp_testwrite($d)
{
    $tfile = "_test.txt";
    $fp    = @fopen($d . "/" . $tfile, "w");
    if (!$fp) {
        return false;
    }
    fclose($fp);
    $rs = @unlink($d . "/" . $tfile);
    if ($rs) {
        return true;
    }
    return false;
}

function sp_dir_create($path, $mode = 0777)
{
    if (is_dir($path))
        return true;
    $ftp_enable = 0;
    $path       = sp_dir_path($path);
    $temp       = explode('/', $path);
    $cur_dir    = '';
    $max        = count($temp) - 1;
    for ($i = 0; $i < $max; $i++) {
        $cur_dir .= $temp[$i] . '/';
        if (@is_dir($cur_dir))
            continue;
        @mkdir($cur_dir, 0777, true);
        @chmod($cur_dir, 0777);
    }
    return is_dir($path);
}

function sp_dir_path($path)
{
    $path = str_replace('\\', '/', $path);
    if (substr($path, -1) != '/')
        $path = $path . '/';
    return $path;
}

function sp_execute_sql($db, $sql)
{
    $sql = trim($sql);
    preg_match('/CREATE TABLE .+ `([^ ]*)`/', $sql, $matches);
    if ($matches) {
        $table_name = $matches[1];
        $msg        = "创建数据表{$table_name}";
        try {
            $db->execute($sql);
            return [
                'error'   => 0,
                'message' => $msg . ' 成功！'
            ];
        } catch (\Exception $e) {
            return [
                'error'     => 1,
                'message'   => $msg . ' 失败！',
                'exception' => $e->getTraceAsString()
            ];
        }

    } else {
        try {
            $db->execute($sql);
            return [
                'error'   => 0,
                'message' => 'SQL执行成功!'
            ];
        } catch (\Exception $e) {
            return [
                'error'     => 1,
                'message'   => 'SQL执行失败！',
                'exception' => $e->getTraceAsString()
            ];
        }
    }
}

/**
 * 显示提示信息
 * @param  string $msg 提示信息
 */
function sp_show_msg($msg, $class = '')
{
    echo "<script type=\"text/javascript\">showmsg(\"{$msg}\", \"{$class}\")</script>";
    flush();
    ob_flush();
}

function register_administrator($db, $prefix, $admin, $auth)
{
    try {
        $uid = 1;
        /*插入用户*/
        $sql = <<<sql
REPLACE INTO `[PREFIX]ucenter_member` (`id`, `username`, `password`, `email`, `mobile`, `reg_time`, `reg_ip`, `last_login_time`, `last_login_ip`, `update_time`, `status`, `type`) VALUES
('[UID]', '[NAME]', '[PASS]','[EMAIL]', '', '[TIME]', '[IP]', '[TIME]', '[IP]',  '[TIME]', 1, 1);
sql;

        $password = think_ucenter_md5($admin['password'],$auth);
        $sql = str_replace(
            array('[PREFIX]', '[NAME]', '[PASS]', '[EMAIL]', '[TIME]', '[IP]', '[UID]'),
            array($prefix, $admin['username'], $password, $admin['email'], time(), get_client_ip(1), $uid),
            $sql);
        //执行sql
        $db->execute($sql);

        /*插入用户资料*/
        $sql = <<<sql
REPLACE INTO `[PREFIX]member` (`uid`, `nickname`, `sex`, `birthday`, `qq`, `login`, `reg_ip`, `reg_time`, `last_login_ip`, `last_login_role`, `show_role`, `last_login_time`, `status`, `signature`) VALUES
('[UID]','[NAME]', 0,  '0', '', 1, 0, '[TIME]', 0, 1, 1, '[TIME]', 1, '');
sql;

        $sql = str_replace(
            array('[PREFIX]', '[NAME]', '[TIME]', '[UID]'),
            array($prefix, $admin['username'], time(), $uid),
            $sql);

        $db->execute($sql);
    } catch (\Exception $e) {

        return false;

    }

    return true;
}


function sp_create_db_config($config)
{
    if (is_array($config)) {
        //读取配置内容
        $conf = file_get_contents(APP_PATH . 'install/data/config.php');

        //替换配置项
        foreach ($config as $key => $value) {
            $conf = str_replace("#{$key}#", $value, $conf);
        }

        try {
            $confDir = CMS_ROOT . 'data/conf/';
            if (!file_exists($confDir)) {
                mkdir($confDir, 0777, true);
            }
            file_put_contents(CMS_ROOT . 'data/conf/database.php', $conf);
        } catch (\Exception $e) {

            return false;

        }

        return true;

    }
}
