<?php
namespace app\common\controller;

/**
 * 插件类
 */
abstract class Addon{
    /**
     * 视图实例对象
     * @var view
     * @access protected
     */
    protected $view = null;

    /**
     * $info = array(
     *  'name'=>'Editor',
     *  'title'=>'编辑器',
     *  'description'=>'用于增强整站长文本的输入和显示',
     *  'status'=>1,
     *  'author'=>'thinkphp',
     *  'version'=>'0.1'
     *  )
     */
    public $info                =   [];
    public $addon_path          =   '';
    public $config_file         =   '';
    public $custom_config       =   '';
    public $admin_list          =   [];
    public $custom_adminlist    =   '';
    public $access_url          =   [];

    public function __construct(){
        $this->view         =   \think\View::instance('think\view');
        $this->addon_path   =   ONETHINK_ADDON_PATH.$this->getName().'/';
        $TMPL_PARSE_STRING = config('TMPL_PARSE_STRING');
        $TMPL_PARSE_STRING['__ADDONROOT__'] = __ROOT__ . '/Addons/'.$this->getName();
        config('TMPL_PARSE_STRING', $TMPL_PARSE_STRING);
        if(is_file($this->addon_path.'config.php')){
            $this->config_file = $this->addon_path.'config.php';
        }
    }

    /**
     * 模板主题设置
     * @access protected
     * @param string $theme 模版主题
     * @return Action
     */
    final protected function theme($theme){
        $this->view->theme($theme);
        return $this;
    }

    //显示方法
    final protected function display($template=''){
        if($template == '')
            $template = Request()->controller();
        echo ($this->fetch($template));
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param mixed $name 要显示的模板变量
     * @param mixed $value 变量的值
     * @return Action
     */
    final protected function assign($name,$value='') {
        $this->view->assign($name,$value);
        return $this;
    }


    //用于显示模板的方法
    final protected function fetch($templateFile = ''){
        if(empty($templateFile)) $templateFile = Request()->controller();
        if(!is_file($templateFile)){
            $templateFile = $this->addon_path.$templateFile.config('TMPL_TEMPLATE_SUFFIX');
            if(!is_file($templateFile)){
                throw new \Exception(lang('_TEMPLATE_NOT_EXIST_')."$templateFile");
            }
        }
        return $this->view->fetch($templateFile);
    }

    final public function getName(){
        $class = get_class($this);
        return substr($class,strrpos($class, '\\')+1, -5);
    }

    final public function checkInfo(){
        $info_check_keys = ['name','title','description','status','author','version'];
        foreach ($info_check_keys as $value) {
            if(!array_key_exists($value, $this->info))
                return FALSE;
        }
        return TRUE;
    }

    /**
     * 获取插件的配置数组
     */
    final public function getConfig($name=''){
        if(empty($name)){
            $name = $this->getName();
        }

        $tag='addons_config_'.$name;
        $config=cache($tag);
        if($config===false){
            static $_config = [];
            if(isset($_config[$name])){
                return $_config[$name];
            }
            $config =   [];
            $map['name']    =   $name;
            $map['status']  =   1;
            $config  =   db('Addons')->where($map)->value('config');
            if($config){
                $config   =   json_decode($config, true);
            }else{
                $temp_arr = include $this->config_file;
                foreach ($temp_arr as $key => $value) {
                    if($value['type'] == 'group'){
                        foreach ($value['options'] as $gkey => $gvalue) {
                            foreach ($gvalue['options'] as $ikey => $ivalue) {
                                $config[$ikey] = $ivalue['value'];
                            }
                        }
                    }else{
                        $config[$key] = $temp_arr[$key]['value'];
                    }
                }
            }
            $_config[$name]     =   $config;
            cache($tag,$config);
        }

        return $config;
    }

    /**初始化钩子的方法，防止钩子不存在的情况发生
     * @param $name
     * @param $description
     * @param int $type
     * @return bool
     */
    public function initHook($name,$description,$type=1){
        $hook=db('hooks')->where(['name'=>$name])->find();
        if(!$hook){
            $hook['name']=$name;
            $hook['description']=$description;
            $hook['type']=$type;
            $hook['update_time']=time();
            $hook['addons']=$this->getName();
            $result=db('hooks')->insert($hook);
            if($result===false){
                return false;
            }else{
                return true;
            }
        }
        return true;
    }

    //必须实现安装
    abstract public function install();

    //必须卸载插件方法
    abstract public function uninstall();
}
