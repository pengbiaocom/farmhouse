<?php
namespace app\common\model;
use think\Model;
/**
 * 配置模型
 */

class ConfigModel extends BaseModel {
    protected $rule = [
        'name'  =>  'require',
        'title'  =>  'require',
    ];
    protected $message = [
        'name.require'  =>  '标识不能为空',
        'title.require'     => '名称不能为空',
    ];
    protected $scene = [
        'add'   =>  ['name','title'],
        'edit'  =>  ['name','title'],
    ];

    protected $insert = ['name','create_time','update_time','status'=>1];

    protected  function setNameAttr($value){
        return strtoupper($value);
    }

    protected  function setCreateTimeAttr(){
        return time();
    }

    protected  function setUpdateTimeAttr(){
        return time();
    }

    /**
     * 获取配置列表
     * @return array 配置数组
     */
    public function lists(){
        $map    = ['status' => 1];
        $data   = $this->where($map)->field('type,name,value')->select();
        
        $config = [];
        if($data){
            foreach ($data as $value) {
                $config[$value['name']] = $this->parse($value['type'], $value['value']);
            }
        }
        return $config;
    }

    /**
     * 根据配置类型解析配置
     * @param  integer $type  配置类型
     * @param  string  $value 配置值
     */
    private function parse($type, $value){
        switch ($type) {
            case 3: //解析数组
                $array = preg_split('/[,;\r\n]+/', trim($value, ",;\r\n"));
                if(strpos($value,':')){
                    $value  =[];
                    foreach ($array as $val) {
                        list($k, $v) = explode(':', $val);
                        $value[$k]   = $v;
                    }
                }else{
                    $value =    $array;
                }
                break;
        }
        return $value;
    }

}
