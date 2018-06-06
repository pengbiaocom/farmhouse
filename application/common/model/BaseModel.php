<?php
namespace app\common\model;

use think\Model;
use think\Db;
use think\Validate;

class BaseModel extends Model{
    protected $error = 0;

    protected $table;

    protected $rule = [];

    protected $msg = [];

    protected $Validate;

    protected $resultSetType = 'collection';

    public function __construct($data = []){
        parent::__construct($data);
        $this->Validate = new Validate($this->rule, $this->msg);
        $this->Validate->extend('no_html_parse', function ($value, $rule) {
            return true;
        });
    }

    /**
     * 获取空模型
     */
    public function getEModel($tables)
    {
        $rs = Db::query('show columns FROM `' . config('database.prefix') . $tables . "`");
        $obj = [];
        if ($rs) {
            foreach ($rs as $key => $v) {
                $obj[$v['Field']] = $v['Default'];
                if ($v['Key'] == 'PRI')
                    $obj[$v['Field']] = 0;
            }
        }
        return $obj;
    }

    public function save($data = [], $where = [], $sequence = null){
        $data = $this->htmlClear($data);
        $retval = parent::save($data, $where, $sequence);
        if(!empty($where))
        {
            //表示更新数据
            if($retval == 0)
            {
                if($retval !== false)
                {
                    $retval = 1;
                }
            }
        }
        return $retval;
    }

    public function ihtmlspecialchars($string) {
        if(is_array($string)) {
            foreach($string as $key => $val) {
                $string[$key] = $this->ihtmlspecialchars($val);
            }
        } else {
            $string = preg_replace('/&amp;((#(d{3,5}|x[a-fa-f0-9]{4})|[a-za-z][a-z0-9]{2,5});)/', '&\1',
                str_replace(['&', '"', '<', '>'], ['&amp;', '&quot;', '&lt;', '&gt;'], $string));
        }
        return $string;
    }

    protected function htmlClear($data){
        $rule =  $this->rule;
        $info = empty($rule) ? $this->Validate : $rule;
        foreach ($data as $k=>$v){
            if (!empty($info)) {
                if (is_array($info)) {
                    $is_Specialchars=$this->is_Specialchars($info, $k);
                    // 数据对象赋值
                    if($is_Specialchars){
                        $data[$k] = $this->ihtmlspecialchars($v);
                    }else{
                        $data[$k] = $v;
                    }
                } else {
                    ;
                }
            }
        }
        return $data;
    }

    /**
     * 判断当前k 是否在数组的k值中
     * @param unknown $rule
     * @param unknown $k
     */
    protected function is_Specialchars($rule, $k){
        $is_have=true;
        foreach ($rule as $key => $value) {
            if($key==$k){
                if(strcasecmp($value,"no_html_parse")!= 0){
                    $is_have=true;
                }else{
                    $is_have=false;
                }
            }
        }
        return $is_have;
    }

    /**
     * 数据库开启事务
     */
    public function startTrans()
    {
        Db::startTrans();
    }

    /**
     * 数据库事务提交
     */
    public function commit()
    {
        Db::commit();
    }

    /**
     * 数据库事务回滚
     */
    public function rollback()
    {
        Db::rollback();
    }

    /**
     * 列表查询
     *
     * @param unknown $page_index
     * @param number $page_size
     * @param string $order
     * @param string $where
     * @param string $field
     */
    public function pageQuery($page_index, $page_size, $condition, $order, $field)
    {
        $count = $this->where($condition)->count();
        if ($page_size == 0) {
            $list = $this->field($field)
                ->where($condition)
                ->order($order)
                ->select();
            $page_count = 1;
        } else {
            $start_row = $page_size * ($page_index - 1);
            $list = $this->field($field)
                ->where($condition)
                ->order($order)
                ->limit($start_row . "," . $page_size)
                ->select();
            if ($count % $page_size == 0) {
                $page_count = $count / $page_size;
            } else {
                $page_count = (int) ($count / $page_size) + 1;
            }
        }
        return array(
            'data' => $list,
            'total_count' => $count,
            'page_count' => $page_count
        );
    }

    /**
     * 获取关联查询列表
     *
     * @param unknown $viewObj
     *            对应view对象
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return multitype:number unknown
     */
    public function viewPageQuery($viewObj, $page_index, $page_size, $condition, $order)
    {
        if ($page_size == 0) {
            $list = $viewObj->where($condition)
                ->order($order)
                ->select();
        } else {
            $start_row = $page_size * ($page_index - 1);

            $list = $viewObj->where($condition)
                ->order($order)
                ->limit($start_row . "," . $page_size)
                ->select();
        }
        return $list;
    }

    /**
     * 获取关联查询数量
     *
     * @param unknown $viewObj
     *            视图对象
     * @param unknown $condition
     *            下旬条件
     * @return unknown
     */
    public function viewCount($viewObj, $condition)
    {
        $count = $viewObj->where($condition)->count();
        return $count;
    }

    /**
     * 设置关联查询返回数据格式
     *
     * @param unknown $list
     *            查询数据列表
     * @param unknown $count
     *            查询数据数量
     * @param unknown $page_size
     *            每页显示条数
     * @return multitype:unknown number
     */
    public function setReturnList($list, $count, $page_size)
    {
        if($page_size == 0)
        {
            $page_count = 1;
        }else{
            if ($count % $page_size == 0) {
                $page_count = $count / $page_size;
            } else {
                $page_count = (int) ($count / $page_size) + 1;
            }
        }
        return array(
            'data' => $list,
            'total_count' => $count,
            'page_count' => $page_count
        );
    }

    /**
     * 获取单条记录的基本信息
     * @param string $condition
     * @param string $field
     * @return array|false|\PDOStatement|string|Model
     */
    public function getRow($condition='',$field='*'){
        $info = Db::table($this->table)->where($condition)
            ->field($field)
            ->find();
        return $info;
    }
    /**
     * 查询数据的数量
     * @param unknown $condition
     * @return unknown
     */
    public function getCount($condition)
    {
        $count = Db::table($this->table)->where($condition)
            ->count();
        return $count;
    }
    /**
     * 查询条件数量
     * @param unknown $condition
     * @param unknown $field
     * @return number|unknown
     */
    public function getSum($condition, $field)
    {
        $sum = Db::table($this->table)->where($condition)
            ->sum($field);
        if(empty($sum))
        {
            return 0;
        }else
            return $sum;
    }
    /**
     * 查询数据最大值
     * @param unknown $condition
     * @param unknown $field
     * @return number|unknown
     */
    public function getMax($condition, $field)
    {
        $max = Db::table($this->table)->where($condition)
            ->max($field);
        if(empty($max))
        {
            return 0;
        }else
            return $max;
    }
    /**
     * 查询数据最小值
     * @param unknown $condition
     * @param unknown $field
     * @return number|unknown
     */
    public function getMin($condition, $field)
    {
        $min = Db::table($this->table)->where($condition)
            ->min($field);
        if(empty($min))
        {
            return 0;
        }else
            return $min;
    }
    /**
     * 查询数据均值
     * @param unknown $condition
     * @param unknown $field
     */
    public function getAvg($condition, $field)
    {
        $avg = Db::table($this->table)->where($condition)
            ->avg($field);
        if(empty($avg))
        {
            return 0;
        }else
            return $avg;
    }
    /**
     * 查询第一条数据
     * @param unknown $condition
     */
    public function getFirstData($condition, $order)
    {
        $data = Db::table($this->table)->where($condition)->order($order)
            ->limit(1)->select();
        if(!empty($data))
        {
            return $data[0];
        }else
            return '';
    }
    /**
     * 修改表单个字段值
     * @param unknown $pk_id
     * @param unknown $field_name
     * @param unknown $field_value
     */
    public function ModifyTableField($pk_name, $pk_id, $field_name, $field_value)
    {
        $data = [
            $field_name => $field_value
        ];
        $res = $this->save($data,[$pk_name => $pk_id]);
        return $res;
    }

    /**
     * 执行SQL文件
     * @access public
     * @param string $file 要执行的sql文件路径
     * @param boolean $stop 遇错是否停止  默认为true
     * @param string $db_charset 数据库编码 默认为utf-8
     * @return array
     */
    public function executeSqlFile($file, $stop = true, $db_charset = 'utf-8')
    {
        $error = true;
        if (!is_readable($file)) {
            $error =[
                'error_code' => 'SQL文件不可读',
                'error_sql' => '',
            ];
            return $error;
        }

        $fp = fopen($file, 'rb');
        $sql = fread($fp, filesize($file));
        fclose($fp);

        $sql = str_replace("\r", "\n", str_replace('`' . 'cms_', '`' . config('database.prefix'), $sql));

        foreach (explode(";\n", trim($sql)) as $query) {
            $query = trim($query);
            if ($query) {
                $res = $this->execute($query);

                if ($res === false) {
                    $error[] = [
                        'error_code' => $this->getError(),
                        'error_sql' => $query,
                    ];

                    if ($stop) return $error;
                }
            }
        }
        return $error;
    }

    /**
     * 便捷分页查询
     * @access public
     * @param mixed $options 表达式参数
     * @param mixed $pageopt 分页参数
     * @return mixed
     */
    public function findPage($pageopt=10, $count = false, $options = array(),$rollPage=0)
    {
        
        // 如果没有传入总数，则自动根据条件进行统计
        if ($count === false) {
            // 查询总数
            $count_options = $options;
            $count_options['limit'] = 1;
            $count_options['field'] = 'count(1) as count';
            // 去掉统计时的排序提高效率
            unset($count_options['order']);
            $result = $this->select($count_options);

            $count = $result[0]['count'];
            unset($result);
            unset($count_options);
        }

        // 如果查询总数大于0
        if ($count > 0) {
            // 载入分页类
            //import('ORG.Util.Page');
            // 解析分页参数
            if (is_numeric($pageopt)) {
                $pagesize = intval($pageopt);
            } else {
                $pagesize = 10;
            }

            $p = new \think\PageBack($count, $pageopt); // 实例化分页类 传入总记录数和每页显示的记录数
            if($rollPage){//zzl添加 2015-6-11 10:44
                $p->setRollPage($rollPage);
            }
            // 查询数据
            $options['limit'] = $p->firstRow . ',' . $p->listRows;


            // 输出控制
            $output['count'] = $count;

            $output['html'] = $p->show();
            $output['totalRows'] = $p->totalRows;
            $output['nowPage'] = $p->nowPage;
            $output['totalPages'] = $p->totalPages;
            $resultSet = $this->where($options['where'])->order($options['order'])->page($p->nowPage, $pageopt)->select();
            if ($resultSet) {
                $this->dataList = $resultSet;
            } else {
                $resultSet = '';
            }
            $output['data'] = $resultSet;
            unset($resultSet);
            unset($p);
            unset($count);
        } else {
            $output['count'] = 0;
            $output['totalPages'] = 0;
            $output['totalRows'] = 0;
            $output['nowPage'] = 1;
            $output['html'] = '';
            $output['data'] = '';
        }
        if($output['totalPages']<2){
            $output['html'] = '';
        }
        // 输出数据
        return $output;
    }

}