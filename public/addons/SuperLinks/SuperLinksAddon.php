<?php
namespace addons\SuperLinks;

use app\common\controller\Addon;
use Think\Db;

/**
 * 合作单位插件
 * @author 苏南
 */
class SuperLinksAddon extends Addon
{
    public $info = [
        'name' => 'SuperLinks',
        'title' => '友情链接',
        'description' => '友情链接，网站底部。',
        'status' => 1,
        'author' => 'tours28',
        'version' => '1.0.0'
    ];
    public $addon_path =  './addons/SuperLinks/';

    public $admin_list = [
        'listKey' => [
            'title' => '站点名称',
            'link'=>'链接地址',
            'type_text' => '类型',
            'status_text' => '显示状态',
            'level' => '优先级',
            'create_time' => '开始时间',
        ],
        'model' => 'SuperLinks',
        'order' => 'level desc,id asc'
    ];
    public $custom_adminlist = 'adminlist.html';

    public function install()
    {
        $db_config = [];
        $db_config['type'] = config('database.type');
        $db_config['hostname'] = config('database.hostname');
        $db_config['database'] = config('database.database');
        $db_config['username'] = config('database.username');
        $db_config['password'] = config('database.password');
        $db_config['hostport'] = config('database.hostport');
        $db_config['prefix'] = config('database.prefix');
        $db = Db::connect($db_config);
        //读取插件sql文件
        $sqldata = file_get_contents('http://' . $_SERVER['HTTP_HOST'] . think_get_root() . '/addons/' . $this->info['name'] . '/install.sql');
        $sqlFormat = $this->sql_split($sqldata, $db_config['prefix']);

        $counts = count($sqlFormat);
        for ($i = 0; $i < $counts; $i++) {
            $sql = trim($sqlFormat[$i]);
            if (strstr($sql, 'CREATE TABLE')) {
                preg_match('/CREATE TABLE `([^ ]*)`/', $sql, $matches);
                mysql_query("DROP TABLE IF EXISTS `$matches[1]");
                $db->execute($sql);
            }
        }
        return true;
    }

    public function uninstall()
    {
        $db_config = [];
        $db_config['type'] = config('database.type');
        $db_config['hostname'] = config('database.hostname');
        $db_config['database'] = config('database.database');
        $db_config['username'] = config('database.username');
        $db_config['password'] = config('database.password');
        $db_config['hostport'] = config('database.hostport');
        $db_config['prefix'] = config('database.prefix');
        $db = Db::connect($db_config);
        //读取插件sql文件
        $sqldata = file_get_contents('http://' . $_SERVER['HTTP_HOST'] . think_get_root() . '/addons/' . $this->info['name'] . '/uninstall.sql');
        $sqlFormat = $this->sql_split($sqldata, $db_config['prefix']);
        $counts = count($sqlFormat);

        for ($i = 0; $i < $counts; $i++) {
            $sql = trim($sqlFormat[$i]);
            $db->execute($sql); //执行语句
        }
        return true;
    }

    public function pageFooter()
    {

    }

    //实现的pageFooter钩子方法
    public function friendLink($param)
    {
        $objModel = get_Addons_model('SuperLinks');
        $obj                 = new $objModel;
        $list = $obj->linkList();
        $this->assign('list', $list);
        $this->assign('link', $param);
        return $this->fetch('widget');

    }

    /**
     * 解析数据库语句函数
     * @param string $sql sql语句   带默认前缀的
     * @param string $tablepre 自己的前缀
     * @return multitype:string 返回最终需要的sql语句
     */
    public function sql_split($sql, $tablepre)
    {

        if ($tablepre != "onethink_")
            $sql = str_replace("onethink_", $tablepre, $sql);
        $sql = preg_replace("/TYPE=(InnoDB|MyISAM|MEMORY)( DEFAULT CHARSET=[^; ]+)?/", "ENGINE=\\1 DEFAULT CHARSET=utf8", $sql);

        if ($r_tablepre != $s_tablepre)
            $sql = str_replace($s_tablepre, $r_tablepre, $sql);
        $sql = str_replace("\r", "\n", $sql);
        $ret = [];
        $num = 0;
        $queriesarray = explode(";\n", trim($sql));
        unset($sql);
        foreach ($queriesarray as $query) {
            $ret[$num] = '';
            $queries = explode("\n", trim($query));
            $queries = array_filter($queries);
            foreach ($queries as $query) {
                $str1 = substr($query, 0, 1);
                if ($str1 != '#' && $str1 != '-')
                    $ret[$num] .= $query;
            }
            $num++;
        }
        return $ret;
    }
}