<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageConfigBuilder;
use app\backstage\builder\BackstageListBuilder;
use app\common\model\SeedsModel;

class SeedsController extends BackstageController{


    /**
     * 列表
     * @return mixed
     */
    public function index(){
        $r = config("LIST_ROWS");
        $seedsModel = new SeedsModel();

        $keyword = input('keyword','','op_t');
        if(!empty($keyword)){
            $map['name'] = array('like', "%".$keyword."%");
        }

        $map['status'] = 1;

        list($list,$totalCount)=$seedsModel->getListByPage($map,'update_time desc','*',$r);

        foreach($list as &$val){
            if($val['stock'] <= 100) {
                $val['stock'] = '<span style="color:red;padding:0px 10px;"><i class="layui-icon layui-icon-tips"></i>&nbsp;库存不足，当前库存（'.$val['stock'].'）</span>';
            }
        }
        unset($val);
        $builder=new BackstageListBuilder();
        $builder->title('种子列表')
            ->data($list)
            ->setSearchPostUrl(url('seeds/index'))
            ->searchText('','keyword','text','种子名称')
            ->buttonNew(url('seeds/edit'))->buttonDelete(url('seeds/setstatus'))
            ->keyId()
            ->keyText('name','种子名称')
            ->keyText('unit','单位')
            ->keyText('stock', '库存')
            ->keyText('sort',lang('_SORT_'))
            ->keyStatus()->keyUpdateTime()
            ->keyDoActionEdit('seeds/edit?id=###');
        $builder->pagination($totalCount);
        return $builder->show();
    }

    /**
     * 修改状态
     * @param $ids
     * @param int $status
     */
    public function setstatus($ids,$status=1)
    {
        !is_array($ids)&&$ids=explode(',',$ids);
        $builder = new BackstageListBuilder();
        cache('seeds_home_data',null);
        $builder->doSetStatus('seeds', $ids, $status);
    }

    /**
     * 编辑新增
     * @return mixed
     */
    public function edit()
    {
        $seedsModel = new SeedsModel();
        $aId=input('id',0,'intval');
        $title=$aId?lang('_EDIT_'):lang('_ADD_');

        if(Request()->isPost()){
            $aId&&$data['id']=$aId;
            $data['name']=input('post.name','','op_t');
            $data['content']=input('post.content','','filter_content');
            $data['stock']=input('post.stock',100,'intval');
			$data['total_sales']=input('post.total_sales',0,'intval');
            $data['unit']=input('post.unit','','op_t');
            $data['cover']=input('post.cover',0,'op_t');
            $data['sort']=input('post.sort',0,'intval');
            $data['status']=input('post.status',1,'intval');

            $this->_checkOk($data);
            $result=$seedsModel->editData($data);
            if($result){
                cache('seeds_home_data',null);
                $this->success($title.lang('_SUCCESS_'),url('seeds/index'));
            }else{
                $this->error($title.lang('_SUCCESS_'),$seedsModel->getError());
            }
        }else{
            $data = [];
            if($aId){
                $data=$seedsModel->getInfoData($aId);
            }

            $builder=new BackstageConfigBuilder();
            $builder->title($title.'种子')
                ->data($data)
                ->keyId('id')
                ->keyText('name',lang('_TITLE_'))
                ->keyMultiImage('cover', "图片")
                ->keyText('unit',"单位")
                ->keyText('stock', "库存", '默认为100')
                ->keyText('total_sales', "总销量", '默认为0')
                ->keyEditor('content',"描述",'','all',['width' => '700px', 'height' => '300px'])

                ->keyStatus()->keyDefault('status',1)
                ->keyInteger('sort',lang('_SORT_'))->keyDefault('sort',999)
                ->group('基础', ['id', 'name' ,'cover', 'unit', 'stock','total_sales','content','sort'])
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
    public function  seedstrash($page = 1, $r = 20,$model=''){
        $builder = new BackstageListBuilder();
        $seedsModel = new SeedsModel();
        $builder->clearTrash($model);
        $map = ['status' => -1];
        $data = $seedsModel->where($map)->page($page, $r)->select();
        $totalCount = $seedsModel->where($map)->count();
        $builder->title('种子回收站')->buttonRestore(url('seeds/setstatus'))
            ->buttonClear('seeds')
            ->data($data)
            ->keyText('name','名称')
            ->keyText('total_sales','总销量')
            ->keyText('sort',lang('_SORT_'))
            ->keyStatus()
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
            $this->error("请填写名称");
        }
        if(mb_strlen($data['content'],'utf-8')<20){
            $this->error(lang('_TIP_CONTENT_LENGTH_'));
        }

        return true;
    }
}