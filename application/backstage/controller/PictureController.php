<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageConfigBuilder;
use app\backstage\builder\BackstageListBuilder;
use app\common\model\PictureModel;

class PictureController extends BackstageController
{
    /**
     * 图片水印设置
     */
    public function config()
    {

        $builder = new BackstageConfigBuilder();
        $data = $builder->handleConfig();

        $data['WATER_OPEN']===null&&$data['WATER_OPEN']=0;
        !is_file($data['WATER_IMAGE'])&& $data['WATER_IMAGE']=__ROOT__.'/static/backstage/images/water.png';
        $data['WATER_SPACE']===null&&$data['WATER_SPACE']=9;

        $builder->title('图片水印设置');
        $this->assign('data',$data);
        return $this->fetch();

    }

    public function uploadwater(){
        $config = [
            'maxSize'    =>    3145728,
            'rootPath'   =>    '/upload/',
            'savePath'   =>    'water/',
            'saveName'   =>    'water',
            'exts'       =>    ['jpg', 'gif', 'png', 'jpeg'],
            'autoSub'    =>    true,
            'subName'    =>    '',
            'replace'=> true,
        ];

        $upload = new \think\Upload($config);// 实例化上传类
        $info   =   $upload->upload($_FILES);
        if($info){
            $return['status'] = 1;
            $return['url'] = '/upload/water/'.$info['download']['savename'];
        }else{
            $return['status'] = 0;
            $return['info'] = '上传失败';
        }

        $this->ajaxReturn($return);
    }

    public function picturelist($r=20)
    {
        $pictureModel = new PictureModel();
        list($list,$page)= $pictureModel->getPictureList($r);
        foreach($list as &$val){
            $val['image']=$val['id'];
        }

        $builder=new BackstageListBuilder();
       return  $builder->title('图片列表')
            ->setStatusUrl(url('Picture/setstatus'))
            ->buttonEnable()->buttonDisable()->buttonDelete()
            ->keyId()
            ->keyCreateTime('create_time','上传时间')
            ->keyText('type','存储空间')
            ->keyText('path','存储路径')
            ->keyText('url','图片链接')
            ->keyText('md5','文件md5编码')
            ->keyText('sha1','文件sha1编码')
            ->keyStatus()
            ->keyImage('image','图片')
            ->data($list)
            ->pagination($page)
            ->show();
    }

    public function setstatus($ids,$status=1)
    {
        $pictureModel = new PictureModel();
        $builder=new BackstageListBuilder();
        !is_array($ids)&&$ids=explode(',',$ids);
        if($status==-1){
            $list= $pictureModel->getList(['id'=>['in',$ids]]);
            foreach($list as $val){
                $path=$val['path'];
                if($val['type']=='local'){
                    $path='.'.$path;
                    @mkdir($path,777,true);
                    unlink($path);
                    $this->_deleteThumb($path);
                }else{
                    $file_name=explode('/',$path);
                    $file_name=$file_name[count($file_name)-1];
                    delete_driver_upload_file($file_name,$val['type']);
                }
            }
            $builder->doDeleteTrue('Picture',$ids);
        }else{
            $builder->doSetStatus('Picture',$ids,$status);
        }
    }

    private function _deleteThumb($path)
    {
        $file_name=explode('/',$path);
        $file_name=$file_name[count($file_name)-1];
        $dir=str_replace($file_name,'',$path);
        $file_name=explode('.',$file_name);
        $file_info['name']=$file_name[0];
        $file_info['ext']=$file_name[1];
        if(is_dir($dir)){
            if ($dh = opendir($dir)){
                while(($file=readdir($dh))!==false){
                    if(strpos($file,$file_info['name'])!==false){
                        $file_path=$dir.$file;
                        @mkdir($file_path,777,true);
                        unlink($file_path);
                    }
                }
                closedir($dh);
            }
        }
        return true;
    }
}