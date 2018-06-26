<?php
/**
 * Created by PhpStorm.
 * User: 离殇<pengxuancom@164.com>
 */
namespace app\api\controller;

use think\Controller;
use think\Request;

class AddressController extends Controller{

    /**
     * 地址列表
     * @param Request $request
     * @return \think\response\Json
     */
    public function  address_list(Request $request){

        $uid = $request->param('uid');

        $address = db("receiving_address")->where(['uid'=>$uid])->order("create_time desc")->select();

        if($address){
            return json(['code'=>0,'msg'=>'有数据','data'=>$address]);
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
        $data['mobile']=$param['mobile'];
        $data['pos_province'] = $param['pos_province'];
        $data['pos_city'] = $param['pos_city'];
        $data['pos_district'] = $param['pos_district'];
        $data['pos_community'] = $param['pos_community'];
        if(db("receiving_address")->where(['uid'=>$data['uid']])->count()==0){
            $data['is_default'] = 1;
        }
        if(empty($data['name'])){
            return json(['code'=>1,'msg'=>'请输入收货人姓名']);
        }else if(!preg_match("/^1[345678]{1}\d{9}$/",$data['mobile'])){
            return json(['code'=>1,'msg'=>'请输入正确的手机号']);
        }else if(empty($data['pos_province']) || empty($data['pos_city']) || empty($data['pos_district']) || empty($data['pos_community'])){
            return json(['code'=>1,'msg'=>'请填写收货地址']);
        }else{
            if(db("receiving_address")->insert($data)){
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
        $data['mobile']=$param['mobile'];
        $data['pos_province'] = $param['pos_province'];
        $data['pos_city'] = $param['pos_city'];
        $data['pos_district'] = $param['pos_district'];
        $data['pos_community'] = $param['pos_community'];
        if(empty($data['name'])){
            return json(['code'=>1,'msg'=>'请输入收货人姓名']);
        }else if(!preg_match("/^1[345678]{1}\d{9}$/",$data['mobile'])){
            return json(['code'=>1,'msg'=>'请输入正确的手机号']);
        }else if(empty($data['pos_province']) || empty($data['pos_city']) || empty($data['pos_district']) || empty($data['pos_community'])){
            return json(['code'=>1,'msg'=>'请填写收货地址']);
        }else{
            if(db("receiving_address")->where(['id'=>$id])->update($data)){
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
            return json(['code'=>0,'msg'=>'成功','data'=>$default_info]);
        }else{
            return json(['code'=>1,'msg'=>'失败,没有默认地址']);
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