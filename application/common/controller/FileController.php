<?php
namespace app\common\controller;
use app\common\model\FileModel;
use app\common\model\PictureModel;

/**
 * 文件控制器
 * 主要用于下载模型的文件上传和下载
 */
class FileController extends BaseController
{

    /* 文件上传 */
    public function upload()
    {
        $return = ['code'=>1,'msg'=>lang('_SUCCESS_UPLOAD_'),'data'=>''];
        /* 调用文件上传组件上传文件 */
        $File = new FileModel();
        $file_driver = config('download_upload_driver');
        $info = $File->upload(
            $_FILES,
            config('download_upload'),
            config('download_upload_driver'),
            config("upload_{$file_driver}_config")
        );

        /* 记录附件信息 */
        if ($info) {
            $return['data'] = think_encrypt(json_encode($info['download']));
        } else {
            $return['code'] = 0;
            $return['msg'] = $File->getError();
        }

        /* 返回JSON数据 */
        $this->ajaxReturn($return);
    }


    public function downloadfile($id=null){
        if (empty($id) || !is_numeric($id)) {
            $this->error(lang('_ERROR_PARAM_').lang('_EXCLAMATION_'));
        }
        $fileModel = new FileModel();
        $info = $fileModel->find($id);
        if(empty($info)){
            $this->error(lang('_DOCUMENT_ID_INEXISTENT_').lang('_COLON_')."{$id}");
            return false;
        }
        $root ='.';
        $call = '';
        if(false === $fileModel->download($root, $info['id'], $call, $info['id'])){
            $this->error( $fileModel->getError());
        }
    }


    /**
     * 用于表单自动上传图片的通用方法
     */
    public function uploadfile()
    {
        $return = ['code' => 1, 'msg' => lang('_SUCCESS_UPLOAD_'), 'data' => ''];
        /* 调用文件上传组件上传文件 */
        $fileModel = new FileModel();
        $driver = modC('download_upload_driver','local','config');
        $driver = check_driver_is_exist($driver);
        $uploadConfig = get_upload_config($driver);

        $info = $fileModel->upload(
            $_FILES,
            config('download_upload'),
            $driver,
            $uploadConfig
        );

        /* 记录附件信息 */
        if ($info) {
            $return['data'] = $info;
        } else {
            $return['code'] = 0;
            $return['msg'] = $fileModel->getError();
        }

        /* 返回JSON数据 */
        $this->ajaxReturn($return);
    }


    /**
     * 上传图片
     */
    public function uploadpicture()
    {

        //TODO: 用户登录检测
        /* 返回标准数据 */
        $return = ['code' => 1, 'msg' =>lang('_SUCCESS_UPLOAD_'), 'data' => ''];

        /* 调用文件上传组件上传文件 */
        $Picture = new PictureModel();

        $driver = modC('picture_upload_driver','local','config');
        $driver = check_driver_is_exist($driver);

        $uploadConfig = get_upload_config($driver);

        $info = $Picture->upload(
            $_FILES,
            config('picture_upload'),
            $driver,
            $uploadConfig
        );
        //TODO:上传到远程服务器
        /* 记录图片信息 */
        if ($info) {
            $return['code'] = 1;
            if ($info['Filedata']) {
                $return = array_merge($info['Filedata'], $return);
            }
            if ($info['download']) {
                $return = array_merge($info['download'], $return);
            }
            /*适用于自动表单的图片上传方式*/
            if ($info['file'] || $info['files']) {
                $return['data']['file'] = $info['file']?$info['file']:$info['files'];
            }
            /*适用于自动表单的图片上传方式end*/
            $aWidth= input('width',0,'intval');
            $aHeight=   input('height',0,'intval');
            if($aHeight<=0){
                $aHeight='auto';
            }
            if($aWidth>0){
                $return['path_self']=getThumbImageById($return['id'],$aWidth,$aHeight);
            }
        } else {
            $return['code'] = 0;
            $return['msg'] = $Picture->getError();
        }

        /* 返回JSON数据 */
        $this->ajaxReturn($return);
    }

    public function uploadpicturebase64()
    {

        $aData = $_POST['data'];

        if ($aData == '' || $aData == 'undefined') {
            $this->ajaxReturn(['code'=>0,'msg'=>'参数错误']);
        }

        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $aData, $result)) {
            $base64_body = substr(strstr($aData, ','), 1);
            empty($aExt) && $aExt = $result[2];
        } else {
            $base64_body = $aData;
        }


        if(!in_array($aExt,['jpg','gif','png','jpeg'])){
            $this->ajaxReturn(['code'=>0,'msg'=>'非法操作,上传照片格式不符。']);
        }
        $hasPhp=base64_decode($base64_body);

        if (strpos($hasPhp, '<?php') !==false) {
            $this->ajaxReturn(['code' => 0, 'msg' => '非法操作']);
        }




        $pictureModel = new PictureModel();

        $md5 = md5($base64_body);
        $sha1 = sha1($base64_body);


        $check = $pictureModel->where(['md5' => $md5, 'sha1' => $sha1])->find();

        if ($check) {
            //已存在则直接返回信息
            $return['id'] = $check['id'];
            $return['path'] = render_picture_path($check['path']);
            $this->ajaxReturn([ 'code'=>1,'id'=>$return['id'],'path'=> $return['path'] ]);
        } else {
            //不存在则上传并返回信息
            $driver = modC('picture_upload_driver','local','config');
            $driver = check_driver_is_exist($driver);
            $date = date('Y-m-d');
            $saveName = uniqid();
            $savePath = '/upload/picture/' . $date . '/';

            $path = $savePath . $saveName . '.' . $aExt;
            if($driver == 'local'){
                //本地上传
                mkdir('.' . $savePath, 0777, true);
                $data = base64_decode($base64_body);
                $rs = file_put_contents('.' . $path, $data);
            }
            else{
                $rs = false;
                //使用云存储
                $name = get_addon_class($driver);
                if (class_exists($name)) {
                    $class = new $name();
                    if (method_exists($class, 'uploadBase64')) {
                        $path = $class->uploadBase64($base64_body,$path);
                        $rs = true;
                    }
                }
            }
            if ($rs) {
                $pic['type'] = $driver;
                $pic['path'] = $path;
                $pic['md5'] = $md5;
                $pic['sha1'] = $sha1;
                $pic['status'] = 1;
                $pic['create_time'] = time();
                $id = $pictureModel->insertGetId($pic);
                $this->ajaxReturn (['code'=>1,'id' => $id, 'path' => render_picture_path($path)]);
            } else {
                $this->ajaxReturn(['code'=>0,'图片上传失败。']);
            }

        }
    }

    /**
     * 用于兼容UM编辑器的图片上传方法
     */
    public function uploadPictureUM()
    {
        header("Content-Type:text/html;charset=utf-8");
        //TODO: 用户登录检测
        /* 返回标准数据 */
        $return = ['code' => 1, 'msg' => lang('_SUCCESS_UPLOAD_'), 'data' => ''];

        //实际有用的数据只有name和state，这边伪造一堆数据保证格式正确
        $originalName = 'u=2830036734,2219770442&fm=21&gp=0.jpg';
        $newFilename = '14035912861705.jpg';
        $filePath = 'upload\/20140624\/14035912861705.jpg';
        $size = '7446';
        $type = '.jpg';
        $status = 'success';
        $rs =[
            "originalName" => $originalName,
            'name' => $newFilename,
            'url' => $filePath,
            'size' => $size,
            'type' => $type,
            'state' => $status,
            'original' => $_FILES['upfile']['name']
        ];
        /* 调用文件上传组件上传文件 */
        $Picture = new PictureModel();

        $setting = config('editor_upload');
        $setting['rootPath']='./upload/editor/picture/';

        $driver = modC('picture_upload_driver','local','config');
        $driver = check_driver_is_exist($driver);
        $uploadConfig = get_upload_config($driver);

        $info = $Picture->upload(
            $_FILES,
            $setting,
            $driver,
            $uploadConfig
        ); //TODO:上传到远程服务器

        /* 记录图片信息 */
        if ($info) {
            $return['code'] = 1;
            if ($info['Filedata']) {
                $return = array_merge($info['Filedata'], $return);
            }
            if ($info['download']) {
                $return = array_merge($info['download'], $return);
            }
            $rs['state'] = 'SUCCESS';
            $rs['url'] = $info['upfile']['path'];
            if ($type == 'ajax') {
                echo json_encode($rs);
                exit;
            } else {
                echo json_encode($rs);
                exit;
            }

        } else {
            $return['code'] = 0;
            $return['msg'] = $Picture->getError();
        }

        /* 返回JSON数据 */
        $this->ajaxReturn($return);
    }


    public function uploadFileUE(){
        $return =['code' => 1, 'msg' => lang('_SUCCESS_UPLOAD_'), 'data' => ''];

        //实际有用的数据只有name和state，这边伪造一堆数据保证格式正确
        $originalName = 'u=2830036734,2219770442&fm=21&gp=0.jpg';
        $newFilename = '14035912861705.jpg';
        $filePath = 'upload\/20140624\/14035912861705.jpg';
        $size = '7446';
        $type = '.jpg';
        $status = 'success';
        $rs = array(
            'name' => $newFilename,
            'url' => $filePath,
            'size' => $size,
            'type' => $type,
            'state' => $status
        );

        /* 调用文件上传组件上传文件 */
        $File = new FileModel();

        $driver = modC('download_upload_driver','local','config');
        $driver = check_driver_is_exist($driver);
        $uploadConfig = get_upload_config($driver);

        $setting = config('editor_upload');
        $setting['rootPath']='./upload/editor/file/';


        $setting['exts'] = 'jpg,gif,png,jpeg,zip,rar,tar,gz,7z,doc,docx,txt,xml,xlsx,xls,ppt,pptx,pdf';
        $info = $File->upload(
            $_FILES,
            $setting,
            $driver,
            $uploadConfig
        );

        /* 记录附件信息 */
        if ($info) {
            $return['data'] = $info;

            $rs['original'] = $info['upfile']['name'];
            $rs['state'] = 'SUCCESS';
            $rs['url'] =  strpos($info['upfile']['savepath'], 'http://') === false ?  __ROOT__.$info['upfile']['savepath'].$info['upfile']['savename']:$info['upfile']['savepath'];
            $rs['size'] = $info['upfile']['size'];
            $rs['title'] = $info['upfile']['savename'];


            if ($type == 'ajax') {
                echo json_encode($rs);
                exit;
            } else {
                echo json_encode($rs);
                exit;
            }



        } else {
            $return['code'] = 0;
            $return['msg'] = $File->getError();
        }

        /* 返回JSON数据 */
        $this->ajaxReturn($return);
    }


    public function uploadAvatar(){

        $aUid = input('uid',0,'intval');

        mkdir ("./upload/avatar/".$aUid);


        $files = $_FILES;
        $setting  = config('picture_upload');

        $driver = modC('picture_upload_driver','local','config');
        $driver = check_driver_is_exist($driver);
        $uploadConfig = get_upload_config($driver);


        /* 上传文件 */
        $setting['rootPath'] = './upload/avatar';
        $setting['saveName'] = ['uniqid', '/'.$aUid.'/'];
        $setting['savepath'] = '';
        $setting['subName'] = '';
        $setting['replace'] = true;

        //sae下
        if (strtolower(config('picture_upload_driver'))  == 'sae') {
            // $config[]
            config(require_once(APP_PATH . 'config_sae.php'));

            $Upload = new \think\Upload($setting,config('picture_upload_driver'), [config('upload_sae_config')]);
            $info = $Upload->upload($files);

            $config=config('upload_sae_config');
            if ($info) { //文件上传成功，记录文件信息
                foreach ($info as $key => &$value) {
                    $value['path'] = $config['rootPath'] . 'avatar/' . $value['savepath'] . $value['savename']; //在模板里的url路径

                }
                /* 设置文件保存位置 */
                $this->insert[] = ['location', 'Ftp' === $driver ? 1 : 0];
            }
        }else{
            $Upload = new \think\Upload($setting, $driver, $uploadConfig);
            $info = $Upload->upload($files);
        }
        if ($info) { //文件上传成功，不记录文件
            $return['code'] = 1;
            if ($info['Filedata']) {
                $return = array_merge($info['Filedata'], $return);
            }
            if ($info['download']) {
                $return = array_merge($info['download'], $return);
            }
            /*适用于自动表单的图片上传方式*/
            if ($info['file']) {
                $return['data']['file'] = $info['file'];

                $path = $info['file']['url'] ? $info['file']['url'] : "./upload/avatar".$info['file']['savename'];
                $src = $info['file']['url'] ? $info['file']['url'] : __ROOT__."/uploads/avatar".$info['file']['savename'];
                $return['data']['file']['path'] =$path;
                $return['data']['file']['src']=$src;
                $size =  getimagesize($path);
                $return['data']['file']['width'] =$size[0];
                $return['data']['file']['height'] =$size[1];
                $return['data']['file']['time'] =time();
            }
        } else {
            $return['code'] = 0;
            $return['msg'] = $Upload->getError();
        }

        $this->ajaxReturn($return);
    }

    /**
     * 手机上传头像
     */
    public function uploadMobAvatarBase64()
    {
        $aData = $_POST['data'];

        if ($aData == '' || $aData == 'undefined') {
            $this->ajaxReturn(['code'=>0,'msg'=>'参数错误']);
        }

        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $aData, $result)) {
            $base64_body = substr(strstr($aData, ','), 1);
            empty($aExt) && $aExt = $result[2];
        } else {
            $base64_body = $aData;
        }

        $avatarModel = db('avatar');
        if(!in_array($aExt,['jpg','gif','png','jpeg'])){
            $this->ajaxReturn(['code'=>0,'msg'=>'非法操作,上传照片格式不符。']);
        }
        $hasPhp=base64_decode($base64_body);

        if (strpos($hasPhp, '<?php') !==false) {
            $this->ajaxReturn(['code' => 0, 'msg' => '非法操作']);
        }

        $driver = modC('picture_upload_driver','local','config');
        $driver = check_driver_is_exist($driver);
        $uid=is_login();
        $saveName = uniqid();


        $path = '/'.$uid .'/'. $saveName . '.' . $aExt;
        if($driver == 'local'){
            //本地上传
            mkdir ("./upload/avatar/".$uid,0777, true);

            $data = base64_decode($base64_body);
            $rs = file_put_contents('./upload/avatar' . $path, $data);
        }
        else{
            $rs = false;
            //使用云存储
            $name = get_addon_class($driver);
            if (class_exists($name)) {
                $class = new $name();
                if (method_exists($class, 'uploadBase64')) {
                    $path = $class->uploadBase64($base64_body,$path);
                    $rs = true;
                }
            }
        }
        if ($rs) {
            $count=$avatarModel->where(['uid'=>$uid])->count();
            $pic['driver'] = $driver;
            $pic['path'] = $path;
            $pic['uid'] = $uid;
            $pic['status'] = 1;
            $pic['create_time'] = time();
            if($count>0){
                $id=$avatarModel->where(['uid'=>$uid])->update($pic);
            }else{
                $id=$avatarModel->insertGetId($pic);
            }
            clean_query_user_cache($uid, 'avatars');
            $this->ajaxReturn (['code'=>1,'id' => $id, 'path' => render_picture_path($path)]);
        } else {
            $this->ajaxReturn(['code'=>0,'info'=>'图片上传失败。']);
        }


    }

}
