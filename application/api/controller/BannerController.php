<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\AdvModel;
use app\common\model\AdvPosModel;

class BannerController extends Controller{

    /**
     * ���ù��  ����ͼ
     * @param Request $request
     * @return \think\response\Json
     */
    public function render(Request $request)
    {
        $param =$request->param();

        $name = $param['name'];
        $path = $param['path'];
        $advPosModel = new AdvPosModel();

        $pos = $advPosModel->getInfo($name, $path);

        //�����ڹ��λ�򴴽�
        if (empty($pos)) {
            empty($param['type']) && $param['type'] = 3;
            empty($param['status']) && $param['status'] = 1;
            empty($param['width']) && $param['width'] = '100px';
            empty($param['height']) && $param['height'] = '100px';
            empty($param['theme']) && $param['theme'] = 'all';
            empty($param['title']) && $param['title'] = $name;
            empty($param['margin']) && $param['margin'] = '';
            empty($param['padding']) && $param['padding'] = '';
            empty($param['data']) && $param['data'] = [];
            $param['name'] = $name;
            $param['path'] = $path;
            $param['data']=json_encode($param['data']);
            $pos['id'] = $advPosModel->allowField(true)->save($param);

            cache('adv_pos_by_pos_' . $path . $name, $pos);
        }
        $pos['type_text'] = $advPosModel->switchType($pos['type']);
        $data = json_decode($pos['data'], true);
        if (!empty($data)) {
            $pos = array_merge($pos, $data);
        }
        $advModel = new AdvModel();
        $list = $advModel->getAdvList($name, $path);

        print_r($list);

        if($list){
            return  json(['code'=>0,'msg'=>'���óɹ�','data'=>$list]);
        }else{
            return  json(['code'=>1,'msg'=>'����ʧ��','data'=>[]]);
        }
    }
}