<?php
// +----------------------------------------------------------------------
// | i友街 [ 新生代贵州网购社区 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2014 http://www.iyo9.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: i友街 <iyo9@iyo9.com> <http://www.iyo9.com>
// +----------------------------------------------------------------------
// 

/**
 * 中国省市区三级联动插件
 * @author i友街
 */

namespace addons\ChinaCity\Controller;
use app\home\controller\AddonsController;

class ChinaCityController extends AddonsController{
	
	//获取中国省份信息
	public function getProvince(){
		if (Request()->isAjax()){
			$pid = input('pid');  //默认的省份id

			if( !empty($pid) ){
				//$map['id'] = $pid;
			}
			$map['level'] = 1;
			$map['upid'] = 0;
			$objModel = get_Addons_model('District','ChinaCity');
			$districtModel                 = new $objModel;
			$list = $districtModel->_list($map);

			$data = "<option value =''>-省份-</option>";
			foreach ($list as $k => $vo) {
				$data .= "<option ";
				if( $pid == $vo['id'] ){
					$data .= " selected ";
				}
				$data .= " value ='" . $vo['id'] . "'>" . $vo['name'] . "</option>";
			}
			$this->ajaxReturn($data);
		}
	}

	//获取城市信息
	public function getCity(){
		if (Request()->isAjax()){
			$cid = input('cid');  //默认的城市id
			$pid = input('pid');  //传过来的省份id

			if( !empty($cid) ){
				//$map['id'] = $cid;
			}
			$map['level'] = 2;
			$map['upid'] = $pid;
			$objModel = get_Addons_model('District','ChinaCity');
			$districtModel                 = new $objModel;
			$list = $districtModel->_list($map);

			$data = "<option value =''>-城市-</option>";
			foreach ($list as $k => $vo) {
				$data .= "<option ";
				if( $cid == $vo['id'] ){
					$data .= " selected ";
				}
				$data .= " value ='" . $vo['id'] . "'>" . $vo['name'] . "</option>";
			}
			$this->ajaxReturn($data);
		}
	}

	//获取区县市信息
	public function getDistrict(){
		if (Request()->isAjax()){
			$did = input('did');  //默认的城市id
			$cid = input('cid');  //传过来的城市id

			if( !empty($did) ){
				//$map['id'] = $did;
			}
			$map['level'] = 3;
			$map['upid'] = $cid;
			$objModel = get_Addons_model('District','ChinaCity');
			$districtModel                 = new $objModel;
			$list = $districtModel->_list($map);

			$data = "<option value =''>-州县-</option>";
			foreach ($list as $k => $vo) {
				$data .= "<option ";
				if( $did == $vo['id'] ){
					$data .= " selected ";
				}
				$data .= " value ='" . $vo['id'] . "'>" . $vo['name'] . "</option>";
			}
			$this->ajaxReturn($data);
		}
	}

}