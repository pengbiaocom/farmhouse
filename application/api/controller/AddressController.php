<?php
/**
 * Created by PhpStorm.
 * User: 离殇<pengxuancom@164.com>
 */
namespace app\api\controller;

use think\Controller;
use think\Request;

class AddressController extends Controller{


    public function get_provinces(){

        $district = db("district")->where(['level'=>1,'is_show'=>0])->order("id asc")->select();

        return json($district);
    }

    public function get_citys(Request $request){
        $upid = $request->param('upid');

        if(empty($upid))  $upid = 110000;


        $district = db("district")->where(['level'=>2,'upid'=>$upid,'is_show'=>0])->order("id asc")->select();

        return json($district);
    }

    public function get_countys(Request $request){
        $upid = $request->param('upid');

        if(empty($upid))  $upid = 110100;

        $district = db("district")->where(['level'=>3,'upid'=>$upid,'is_show'=>0])->order("id asc")->select();

        return json($district);
    }


    public function get_streets(Request $request){
        $upid = $request->param('upid');

        if(empty($upid))  return json(['code'=>1,'msg'=>'缺少参数']);

        $district = db("district")->where(['level'=>4,'upid'=>$upid,'is_show'=>0])->order("id asc")->select();

        return json(['code'=>0,'data'=>$district]);
    }


    /**
     * 地址列表
     * @param Request $request
     * @return \think\response\Json
     */
    public function  address_list(Request $request){

        $uid = $request->param('uid');
        $limit = $request->param('limit', 10, 'intval');
        $page = $request->param('page', 1, 'intval');

        $address = db("receiving_address")->where(['uid'=>$uid])->order("create_time desc")->page($page, $limit)->select();

        if($address){
            foreach($address as $key=>$row){
                $pos_province = db("district")->where(['id'=>$row['pos_province']])->value("name");
                $pos_city = db("district")->where(['id'=>$row['pos_city']])->value("name");
                $pos_district = db("district")->where(['id'=>$row['pos_district']])->value("name");
                $street_id = db("district")->where(['id'=>$row['street_id']])->value("name");
                $address[$key]['address'] = $pos_province.$pos_city.$pos_district.$street_id.$row['pos_community'];
            }
            return json(['code'=>0,'msg'=>'有数据','data'=>$address,'paginate'=>array('page'=>sizeof($address) < 10 ? $page : $page+1, 'limit'=>$limit)]);
        }else{
            return json(['code'=>1,'msg'=>'没有地址']);
        }
    }

    /**
     * 得到地址详细
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_detail(Request $request){

        $id = $request->param('id');

        $detail = db("receiving_address")->where(['id'=>$id])->find();

        if($detail){
            $pos_province = db("district")->where(['id'=>$detail['pos_province']])->value("name");
            $pos_city = db("district")->where(['id'=>$detail['pos_city']])->value("name");
            $pos_district = db("district")->where(['id'=>$detail['pos_district']])->value("name");
            $street_id = db("district")->where(['id'=>$detail['street_id']])->value("name");

            $detail['province_name'] = $pos_province;
            $detail['city_name'] = $pos_city;
            $detail['district_name'] = $pos_district;
            $detail['street_name']  = $street_id;

            return json(['code'=>0,'msg'=>'查询成功','data'=>$detail]);
        }else{
            return json(['code'=>1,'msg'=>'没有地址']);
        }
    }

    /**
     * 添加地址
     * @param Request $request
     * @return \think\response\Json
     */
    public function add(Request $request){
        $param = $request->param();

        $data['uid'] = $param['uid'];
        $data['name'] = $param['name'];
        $data['mobile']=$param['tel'];
        $data['pos_province'] = $param['province_id'];
        $data['pos_city'] = $param['city_id'];
        $data['pos_district'] = $param['county_id'];
        $data['street_id'] = $param['street_id'];
        $data['pos_community'] = $param['address'];
        $data['is_default'] = $param['is_def'];
        $data['create_time'] = time();

        $old = db("receiving_address")->where(['uid'=>$data['uid'],'is_default'=>1])->find();

        if(empty($data['name'])){
            return json(['code'=>1,'msg'=>'请输入收货人姓名']);
        }else if(!preg_match("/^1[345678]{1}\d{9}$/",$data['mobile'])){
            return json(['code'=>1,'msg'=>'请输入正确的手机号']);
        }else if(empty($data['pos_province']) || empty($data['pos_city']) || empty($data['pos_district']) || empty($data['pos_community'])){
            return json(['code'=>1,'msg'=>'请填写收货地址']);
        }else{
            if(db("receiving_address")->insert($data)){
                if($data['is_default']==1){
                    db("receiving_address")->where(['uid'=>$data['uid'],'id'=>$old['id']])->update(['is_default'=>0]);
                }
                return json(['code'=>0,'msg'=>'新增地址成功']);
            }else{
                return json(['code'=>1,'msg'=>'新增地址失败']);
            }
        }
    }

    /**
     * 编辑地址
     * @param Request $request
     * @return \think\response\Json
     */
    public function edit(Request $request){
        $param = $request->param();

        $id = $param['id'];
        $data['uid'] = $param['uid'];
        $data['name'] = $param['name'];
        $data['mobile']=$param['tel'];
        $data['pos_province'] = $param['province_id'];
        $data['pos_city'] = $param['city_id'];
        $data['pos_district'] = $param['county_id'];
        $data['street_id'] = $param['street_id'];
        $data['pos_community'] = $param['address'];
        $data['is_default'] = $param['is_def'];

        $old = db("receiving_address")->where(['uid'=>$data['uid'],'is_default'=>1])->find();

        if(empty($data['name'])){
            return json(['code'=>1,'msg'=>'请输入收货人姓名']);
        }else if(!preg_match("/^1[345678]{1}\d{9}$/",$data['mobile'])){
            return json(['code'=>1,'msg'=>'请输入正确的手机号']);
        }else if(empty($data['pos_province']) || empty($data['pos_city']) || empty($data['pos_district']) || empty($data['pos_community'])){
            return json(['code'=>1,'msg'=>'请填写收货地址']);
        }else{
            if(db("receiving_address")->where(['id'=>$id])->update($data)){
                if($data['is_default']==1){
                    db("receiving_address")->where(['uid'=>$data['uid'],'id'=>$old['id']])->update(['is_default'=>0]);
                }
                return json(['code'=>0,'msg'=>'修改地址成功']);
            }else{
                return json(['code'=>1,'msg'=>'修改地址失败']);
            }
        }
    }

    /**
     * 删除
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function del(Request $request){
        $data = $request->param("id");

        if(empty($data['id']))  return json(['code'=>1,'msg'=>'缺少参数']);

        if(db("receiving_address")->where(['id'=>$data['id']])->delete()){
            return json(['code'=>0,'msg'=>'删除成功']);
        }else{
            return json(['code'=>1,'msg'=>'删除失败']);
        }
    }

    /**
     * 获取默认地址
     * @param Request $request
     * @return \think\response\Json
     */
    public function default_address(Request $request){

        $data = $request->param();

        if(empty($data['uid']))  return json(['code'=>1,'msg'=>'缺少参数']);

        $default_info = db("receiving_address")->where(['uid'=>$data['uid'],'is_default'=>1])->find();

        if($default_info){
            $pos_province = db("district")->where(['id'=>$default_info['pos_province']])->value("name");
            $pos_city = db("district")->where(['id'=>$default_info['pos_city']])->value("name");
            $pos_district = db("district")->where(['id'=>$default_info['pos_district']])->value("name");
            $street_id = db("district")->where(['id'=>$default_info['street_id']])->value("name");

            $default_info['province_name'] = $pos_province;
            $default_info['city_name'] = $pos_city;
            $default_info['district_name'] = $pos_district;
            $default_info['street_name']  = $street_id;
            return json(['code'=>0,'msg'=>'成功','data'=>$default_info]);
        }else{
            $default_info = db("receiving_address")->where(['uid'=>$data['uid']])->order("id desc")->find();
            if($default_info){
                return json(['code'=>0,'msg'=>'成功','data'=>$default_info]);
            }else{
                return json(['code'=>1,'msg'=>'失败,没有默认地址']);
            }

        }
    }

    /**
     * 设置默认地址
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function set_default(Request $request){
        $data = $request->param();
        if(empty($data['uid']) || empty($data['id']))  return json(['code'=>1,'msg'=>'缺少参数']);

        $old = db("receiving_address")->where(['uid'=>$data['uid'],'is_default'=>1])->find();

        if(db("receiving_address")->where(['uid'=>$data['uid'],'id'=>$data['id']])->update(['is_default'=>1])){
            db("receiving_address")->where(['uid'=>$data['uid'],'id'=>$old['id']])->update(['is_default'=>0]);
            return json(['code'=>0,'msg'=>'设置成功']);
        }else{
            return json(['code'=>1,'msg'=>'设置失败']);
        }

    }
}