<?php
namespace addons\SuperLinks\Controller;
use app\backstage\controller\AddonsController;
use think\Controller;

class SuperLinksController extends Controller{

	/* 添加友情连接 */
	public function add(){
		$current = url('/backstage/addons/adminlist/name/SuperLinks');
		$this->assign('current',$current);
		$this->assign('info',[]);
		$this->assign('meta_title','添加友情链接');
		echo  $this->fetch(T('addons://SuperLinks@SuperLinks/edit'));
	}
	
	/* 编辑友情连接 */
	public function edit(){
		$id     =   input('id','');
		$current = url('/backstage/addons/adminlist/name/SuperLinks');
		$objModel = get_Addons_model('SuperLinks');
		$obj                 = new $objModel;
		$detail = $obj->get($id);
		$this->assign('info',$detail);
		$this->assign('current',$current);
		$this->assign('meta_title','修改友情链接');
		echo $this->fetch(T('addons://SuperLinks@SuperLinks/edit'));
	}
	
	/* 禁用友情连接 */
	public function forbidden(){
		$this->assign('meta_title','禁用友情链接');
		$id     =   input('id','');
		$objModel = get_Addons_model('SuperLinks');
		$obj                 = new $objModel;
		if($obj->forbidden($id)){
			$this->success('成功禁用该友情连接');
		}else{
			$this->error($obj->getError());
		}
	}
	
	/* 启用友情连接 */
	public function off(){
		$this->assign('meta_title','启用友情链接');
		$id     =   input('id','');
		$objModel = get_Addons_model('SuperLinks');
		$obj                 = new $objModel;
		if($obj->off($id)){
			$this->success('成功启用该友情连接');
		}else{
			$this->error($obj->getError());
		}
	}
	
	/* 删除友情连接 */
	public function del(){
		$this->assign('meta_title','删除友情链接');
		$id     =   input('id','');
		$objModel = get_Addons_model('SuperLinks');
		$obj                 = new $objModel;
		if($obj->del($id)){
			$this->success('删除成功');
		}else{
			$this->error($obj->getError());
		}
	}
	
	/* 更新友情连接 */
	public function update(){
		$this->assign('meta_title','更新友情链接');
		$objModel = get_Addons_model('SuperLinks');
		$obj                 = new $objModel;
		$res = $obj->updates();
		if(!$res){
			$this->error($obj->getError());
		}else{
			if($res['id']){
				$this->success('更新成功',  Cookie('__forward__'));
			}else{
				$this->success('新增成功',  Cookie('__forward__'));
			}
		}
	}
	/*多选改变启/禁用*/
	public function savestatus(){
		$status     =   input('status','');
		$ids=$_REQUEST['id'];
		$map['id']=$ids;
		$count=count($ids);
		$objModel = get_Addons_model('SuperLinks');
		$obj                 = new $objModel;
		if($status==1){
			foreach($ids as $v){
				if($obj->off($v)){
					$flag=1;
				}
				else{
					$flag=0;
				}
			}
			if($flag==0) {
				$this->error($obj->getError());
			}else{
				$this->success('成功启用友情连接');
			}

		}
		else{
			foreach($ids as $v){
				if($obj->forbidden($v)){
					$flag=1;
				}
				else{
					$flag=0;
				}
			}
			if($flag==1){
				$this->success('成功禁用友情连接');
			}else{
				$this->error($obj->getError());
			}
		}
	}
}
