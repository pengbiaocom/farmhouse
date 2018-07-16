<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageConfigBuilder;
use app\backstage\builder\BackstageListBuilder;
use app\backstage\builder\BackstageTreeListBuilder;
use app\common\model\ProductCategoryModel;
use app\common\model\ProductModel;

class ProductController extends BackstageController{

    /**
     * 分类列表
     * @return mixed
     */
    public function category()
    {
        //显示页面
        $builder = new BackstageTreeListBuilder();
        $CategoryModel = new ProductCategoryModel();
        $tree = $CategoryModel->getTree(0, 'id,title,sort,pid,status');

        return  $builder->title('商品分类')
            ->suggest('禁用、删除分类时会将分类下的商品转移到默认分类下')
            ->buttonNew(url('product/add'))
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
        $CategoryModel = new ProductCategoryModel();
        if ($this->request->isPost()) {
            $data   = $this->request->param();
            $res = $CategoryModel->editData($data);
            if ($res) {
                cache('SHOW_EDIT_BUTTON',null);
                $this->success($title.lang('_SUCCESS_'), url('Product/category'));
            } else {
                $this->error($title.lang('_FAIL_').$CategoryModel->getError());
            }
        } else {
            $builder = new BackstageConfigBuilder();
            $data = [];
            if ($id != 0) {
                $data = $CategoryModel->find($id);
            } else {
                $father_category_pid=$CategoryModel->where(['id'=>$pid])->value('pid');
                if($father_category_pid!=0){
                    $this->error(lang('_ERROR_CATEGORY_HIERARCHY_'));
                }
            }

            $opt = [];
            if($pid!=0){
                $categorys = $CategoryModel->where(['pid'=>0,'status'=>['egt',0]])->select();
                foreach ($categorys as $category) {
                    $opt[$category['id']] = $category['title'];
                }
            }

            $builder->title($title.'商品分类')
                ->data($data)
                ->keyId()->keyText('title', lang('_TITLE_'))
                ->keySelect('pid',lang('_FATHER_CLASS_'), lang('_FATHER_CLASS_SELECT_'), ['0' =>lang('_TOP_CLASS_')] + $opt)->keyDefault('pid',$pid)
                ->keyStatus()->keyDefault('status',1)
                ->keyInteger('sort',lang('_SORT_'))->keyDefault('sort',0)
                ->buttonSubmit(url('product/add'))->buttonBack();
            return $builder->show();
        }

    }

    /**
     * 设置分类状态：删除=-1，禁用=0，启用=1
     * @param $ids
     * @param $status
     */
    public function setstatus($ids, $status)
    {
        $id = $this->request->param('id', 0, 'intval');
        $productModel = new ProductModel();
        !is_array($ids)&&$ids=explode(',',$ids);
        if(in_array(1,$ids)){
            $this->error(lang('_ERROR_CANNOT_'));
        }
        if($status==0||$status==-1){
            $map['category']= ['in',$ids];
            $productModel->where($map)->setField('category',1);
        }
        $builder = new BackstageListBuilder();
        $builder->doSetStatus('productCategory', $ids, $status);
    }

    /**
     * 商品列表
     * @return mixed
     */
    public function index(){
        $r = config("LIST_ROWS");
        $CategoryModel = new ProductCategoryModel();
        $productModel = new ProductModel();
        $aCate=input('cate',0,'intval');
        if($aCate){
            $cates=$CategoryModel->getCategoryList(['pid'=>$aCate]);
            if(count($cates)){
                $cates=array_column($cates,'id');
                $cates=array_merge([$aCate],$cates);
                $map['category']=['in',$cates];
            }else{
                $map['category']=$aCate;
            }
        }

        $keyword = input('keyword','','op_t');
        if(!empty($keyword)){
            $map[] = "(name like '%".$keyword."%' or id=".intval($keyword)." )";
        }

        $map['status']=1;

        list($list,$totalCount)=$productModel->getListByPage($map,'update_time desc','*',$r);
        $category=$CategoryModel->getCategoryList(['status'=>['egt',0]],1);
        $category=array_combine(array_column($category,'id'),$category);

        foreach($list as &$val){
            $val['category']='['.$val['category'].'] '.$category[$val['category']]['title'];
            if($val['stock'] <= 100) {
                $val['stock'] = '<span style="color:red;padding:0px 10px;"><i class="layui-icon layui-icon-tips"></i>&nbsp;库存不足，当前库存（'.$val['stock'].'）</span>';
            }
        }
        unset($val);
        $optCategory=$category;
        foreach($optCategory as &$val){
            $val['value']=$val['title'];
        }
        unset($val);
        $builder=new BackstageListBuilder();
        $builder->title('商品列表')
            ->data($list)
            ->setSearchPostUrl(url('product/index'))
            ->searchSelect('','cate','select','','',array_merge([['id'=>0,'value'=>lang('_EVERYTHING_')]],$optCategory))
            ->searchText('','keyword','text','商品名称/编号')
            ->buttonNew(url('product/editproduct'))->buttonDelete(url('product/setproductstatus'))
            ->keyId()
            ->keyText('name','商品名称')
            ->keyText('category',lang('_CATEGORY_'))
            ->keyText('price','价格')
            ->keyText('unit','单位')
            ->keyText('stock', '库存')
            ->keyText('spec','规格')
            ->keyText('sort',lang('_SORT_'))
            ->keyMap('isXg', '限购', array(0=>'否', 1=>'是'))
            ->keyStatus()->keyUpdateTime()
            ->keyDoActionEdit('product/editproduct?id=###');
        $builder->pagination($totalCount);
        return $builder->show();
    }

    /**
     * 修改商品状态
     * @param $ids
     * @param int $status
     */
    public function setproductstatus($ids,$status=1)
    {
        !is_array($ids)&&$ids=explode(',',$ids);
        $builder = new BackstageListBuilder();
        cache('product_home_data',null);
        $builder->doSetStatus('product', $ids, $status);
    }

    /**
     * 编辑新增商品
     * @return mixed
     */
    public function editproduct()
    {
        $productModel = new ProductModel();
        $CategoryModel = new ProductCategoryModel();
        $aId=input('id',0,'intval');
        $title=$aId?lang('_EDIT_'):lang('_ADD_');

        if(Request()->isPost()){
            $aId&&$data['id']=$aId;
            $data['name']=input('post.name','','op_t');
            $data['content']=input('post.content','','filter_content');
            $data['category']=input('post.category',0,'intval');
            $data['isXg']=input('post.isXg','','intval');
            $data['unit']=input('post.unit','','op_t');
            $data['spec']=input('post.spec','','op_t');
            $data['cover']=input('post.cover',0,'op_t');
            $data['price']=input('post.price','0.00','op_t');
            $data['market_price']=input('post.market_price','0.00','op_t');
            $data['price_line']=input('post.price_line', "", 'op_t');
            $data['sort']=input('post.sort',0,'intval');
            $data['status']=input('post.status',1,'intval');

            $this->_checkOk($data);
            $result=$productModel->editData($data);
            if($result){
                cache('product_home_data',null);
                $this->success($title.lang('_SUCCESS_'),url('product/index'));
            }else{
                $this->error($title.lang('_SUCCESS_'),$productModel->getError());
            }
        }else{
            $data = [];
            if($aId){
                $data=$productModel->getInfoData($aId);
            }
            $category=$CategoryModel->getCategoryList(['status'=>['egt',0]],1);
            $options= [];
            foreach($category as $val){
                $options[$val['id']]=$val['title'];
            }

            $start_time = strtotime(date("Y-m-d 08:00:00",time()));
            $end_time = strtotime(date("Y-m-d 20:00:00",time()));

            $builder=new BackstageConfigBuilder();
            $builder->title($title.'商品')
                ->data($data)
                ->keyId('id')
                ->keyText('name',lang('_TITLE_'))
                ->keySelect('category',lang('_CATEGORY_'),'',$options)
                ->keyMultiImage('cover', "商品图片")
                ->keyText('price',"商品现价")
                ->keyText('market_price',"商品市价")
                ->keyText('unit',"商品单位")
                ->keyText('spec',"商品规格")
                ->keyTextArea('price_line', "商品价格线", '数量|价格  一行一个')
                ->keyEditor('content',"商品描述",'','all',['width' => '700px', 'height' => '300px'])
                ->keyRadio('isXg', '是否为限购商品', '开启限购后，该商品每人仅能购买一份', array(0=>'否', 1=>'是'))

                ->keyStatus()->keyDefault('status',1)
                ->keyInteger('sort',lang('_SORT_'))->keyDefault('sort',999)
                ->group('基础', ['id', 'name', 'category','cover', 'isXg', 'price', 'market_price'])
                ->group('扩展', ['unit', 'spec', 'price_line', 'content', 'sort'])
                ->buttonSubmit()->buttonBack();
            return $builder->show();
        }
    }

    /**
     * 回收站
     * @param int $page
     * @param int $r
     * @param string $model
     * @return mixed
     */
    public function  producttrash($page = 1, $r = 20,$model=''){
        $builder = new BackstageListBuilder();
        $productModel = new ProductModel();
        $builder->clearTrash($model);
        $map = ['status' => -1];
        $data = $productModel->where($map)->page($page, $r)->select();
        $totalCount = $productModel->where($map)->count();
        $builder->title('商品回收站')->buttonRestore(url('product/setproductstatus'))
            ->buttonClear('product')
            ->data($data)
            ->keyText('name','商品名称')
            ->keyText('category',lang('_CATEGORY_'))
            ->keyText('price','价格')
            ->keyTime('start_time','开始时间')
            ->keyTime('end_time','结束时间')
            ->keyText('sort',lang('_SORT_'))
            ->keyStatus()->keyUpdateTime()
            ->pagination($totalCount);
        return $builder->show();
    }

    /**
     * 检测
     * @param array $data
     * @return bool
     */
    private function _checkOk($data= []){
        if(!mb_strlen($data['name'],'utf-8')){
            $this->error("请填写商品名称");
        }
        if(mb_strlen($data['content'],'utf-8')<20){
            $this->error(lang('_TIP_CONTENT_LENGTH_'));
        }

        if(empty($data['price'])){
            $this->error("请填写商品价格");
        }else if(!preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $data['price'])){
            $this->error("请填写正确的商品价格");
        }

        if($data['end_time']<$data['start_time']){
            $this->error("结束时间不能小于开始时间");
        }
        return true;
    }
}