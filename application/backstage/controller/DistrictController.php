<?php
namespace app\backstage\controller;

use app\common\model\DistrictModel;
use app\backstage\builder\BackstageConfigBuilder;
/**
 * 地区库
 * User: 丶陌路灬离殇 <pengxuancom@163.com>
 */
class DistrictController extends BackstageController{


    /**
     * 地区库列表
     * @return \think\response\View
     * @User 丶陌路灬离殇 <pengxuancom@163.com>
     */
    public function index(){
        $districtModel = new DistrictModel();
        $parentId = request()->param('id',0);
        $REQUEST = $this->request->param();
        $curClass = $districtModel->where(['id'=>$parentId])->find();
        if(!$curClass) $curClass = [['id'=>0,'name'=>'根目录']];

        $keyword = input('keyword','','op_t');
        if(!empty($keyword)){
            $map = "(id=".intval($keyword)." or name like '%".trim($keyword)."%')";
        }else{
            $map = 'upid='.$parentId;
        }

        $listRows = config('LIST_ROWS') > 0 ? config('LIST_ROWS') : 10;
        $list = $districtModel->where($map)->order('id asc')->paginate($listRows,false,['query'=>$REQUEST]);
        $page = $list->render();

        $curClassList = '<a href="'.url('backstage/District/index',array('id'=>0)).'">根目录</a> <span class="layui-box">&gt;</span>';
        $curClassList =$curClassList.$districtModel->adminTipOffset($parentId);
        $this->assign('meta_title','地区管理');
        $this->assign('curClassList',$curClassList);
        $this->assign('rows',$list);
        $this->assign('pageStr',$page);
        $this->assign('id',$parentId);
        // 记录当前列表页的cookie
        Cookie('__forward__', $_SERVER['REQUEST_URI']);
        return view();
    }

    /**
     *@ 添加页
     */
    public function add(){
        $districtModel = new DistrictModel();
        if(Request()->isPost()){
            $data['name']=input('post.name','','op_t');
            $data['level']=input('post.level',0,'intval');
            $data['upid']=input('post.upid',0,'intval');
            $data['is_show']=input('post.is_show',0,'intval');
            $this->check($data);
            if($districtModel->add($data)){
                $this->success("添加成功",Cookie('__forward__'));
            }else{
                $this->error("添加失败");
            }
        }else{
            $curclassId = input("id",0,'intval');
            $curclass = db("district")->where("id={$curclassId}")->find();
            if(empty($curclass)) $curclass = [['id'=>0,'name'=>'根目录','level'=>0]];
            $options[$curclass['id']]=$curclass['name'];
            $data = [];
            $show_text = ['隐藏','显示'];
            $builder=new BackstageConfigBuilder();
            $builder->title('新增')
                ->data($data)
                ->keyText('name','地区名称','用于显示的文字')
                ->keyText('level','级数','分层级数')->keyDefault('level',$curclass['level']+1)
                ->keySelect('upid','上级分类','',$options)
                ->keySelect('is_show','上级分类','',$show_text)
                ->buttonSubmit()->buttonBack();
            return $builder->show();
        }

    }

    /**
     *@ 修改页
     */
    public function edit(){
        $districtModel = new DistrictModel();
        
        if(Request()->isPost()){
            $data['id'] = input('post.id',0,'intval');
            $data['name']=input('post.name','','op_t');
            $data['level']=input('post.level',0,'intval');
            $data['upid']=input('post.upid',0,'intval');

            $this->check($data);
            if($districtModel->where('id', $data['id'])->update($data)){
                $this->success("修改成功",Cookie('__forward__'));
            }else{
                $this->error("修改失败");
            }
        }else{
            $id=input("id",0,'intval');
            $row = $districtModel->where("id={$id}")->find();
            $curclass = $districtModel->field('id,name,level')->where("id={$row['upid']}")->find();
            if(empty($curclass)) $curclass = [['id'=>0,'name'=>'根目录']];
            $options[$curclass['id']]=$curclass['name'];
            $builder=new BackstageConfigBuilder();
            $builder->title('编辑')
                ->keyId()
                ->data($row)
                ->keyText('name','地区名称','用于显示的文字')
                ->keyText('level','级数','分层级数')
                ->keySelect('upid','上级分类','',$options)
                ->buttonSubmit()->buttonBack();
            return $builder->show();
        }

    }

    /**
     *@ 删除功能
     */
    public function del(){
        $districtModel = new DistrictModel();
        $ids = Request()->param("ids/a");
        $ids = is_array($ids) ? implode(',', $ids) : $ids;
        if (empty($ids)) {
            $this->error("请选择要操作的数据!");
        }
        if($districtModel->del("id in (".$ids.")")){
            $this->success("删除成功",Cookie('__forward__'));
        }else{
            $this->error("删除失败");
        }
    }

    /**
     *@ 修改字段值
     */
    public function setval(){
        $data = Request()->param();
        $districtModel = new DistrictModel();
        if($districtModel->save([$data['name']=>$data['value']], function($query) use($data){
            $query->where('id', $data['id']);
        })){
            $this->success("操作成功",Cookie('__forward__'));
        }else{
            $this->error("操作失败");
        }
    }

    /**
     * 检查提交的数据
     * @param $arr
     */
    private function check(&$arr){
        if(!mb_strlen($arr['name'],'utf-8')){
            $this->error("请输入地理名称！");
        }
    }

    /**
     * 从表格中更新数据
     *
     */
    public function table_edit(){
        $data = Request()->param();
        $districtModel = new DistrictModel();
        $districtModel->updateFields($data);
        $this->success("更新成功",Cookie('__forward__'));
    }

    public function   get_province(){
        $district = db("district")->where(['level'=>1,'is_show'=>1])->order("id asc")->select();

        if($district){
            return json(['code'=>0,'msg'=>'ok','data'=>$district]);
        }else{
            return json(['code'=>1,'msg'=>'ok','data'=>$district]);
        }
    }

    public function   get_citys(){
        $upid = $this->request->param('upid');

        if(empty($upid))  $upid = 110000;

        $district = db("district")->where(['level'=>2,'upid'=>$upid,'is_show'=>1])->order("id asc")->select();

        if($district){
            return json(['code'=>0,'msg'=>'ok','data'=>$district]);
        }else{
            return json(['code'=>1,'msg'=>'ok','data'=>$district]);
        }
    }

    public function get_countys(){
        $upid = $this->request->param('upid');

        if(empty($upid))  $upid = 110100;

        $district = db("district")->where(['level'=>3,'upid'=>$upid,'is_show'=>1])->order("id asc")->select();

        if($district){
            return json(['code'=>0,'msg'=>'ok','data'=>$district]);
        }else{
            return json(['code'=>1,'msg'=>'ok','data'=>$district]);
        }
    }
}