<?php
namespace addons\SuperLinks\Model;
use think\Model;

/**
 * 分类模型
 */
class SuperLinksModel extends Model{

	/* 自动完成规则 */
	protected $auto = [
		'create_time'
	];

	protected function setCreateTimeAttr(){
		$create_time    =   input('post.create_time');
		return $create_time?strtotime($create_time):time();
	}

	public function getStatusTextAttr($value,$data)
	{
		$status = [0=>'禁用',1=>'正常'];
		return $status[$data['status']];
	}

	public function getTypeTextAttr($value,$data)
	{
		$status = [2=>'普通连接',1=>'图片连接'];
		return $status[$data['type']];
	}

	public function getCreateTimeAttr($value,$data)
	{
		return date('Y-m-d', $data['create_time']);
	}

	/*  展示数据  */
	public function linkList(){
		$link = $this->where('status = 1')->order('level desc,id asc')->select();
		foreach($link as $key=>$val){
			if($val['type'] == 1){//图片连接
				$data['picture'][$key] = $val;
				$cover = db('picture')->find($val['cover_id']);
				$data['picture'][$key]['path'] = $cover['path'];
			}else{
				$data['writing'][$key] = $val;
				$cover = db('picture')->find($val['cover_id']);
				$data['writing'][$key]['path'] = $cover['path'];
			}
		}
		return $data;
	}

	/* 获取编辑数据 */
	public function detail($id){
		$data = $this->find($id);
		return $data;
	}

	/* 禁用 */
	public function forbidden($id){
		return $this->save(['id'=>$id,'status'=>'0']);
	}

	/* 启用 */
	public function off($id){
		return $this->save(['id'=>$id,'status'=>'1']);
	}

	/* 删除 */
	public function del($id){
		return $this->where(['id'=>$id])->delete();
	}

	/**
	 * 新增或更新一个文档
	 * @return boolean fasle 失败 ， int  成功 返回完整的数据
	 */
	public function updates(){
         $data = Request()->param();
		/* 添加或新增基础内容 */
		if(empty($data['id'])){ //新增数据
			$id = $this->allowField(true)->save($data); //添加基础内容
			if(!$id){
				$this->error = '新增广告内容出错！';
				return false;
			}
		} else { //更新数据
			$status = $this->allowField(true)->isUpdate(true)->data($data,true)->save(); //更新基础内容
			if(false === $status){
				$this->error = '更新广告内容出错！';
				return false;
			}
		}

		//内容添加或更新完成
		return $data;

	}
}