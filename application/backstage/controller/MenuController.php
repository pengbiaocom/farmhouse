<?php
namespace app\backstage\controller;

use app\common\model\MenuModel;
use app\common\model\TreeModel;
use app\common\model\ModuleModel;
use app\backstage\builder\BackstageConfigBuilder;
use app\backstage\builder\BackstageListBuilder;
use app\backstage\builder\BackstageSortBuilder;
/**
 * 后台配置控制器
 */
class MenuController extends BackstageController {

    /**
     * 后台菜单首页
     * @return none
     */
    public function index(){
        $pid  = input('pid',0,'intval');
        $menuModel = new MenuModel();
        if($pid){
            $data = $menuModel->where("id={$pid}")->field(true)->find();
            $this->assign('data',$data);
        }
        $title      =   trim(input('title'));
        $all_menu   =   $menuModel->field('id,title')->order('sort asc,id asc')->select();
        $all_menu = array_column($all_menu,'title','id');
        $map['pid'] =   $pid;
        if($title)
            $map['title'] = ['like',"%{$title}%"];

        $list       =   $menuModel->where($map)->field(true)->order('sort asc,id asc')->select()->toArray();
        int_to_string($list,['hide'=>[1=>lang('_YES_'),0=>lang('_NOT_')],'is_dev'=>[1=>lang('_YES_'),0=>lang('_NOT_')]]);
        if($list) {
            foreach($list as &$key){
                if($key['pid']){
                    $key['up_title'] = $all_menu[$key['pid']];
                }
            }
        }
        // 记录当前列表页的cookie
        Cookie('__forward__',$_SERVER['REQUEST_URI']);
        $builder = new BackstageListBuilder();

        $titles = !empty($data) ? '['.$data['title'].'] 子 ': '';

        $builder->title($titles.lang('_MENU_MANAGER_'))
            ->buttonNew(url('add',['pid'=>$pid]))
            ->button(lang('_DELETE_WITH_SPACE_'),['class'=>'layui-btn layui-btn-danger ajax-post confirm','url'=>url('del'),'target-form'=>'ids'])
            ->button(lang('_SORT_'),['class'=>'layui-btn layui-btn-normal list_sort','url'=>url('sort',['pid'=>$pid])])
            ->setSearchPostUrl(url('index'))
            ->searchText('','title','text',lang('_MENU_NAME_INPUT_'))
            ->keyText('id',lang('_ID_'))
            ->keyLink('title',lang('_NAME_'),'index?pid=###')
            ->keyText('up_title',lang('_MENU_SUPERIOR_'))
            ->keyText('groups',lang('_GROUP_'))
            ->keyText('url',lang('_URL_'))
            ->keyText('sort',lang('_SORT_'))
            ->keyText('is_dev_text',lang('_DEV_MODE_ONLY_'))
            ->keyText('hide_text',lang('_HIDE_'))
            ->keyDoAction('edit?id=###',lang('_EDIT_'))
            ->keyDoAction('del?ids=###',lang('_DELETE_'))
            ->data($list);
        return $builder->show();
    }

    /**
     * 新增菜单
     */
    public function add(){
        $menuModel = new MenuModel();
        if(Request()->isPost()){
            $data = $this->request->param();
            $id = $menuModel->allowField(true)->save($data);
            if($id){
                //记录行为
                action_log('add_menu', 'Menu', $id, UID);
                $this->success(lang('_SUCCESS_ADD_'), Cookie('__forward__'));
            } else {
                $this->error(lang('_FAIL_ADD_'));
            }
        } else {
            $pid = input('pid',0,'intval');
            if(input('pid')!=0){
                $modelName=$menuModel->where(['id'=>input('pid'),'module'=>['neq','']])->find();
            }
            $info = ['pid'=>$pid,'groups'=>''];
            $menus = $menuModel->field(true)->select()->toArray();

            $treeModel = new TreeModel();
            $menus = $treeModel->toFormatTree($menus);
            $menus = array_merge([0=>['id'=>0,'value'=>lang('_MENU_TOP_')]], $menus);
            $menus = array_column($menus,'value','id');

            $moduleModel = new ModuleModel();
            if(!empty($modelName)){
                $module[$modelName['module']] =$modelName['title'].'-'.$modelName['module'];
            }else{
                $module['all'] = lang('_SYSTEM_CORE_MENU_');
            }
            $modules = $moduleModel->getAll();
            if(!empty($modules)){
                foreach($modules as $k=>$v){
                    $module[$v['name']] = $v['alias'];
                }
            }

            $builder=new BackstageConfigBuilder();

            $hide = [1=>lang('_YES_'),0=>lang('_NOT_')];

            $builder->title(lang('_NEW_WITH_SINGLE_').lang('_BACKGROUND_MENU_'))
                ->keyText('title',lang('_TITLE_').lang('_COLON_'),lang('_USED_IN_THE_CONFIGURATION_HEADER_'))
                ->keySelect('module',lang('_THE_MODULE_').lang('_COLON_'),lang('_MODULE_MODULE_MENU_MUST_BE_SELECTED_OTHERWISE_IT_CAN_NOT_BE_EXPORTED_'),$module)
                ->keyText('icon',lang('_SMALL_ICON_').lang('_COLON_'),lang('_USED_TO_DISPLAY_THE_LEFT_SIDE_OF_THE_MENU_NOT_TO_SHOW_'))
                ->keyInteger('sort',lang('_SORT_').lang('_COLON_'),lang('_USED_IN_THE_ORDER_OF_GROUP_DISPLAY_'))->keyDefault('sort',0)
                ->keyText('url',lang('_LINK_').lang('_COLON_'),lang('_U_FUNCTION_ANALYSIS_OF_THE_URL_OR_THE_CHAIN_'))
                ->keySelect('pid',lang('_SUPERIOR_MENU_').lang('_COLON_'),lang('_THE_HIGHER_LEVEL_MENU_'),$menus)
                ->keyText('groups',lang('_GROUPING_').lang('_COLON_'),lang('_FOR_THE_LEFT_GROUP_TWO_MENU_'))
                ->keyRadio('hide',lang('_WHETHER_TO_HIDE_').lang('_COLON_'),'',$hide)
                ->keyRadio('is_dev',lang('_ONLY_DEVELOPER_MODE_VISIBLE_').lang('_COLON_'),'',$hide)
                ->keyText('tip',lang('_EXPLAIN_').lang('_COLON_'),lang('_MENU_DETAILS_'))
                ->buttonSubmit()
                ->buttonBack()
                ->data($info);
            return $builder->show();
        }
    }


    /**
     * 编辑配置
     */
    public function edit(){
        $menuModel = new MenuModel();
        if(Request()->isPost()){
            $data = $this->request->param();
            if($menuModel->allowField(true)->save($data,['id'=>$data['id']])!== false){
                //记录行为
                action_log('update_menu', 'Menu', $data['id'], UID);
                $this->success(lang('_SUCCESS_UPDATE_'), Cookie('__forward__'));
            } else {
                $this->error(lang('_FAIL_UPDATE_'));
            }
        } else {
            /* 获取数据 */
            $id = input('id',0,'intval');
            $treeModel = new TreeModel();
            $info = $menuModel->field(true)->find($id);
            $menus = $menuModel->field(true)->select()->toArray();
            $menus = $treeModel->toFormatTree($menus);
            $menus = array_merge([0=>['id'=>0,'value'=>lang('_MENU_TOP_')]], $menus);
            $menus = array_column($menus,'value','id');
            $moduleModel = new ModuleModel();
            if(!empty($modelName)){
                $module[$modelName['module']] =$modelName['title'].'-'.$modelName['module'];
            }else{
                $module['all'] = lang('_SYSTEM_CORE_MENU_');
            }
            $modules = $moduleModel->getAll();
            if(!empty($modules)){
                foreach($modules as $k=>$v){
                    $module[$v['name']] = $v['alias'];
                }
            }

            if(false === $info){
                $this->error(lang('_ERROR_MENU_INFO_GET_'));
            }

            $builder=new BackstageConfigBuilder();

            $hide = [1=>lang('_YES_'),0=>lang('_NOT_')];

            $builder->title(lang('_EDIT_WITH_SINGLE_').lang('_BACKGROUND_MENU_'))
                ->keyHidden('id','')
                ->keyText('title',lang('_TITLE_').lang('_COLON_'),lang('_USED_IN_THE_CONFIGURATION_HEADER_'))
                ->keySelect('module',lang('_THE_MODULE_').lang('_COLON_'),lang('_MODULE_MODULE_MENU_MUST_BE_SELECTED_OTHERWISE_IT_CAN_NOT_BE_EXPORTED_'),$module)
                ->keyText('icon',lang('_SMALL_ICON_').lang('_COLON_'),lang('_USED_TO_DISPLAY_THE_LEFT_SIDE_OF_THE_MENU_NOT_TO_SHOW_'))
                ->keyText('sort',lang('_SORT_').lang('_COLON_'),lang('_USED_IN_THE_ORDER_OF_GROUP_DISPLAY_'))
                ->keyText('url',lang('_LINK_').lang('_COLON_'),lang('_U_FUNCTION_ANALYSIS_OF_THE_URL_OR_THE_CHAIN_'))
                ->keySelect('pid',lang('_SUPERIOR_MENU_').lang('_COLON_'),lang('_THE_HIGHER_LEVEL_MENU_'),$menus)
                ->keyText('groups',lang('_GROUPING_').lang('_COLON_'),lang('_FOR_THE_LEFT_GROUP_TWO_MENU_'))
                ->keyRadio('hide',lang('_WHETHER_TO_HIDE_').lang('_COLON_'),'',$hide)
                ->keyRadio('is_dev',lang('_ONLY_DEVELOPER_MODE_VISIBLE_').lang('_COLON_'),'',$hide)
                ->keyText('tip',lang('_EXPLAIN_').lang('_COLON_'),lang('_MENU_DETAILS_'))
                ->buttonSubmit()
                ->buttonBack()
                ->data($info);
            return $builder->show();
        }
    }

    /**
     * 删除后台菜单
     */
    public function del(){
        $id = array_unique(input('ids/a',0));

        if ( empty($id) ) {
            $this->error(lang('_ERROR_DATA_SELECT_').lang('_EXCLAMATION_'));
        }
        $menuModel = new MenuModel();
        $id = is_array($id) ? implode(',', $id) : $id;
        $map = ['id' => ['in', $id]];
        if($menuModel->where($map)->delete()){
            //记录行为
            action_log('delete_menu', 'Menu', $id, UID);
            $this->success(lang('_DELETE_SUCCESS_'));
        } else {
            $this->error(lang('_DELETE_FAILED_'));
        }
    }

    /**
     * 菜单排序
     */
    public function sort(){
        $menuModel = new MenuModel();
        if(Request()->isGet()){
            $data = $this->request->param();
            $ids = $data['ids'];
            $pid = $data['pid'];

            //获取排序的数据
            $map['hide']=0;
            if(!empty($ids)){
                $ids = is_array($ids) ? implode(',', $ids) : $ids;
                $map['id'] = ['in',$ids];
            }else{
                if($pid !== ''){
                    $map['pid'] = $pid;
                }
            }
            $list = $menuModel->where($map)->field('id,title')->order('sort asc,id asc')->select();

            $builder = new BackstageSortBuilder();
            $builder->title(lang('_MENU_SORT_'))
                ->buttonSubmit(url('sort'))
                ->buttonBack(Cookie('__forward__'))
                ->data($list);
            return $builder->show();
        }elseif (Request()->isPost()){
            $data = $this->request->param();
            $ids = $data['ids'];
            $ids = explode(',', $ids);
            foreach ($ids as $key=>$value){
                $res = $menuModel->where(['id'=>$value])->setField('sort', $key+1);
            }
            if($res !== false){
                $this->success(lang('_SORT_OF_SUCCESS_'));
            }else{
                $this->error(lang('_SORT_OF_FAILURE_'));
            }
        }else{
            $this->error(lang('_ILLEGAL_REQUEST_'));
        }
    }
}
