<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageConfigBuilder;
use app\backstage\builder\BackstageListBuilder;
use app\backstage\builder\BackstageTreeListBuilder;
use app\news\model\NewsCategoryModel;
use app\news\model\NewsModel;

class NewsController extends BackstageController{

    /**
     * 分类列表
     * @return mixed
     */
    public function newscategory()
    {
        //显示页面
        $builder = new BackstageTreeListBuilder();
        $newsCategoryModel = new NewsCategoryModel();
        $tree = $newsCategoryModel->getTree(0, 'id,title,sort,pid,status');

       return  $builder->title(lang('_CATEGORY_MANAGER_'))
            ->suggest(lang('_CATEGORY_MANAGER_SUGGEST_'))
            ->buttonNew(url('news/add'))
            ->data($tree)->show();
    }

    /**
     * 分类添加
     */
    public function add()
    {
        $id = $this->request->param('id', 0, 'intval');
        $pid = $this->request->param('pid', 0, 'intval');
        $title=$id?lang('_EDIT_'):lang('_ADD_');
        $newsCategoryModel = new NewsCategoryModel();
        if ($this->request->isPost()) {
            $data   = $this->request->param();
            $res = $newsCategoryModel->editData($data);
            if ($res) {
                cache('SHOW_EDIT_BUTTON',null);
                $this->success($title.lang('_SUCCESS_'), url('News/newscategory'));
            } else {
                $this->error($title.lang('_FAIL_').$newsCategoryModel->getError());
            }
        } else {
            $builder = new BackstageConfigBuilder();
            $data = [];
            if ($id != 0) {
                $data = $newsCategoryModel->find($id);
            } else {
                $father_category_pid=$newsCategoryModel->where(['id'=>$pid])->value('pid');
                if($father_category_pid!=0){
                    $this->error(lang('_ERROR_CATEGORY_HIERARCHY_'));
                }
            }

            $opt = [];
            if($pid!=0){
                $categorys = $newsCategoryModel->where(['pid'=>0,'status'=>['egt',0]])->select();
                foreach ($categorys as $category) {
                    $opt[$category['id']] = $category['title'];
                }
            }

            $builder->title($title.lang('_CATEGORY_'))
                ->data($data)
                ->keyId()->keyText('title', lang('_TITLE_'))
                ->keySelect('pid',lang('_FATHER_CLASS_'), lang('_FATHER_CLASS_SELECT_'), ['0' =>lang('_TOP_CLASS_')] + $opt)->keyDefault('pid',$pid)
                ->keyRadio('can_post',lang('_PLAY_YN_'),'',[0=>lang('_NO_'),1=>lang('_YES_')])->keyDefault('can_post',1)
                ->keyRadio('need_audit',lang('_PLAY_YN_AUDIT_'),'',[0=>lang('_NO_'),1=>lang('_YES_')])->keyDefault('need_audit',1)
                ->keyStatus()->keyDefault('status',1)
                ->keyInteger('sort',lang('_SORT_'))->keyDefault('sort',0)
                ->buttonSubmit(url('news/add'))->buttonBack();
             return $builder->show();
        }

    }

    /**
     * 设置资讯分类状态：删除=-1，禁用=0，启用=1
     * @param $ids
     * @param $status
     */
    public function setstatus($ids, $status)
    {
        $id = $this->request->param('id', 0, 'intval');
        $newsModel = new NewsModel();
        !is_array($ids)&&$ids=explode(',',$ids);
        if(in_array(1,$ids)){
            $this->error(lang('_ERROR_CANNOT_'));
        }
        if($status==0||$status==-1){
            $map['category']= ['in',$ids];
            $newsModel->where($map)->setField('category',1);
        }
        $builder = new BackstageListBuilder();
        $builder->doSetStatus('newsCategory', $ids, $status);
    }

    public function config()
    {
        $builder=new BackstageConfigBuilder();
        $data=$builder->handleConfig();
        $default_position=<<<str
1:系统首页
2:推荐阅读
4:本类推荐
str;

        $builder->title(lang('_NEWS_BASIC_CONF_'))
            ->data($data);

        $builder->keyTextArea('NEWS_SHOW_POSITION',lang('_GALLERY_CONF_'))->keyDefault('NEWS_SHOW_POSITION',$default_position)
            ->keyRadio('NEWS_ORDER_FIELD',lang('_FRONT_LIST_SORT_'),lang('_SORT_RULE_'),['view'=>lang('_VIEWS_'),'create_time'=>lang('_CREATE_TIME_'),'update_time'=>lang('_UPDATE_TIME_')])->keyDefault('NEWS_ORDER_FIELD','create_time')
            ->keyRadio('NEWS_ORDER_TYPE',lang('_LIST_SORT_STYLE_'),'',array('asc'=>lang('_ASC_'),'desc'=>lang('_DESC_')))->keyDefault('NEWS_ORDER_TYPE','desc')
            ->keyInteger('NEWS_PAGE_NUM','',lang('_LIST_IN_PAGE_'))->keyDefault('NEWS_PAGE_NUM','20')

            ->keyText('NEWS_SHOW_TITLE', lang('_TITLE_NAME_'), lang('_TIP_NEWS_ARISE_'))->keyDefault('NEWS_SHOW_TITLE',lang('_HOT_NEWS_'))
            ->keyText('NEWS_SHOW_COUNT', lang('_NEWS_SHOWS_'), lang('_TIP_NEWS_ARISE_'))->keyDefault('NEWS_SHOW_COUNT',4)
            ->keyRadio('NEWS_SHOW_TYPE', lang('_NEWS_SCREEN_'), '', ['1' => lang('_BG_RECOMMEND_'), '0' => lang('_EVERYTHING_')])->keyDefault('NEWS_SHOW_TYPE',0)
            ->keyRadio('NEWS_SHOW_ORDER_FIELD', lang('_SORT_VALUE_'), lang('_TIP_SORT_VALUE_'), ['view' => lang('_VIEWS_'), 'create_time' => lang('_DELIVER_TIME_'), 'update_time' => lang('_UPDATE_TIME_')])->keyDefault('NEWS_SHOW_ORDER_FIELD','view')
            ->keyRadio('NEWS_SHOW_ORDER_TYPE', lang('_SORT_TYPE_'), lang('_TIP_SORT_TYPE_'), ['desc' => lang('_COUNTER_'), 'asc' => lang('_DIRECT_')])->keyDefault('NEWS_SHOW_ORDER_TYPE','desc')
            ->keyText('NEWS_SHOW_CACHE_TIME', lang('_CACHE_TIME_'),lang('_TIP_CACHE_TIME_'))->keyDefault('NEWS_SHOW_CACHE_TIME','600')

            ->group(lang('_BASIC_CONF_'), 'NEWS_SHOW_POSITION,NEWS_ORDER_FIELD,NEWS_ORDER_TYPE,NEWS_PAGE_NUM')->group(lang('_HOME_SHOW_CONF_'), 'NEWS_SHOW_COUNT,NEWS_SHOW_TITLE,NEWS_SHOW_TYPE,NEWS_SHOW_ORDER_TYPE,NEWS_SHOW_ORDER_FIELD,NEWS_SHOW_CACHE_TIME')
            ->groupLocalComment(lang('_LOCAL_COMMENT_CONF_'),'index')
            ->buttonSubmit()->buttonBack();
        return $builder->show();
    }


    //资讯列表start
    public function index($page=1,$r=20)
    {
        $newsCategoryModel = new NewsCategoryModel();
        $newsModel = new NewsModel();
        $aCate=input('cate',0,'intval');
        if($aCate){
            $cates=$newsCategoryModel->getCategoryList(['pid'=>$aCate]);
            if(count($cates)){
                $cates=array_column($cates,'id');
                $cates=array_merge([$aCate],$cates);
                $map['category']=['in',$cates];
            }else{
                $map['category']=$aCate;
            }
        }

        $aPos=input('pos',0,'intval');
        /* 设置推荐位 */
        if($aPos>0){
            $map[] = "position & {$aPos} = {$aPos}";
        }

        $keyword = input('keyword','','op_t');
        if(!empty($keyword)){
            $map[] = "(title like '%".$keyword."%' or id=".intval($keyword)." )";
        }

        $map['status']=1;

        $positions=$this->_getPositions(1);
        list($list,$totalCount)=$newsModel->getListByPage($map,'update_time desc','*',$r);
        $category=$newsCategoryModel->getCategoryList(['status'=>['egt',0]],1);
        $category=array_combine(array_column($category,'id'),$category);

        foreach($list as &$val){
            $val['category']='['.$val['category'].'] '.$category[$val['category']]['title'];
        }
        unset($val);
        $optCategory=$category;
        foreach($optCategory as &$val){
            $val['value']=$val['title'];
        }
        unset($val);
        $builder=new BackstageListBuilder();
        $builder->title(lang('_NEWS_LIST_'))
            ->data($list)
            ->setSearchPostUrl(url('backstage/news/index'))
            ->searchSelect('','cate','select','','',array_merge([['id'=>0,'value'=>lang('_EVERYTHING_')]],$optCategory))
            ->searchSelect(lang('_RECOMMENDATIONS_'),'pos','select','','',array_merge([['id'=>0,'value'=>lang('_ALL_DEFECTIVE_')]],$positions))
            ->searchText('','keyword','text','资讯标题/编号')
            ->buttonNew(url('news/editnews'))->buttonDelete(url('news/setnewsstatus'))
            ->keyId()->keyUid()->keyText('title',lang('_TITLE_'))->keyText('category',lang('_CATEGORY_'))->keyText('sort',lang('_SORT_'))
            ->keyStatus()->keyUpdateTime()
            ->keyDoActionEdit('news/editnews?id=###');
        $builder->pagination($totalCount);
        return $builder->show();
    }

    //待审核列表
    public function audit($page=1,$r=20)
    {
        $newsCategoryModel = new NewsCategoryModel();
        $newsModel = new NewsModel();
        $aAudit=input('audit',0,'intval');
        if($aAudit==1){
            $map['status']=-1;
        }else{
            $map['status']=2;
        }
        list($list,$totalCount)=$newsModel->getListByPage($map,'update_time desc','*',$r);
        $cates=array_column($list,'category');
        $category=$newsCategoryModel->getCategoryList(['id'=>['in',$cates],'status'=>1],1);
        $category=array_combine(array_column($category,'id'),$category);
        foreach($list as &$val){
            $val['category']='['.$val['category'].'] '.$category[$val['category']]['title'];
        }
        unset($val);

        $builder=new BackstageListBuilder();

        $builder->title(lang('_AUDIT_LIST_'))
            ->data($list)
            ->setStatusUrl(url('news/setnewsstatus'))
            ->buttonEnable(null,lang('_AUDIT_SUCCESS_'))
            ->buttonModalPopup(url('news/doaudit'),null,lang('_AUDIT_UNSUCCESS_'),['data-title'=>lang('_AUDIT_FAIL_REASON_'),'target-form'=>'ids'])
            ->setSearchPostUrl(url('backstage/news/audit'))
            ->searchSelect('','audit','select','','',[['id'=>0,'value'=>lang('_AUDIT_READY_')],['id'=>1,'value'=>lang('_AUDIT_FAIL_')]])
            ->keyId()->keyUid()->keyText('title',lang('_TITLE_'))->keyText('category',lang('_CATEGORY_'))->keyText('sort',lang('_SORT_'));
        if($aAudit==1){
            $builder->keyText('reason',lang('_FAULT_REASON_'));
        }
        $builder->keyUpdateTime()
            ->keyDoActionEdit('news/editnews?id=###')
            ->pagination($totalCount);
        return $builder->show();
    }

    /**
     * 审核失败原因设置
     */
    public function doaudit()
    {
        $newsModel = new NewsModel();
        if(Request()->isPost()){
            $ids=input('post.ids','','text');
            $ids=explode(',',$ids);
            $reason=input('post.reason','','text');
            $res=$newsModel->where(['id'=>['in',$ids]])->setField(['reason'=>$reason,'status'=>-1]);
            if($res){
                $result['status']=1;
                $result['url']=url('backstage/news/audit');
            }else{
                $result['status']=0;
                $result['info']=lang('_OPERATE_FAIL_');
            }
            $this->ajaxReturn($result);
        }else{
            $ids=input('ids');
            $ids=implode(',',$ids);
            $this->assign('ids',$ids);
            return $this->fetch(T('News@Backstage/audit'));
        }
    }

    public function setnewsstatus($ids,$status=1)
    {
        !is_array($ids)&&$ids=explode(',',$ids);
        $builder = new BackstageListBuilder();
        cache('news_home_data',null);
        $builder->doSetStatus('News', $ids, $status);
    }

    public function editnews()
    {
        $newsModel = new NewsModel();
        $newsCategoryModel = new NewsCategoryModel();
        $aId=input('id',0,'intval');
        $title=$aId?lang('_EDIT_'):lang('_ADD_');
        if(Request()->isPost()){
            $aId&&$data['id']=$aId;
            $data['uid']=input('post.uid',is_login(),'intval');
            $data['title']=input('post.title','','op_t');
            $data['content']=input('post.content','','filter_content');
            $data['category']=input('post.category',0,'intval');
            $data['description']=input('post.description','','op_t');
            $data['cover']=input('post.cover',0,'intval');
            $data['view']=input('post.view',0,'intval');
            $data['comment']=input('post.comment',0,'intval');
            $data['collection']=input('post.collection',0,'intval');
            $data['sort']=input('post.sort',0,'intval');
            $data['status']=input('post.status',1,'intval');
            $data['source']=input('post.source','','op_t');
            $data['position']=0;
            $position=input('post.position','','op_t');
            $position=explode(',',$position);
            foreach($position as $val){
                $data['position']+=intval($val);
            }
            $this->_checkOk($data);
            $result=$newsModel->editData($data);
            if($result){
                cache('news_home_data',null);
                $this->success($title.lang('_SUCCESS_'),url('News/index'));
            }else{
                $this->error($title.lang('_SUCCESS_'),$newsModel->getError());
            }
        }else{
            $position_options=$this->_getPositions();
            if($aId){
                $data=$newsModel->getData($aId);
                $position= [];
                foreach($position_options as $key=>$val){
                    if($key&$data['position']){
                        $position[]=$key;
                    }
                }
                $data['position']=implode(',',$position);
            }
            $category=$newsCategoryModel->getCategoryList(['status'=>['egt',0]],1);
            $options= [];
            foreach($category as $val){
                $options[$val['id']]=$val['title'];
            }

            $builder=new BackstageConfigBuilder();
            $builder->title($title.lang('_NEWS_'))
                ->data($data)
                ->keyId()
                ->keyReadOnly('uid',lang('_PUBLISHER_'))->keyDefault('uid',is_login())
                ->keyText('title',lang('_TITLE_'))
                ->keySingleImage('cover',lang('_COVER_'))
                ->keySelect('category',lang('_CATEGORY_'),'',$options)
                ->keyEditor('content',lang('_CONTENT_'),'','all',['width' => '700px', 'height' => '400px'])

                ->keyTextArea('description',lang('_NOTE_'))
                ->keyStatus()->keyDefault('status',1)
                ->keyInteger('view',lang('_VIEWS_'))->keyDefault('view',0)
                ->keyInteger('comment',lang('_COMMENTS_'))->keyDefault('comment',0)
                ->keyInteger('collection',lang('_COLLECTS_'))->keyDefault('collection',0)
                ->keyInteger('sort',lang('_SORT_'))->keyDefault('sort',0)
                ->keyText('source',lang('_SOURCE_'),lang('_SOURCE_ADDRESS_'))

                ->keyCheckBox('position',lang('_RECOMMENDATIONS_'),lang('_TIP_RECOMMENDATIONS_'),$position_options)
                ->group(lang('_BASIS_'),'id,uid,title,cover,category,content')
                ->group(lang('_EXTEND_'),'description,status,view,comment,sort,position,source')

                ->buttonSubmit()->buttonBack();
            return $builder->show();
        }
    }

    //回收站
    public function  newstrash($page = 1, $r = 20,$model=''){
        $builder = new BackstageListBuilder();
        $newsModel = new NewsModel();
        $builder->clearTrash($model);
        $map = ['status' => -1];
        $data = $newsModel->where($map)->page($page, $r)->select();
        $totalCount = $newsModel->where($map)->count();
        $builder->title('资讯回收站')->buttonRestore(url('news/setnewsstatus'))
            ->buttonClear('news')
            ->data($data)
            ->keyId()->keyUid()->keyText('title',lang('_TITLE_'))->keyText('category',lang('_CATEGORY_'))->keyText('sort',lang('_SORT_'))
            ->keyStatus()->keyUpdateTime()
            ->pagination($totalCount, $r);
        return $builder->show();
    }

    private function _checkOk($data= []){
        if(!mb_strlen($data['title'],'utf-8')){
            $this->error(lang('_TIP_TITLE_EMPTY_'));
        }
        if(mb_strlen($data['content'],'utf-8')<20){
            $this->error(lang('_TIP_CONTENT_LENGTH_'));
        }
        return true;
    }

    private function _getPositions($type=0)
    {
        $default_position=<<<str
1:系统首页
2:推荐阅读
4:本类推荐
str;
        $positons=modC('NEWS_SHOW_POSITION',$default_position,'news');
        $positons = str_replace("\r", '', $positons);
        $positons = explode("\n", $positons);
        $result= [];
        if($type){
            foreach ($positons as $v) {
                $temp = explode(':', $v);
                $result[] = ['id'=>$temp[0],'value'=>$temp[1]];
            }
        }else{
            foreach ($positons as $v) {
                $temp = explode(':', $v);
                $result[$temp[0]] = $temp[1];
            }
        }

        return $result;
    }

} 