<?php
namespace app\news\controller;

use app\common\controller\HomeBaseController;
use app\common\model\ModuleModel;
use app\news\model\NewsCategoryModel;
use app\news\model\NewsModel;

class IndexController extends HomeBaseController{

    public  function _initialize(){
        parent::_initialize();
        $moduleModel = new ModuleModel();
        if($moduleModel->isInstalled('Wap')) {
            $sign = modC('JUMP_MOB', 0, 'mob');
            if(is_mobile() && ($sign == 0)) {
                redirect('wap/news/index');
            }
        }

        if(isset($_POST['keywords'])){
            $_GET['keywords']=json_encode(trim($_POST['keywords']));
        }
        $keywords=$_GET['keywords'];

        $newsCategoryModel = new NewsCategoryModel();

        $tree = $newsCategoryModel->getTree(0,true,['status' => 1]);
        $this->assign('tree', $tree);
        $menu_list['left'][]= [ 'title' => lang('_HOME_'), 'href' => url('News/Index/index'),'tab'=>'home'];
        foreach ($tree as $category) {
            $menu = ['tab' => 'category_' . $category['id'], 'title' => $category['title'], 'href' => url('News/index/index', ['category' => $category['id'],'keywords'=>$keywords])];
            if ($category['_']) {
                $menu['children'][] = ['title' => lang('_EVERYTHING_'), 'href' => url('News/index/index', ['category' => $category['id'],'keywords'=>$keywords])];
                foreach ($category['_'] as $child)
                    $menu['children'][] = ['title' => $child['title'], 'href' => url('News/index/index', ['category' => $child['id'],'keywords'=>$keywords])];
            }
            $menu_list['left'][] = $menu;
        }
        $menu_list['right']= [];
        $show_edit=cache('SHOW_EDIT_BUTTON');
        if($show_edit===false){
            $map['can_post']=1;
            $map['status']=1;
            $show_edit=$newsCategoryModel->where($map)->count();
            cache('SHOW_EDIT_BUTTON',$show_edit);
        }
        if($show_edit){
            $menu_list['right'][]= ['type'=>'search', 'input_title' => lang('_INPUT_TIP_'),'input_name'=>'keywords','from_method'=>'post', 'action' =>url('News/index/index')];
        }
        $menu_list['first']= ['title' => lang('_NEWS_')];

        $this->assign('tab','home');
        $this->assign('sub_menu', $menu_list);
    }

    public function index(){
        $data = $this->request->param();
        $category = intval($data['category']);
        $current='';
        $map = "status=1";
        if($category){
            $newsCategoryModel = new NewsCategoryModel();
            $this->_category($category);
            $cates= $newsCategoryModel->getCategoryList(['pid'=>$category,'status'=>1]);
            if(count($cates)){
                $cates=array_column($cates,'id');
                $cates=array_merge([$category],$cates);
                $cates = implode(',',$cates);
                $map .= " and category in (".$cates.")";
            }else{
                $map .= " and category =".$category;
            }
            $now_category= $newsCategoryModel->find($category);
            $cid=$now_category['pid']==0?$now_category['id']:$now_category['pid'];
            $current='category_' . $cid;
        }

        $order_field=modC('NEWS_ORDER_FIELD','create_time','News');
        $order_type=modC('NEWS_ORDER_TYPE','desc','News');
        if($data['ob'] == 'is_recommend'){
            $orderby = 'recommend desc,'.$order_field.' '.$order_type;;
        }else if($data['ob'] == 'is_view' ){
            $orderby = 'view desc,'.$order_field.' '.$order_type;;
        }else if($data['ob'] == 'is_comment'){
            $orderby = 'comment asc,'.$order_field.' '.$order_type;
        }else{
            $orderby='sort desc,'.$order_field.' '.$order_type;
        }
        if(empty($data['page'])) $data['page'] = 1;

        $newsModel = new NewsModel();
        list($list,$totalCount) = $newsModel->getListByPage($map,$data['page'],$orderby,'*',modC('NEWS_PAGE_NUM',20,'News'));
        foreach($list as &$val){
            $val['user']=query_user(['space_url','nickname'],$val['uid']);
        }
        unset($val);
        $this->assign('list',$list);
        $this->assign('category', $category);
        $this->assign('totalCount',$totalCount);
        $current= ($current==''?'home':$current);
        $this->assign('tab',$current);
        return $this->fetch();
    }

    public function detail(){
        $aId=input('id',0,'intval');

        /* 标识正确性检测 */
        if (!($aId && is_numeric($aId))) {
            $this->error(lang('_ERROR_ID_'));
        }
        $newsModel = new NewsModel();
        $info=$newsModel->getData($aId);

        $author=query_user(['uid','space_url','nickname','avatar64','signature'],$info['uid']);
        $author['news_count']=$newsModel->where(['uid'=>$info['uid']])->count();

        $this->_category($info['category']);
        /* 更新浏览数 */
        $map = ['id' => $aId];
        $newsModel->where($map)->setInc('view');
        /* 模板赋值并渲染模板 */
        $view=$newsModel->where($map)->field('view')->find();
        $this->assign('view',$view['view']);
        $this->assign('author',$author);
        $this->assign('info', $info);
        $this->setTitle(text($info['title']).' —— '.lang("_MODULE_"));
        $this->setDescription(text($info['description']).' ——'.lang("_MODULE_"));
        return $this->fetch();

    }

    private function _category($id=0)
    {
        $newsCategoryModel = new NewsCategoryModel();
        $now_category=$newsCategoryModel->getTree($id,'id,title,pid,sort',['status'=>1]);
        $this->assign('now_category',$now_category);
        return $now_category;
    }

}