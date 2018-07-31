<?php
namespace app\common\model;

/**
 * 插件模型
 */

class MenuModel extends BaseModel {

	protected $rule = [
		'url'  =>  'require',
	];

	protected $message = [
		'name.require'  =>  'url必须填写',
	];

	protected $scene = [
		'add'   =>  ['url'],
		'edit'  =>  ['url'],
	];

	//获取树的根到子节点的路径
	public function getPath($id){
		$path = [];
		$nav = db("menu")->where("id={$id}")->field('id,pid,title,url')->find();
		$path[] = $nav;
		if($nav['pid'] >0){
			$path = array_merge($this->getPath($nav['pid']),$path);
		}
		return $path;
	}
}