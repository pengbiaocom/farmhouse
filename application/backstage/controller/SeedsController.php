<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageConfigBuilder;
use app\backstage\builder\BackstageListBuilder;
use app\common\model\SeedsModel;
use app\common\model\SeedsUserModel;

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
            ->keyText('price','价格')
            ->keyText('unit','单位')
            ->keyText('sum_exp','总经验')
            ->keyText('stock', '库存')
            ->keyText('sort',lang('_SORT_'))
            ->keyStatus()->keyUpdateTime()
            ->keyDoActionEdit('seeds/edit?id=###');
        $builder->pagination($totalCount);
        return $builder->show();
    }
    
    /**
    * 领用的数据
    * @date: 2018年9月11日 下午4:28:33
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function seedUser(){
        $r = config("LIST_ROWS");
        $seedsUserModel = new SeedsUserModel();

        $map['pay_status'] = 1;
        list($list,$totalCount)=$seedsUserModel->getListByPage($map,'status desc,create_time desc','*',$r);
        
        if($list){
            foreach($list as $key=>$row){
                $list[$key]['nickname'] = get_nickname($row['uid']);
                
                $seeds = db('seeds')->find($row['sid']);
                $list[$key]['seeds_name'] = $seeds['name'];
                $list[$key]['status'] = $row['status'] == 1 ? '已长成' : '成长中';
                $list[$key]['update_time'] = date('Y-m-d H:i:s', $row['update_time']);
            }
        }
        
        $this->assign('list', $list);
        $this->assign('_page',$totalCount);
        return $this->fetch();
    }
    
    /**
    * 打印
    * @date: 2018年9月26日 下午1:55:15
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function seedsuseredit(){
        $id = $this->request->param('id', 0, 'intval');
        
        $seedsUserModel = new SeedsUserModel();
        $info = $seedsUserModel->getInfoData($id);
        $info['nickname'] = get_nickname($info['uid']);
        $info['seeds'] = db('seeds')->find($row['sid']);
        
        //获取收货地址
        $address = db('receiving_address')
            ->alias('address')
            ->field('address.name,address.mobile,address.pos_community,province.name as province_name,city.name as city_name,district.name as district_name,street.name as street_name')
            ->join('__DISTRICT__ province', 'address.pos_province = province.id', 'LEFT')
            ->join('__DISTRICT__ city', 'address.pos_city = city.id', 'LEFT')
            ->join('__DISTRICT__ district', 'address.pos_district = district.id', 'LEFT')
            ->join('__DISTRICT__ street', 'address.street_id = street.id', 'LEFT')
            ->order('address.is_default desc')
            ->where('address.uid', $info['uid'])
            ->find();
        
        $this->assign('address', $address);
        $this->assign('info', $info);
        return $this->fetch();
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
            $data['price']=input('post.price','','op_t');
            $data['sum_exp']=input('post.sum_exp','','op_t');
            $data['cover']=input('post.cover',0,'op_t');
            $data['adult_cover']=input('post.adult_cover',0,'op_t');
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
                ->keyMultiImage('cover', "幼年图片")
                ->keyMultiImage('adult_cover', "成年图片")
                ->keyText('price',"价格")
                ->keyText('unit',"单位")
                ->keyText('sum_exp',"总经验")
                ->keyText('stock', "库存", '默认为100')
                ->keyText('total_sales', "总销量", '默认为0')
                ->keyEditor('content',"描述",'','all',['width' => '700px', 'height' => '300px'])

                ->keyStatus()->keyDefault('status',1)
                ->keyInteger('sort',lang('_SORT_'))->keyDefault('sort',999)
                ->group('基础', ['id', 'name' ,'cover','adult_cover', 'price','unit','sum_exp', 'stock','total_sales','content','sort'])
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