<?php
namespace app\common\model;

use think\Upload;

/**
 * 文件模型
 * 负责文件的下载和上传
 */

class FileModel extends BaseModel{

    /**
     * 文件模型自动完成
     * @var array
     */
    protected  $insert = ['create_time'];

    protected  function setCreateTimeAttr(){
        return time();
    }

    /**
     * 文件上传
     * @param  array  $files   要上传的文件列表（通常是$_FILES数组）
     * @param  array  $setting 文件上传配置
     * @param  string $driver  上传驱动名称
     * @param  array  $config  上传驱动配置
     * @return array           文件上传成功后的信息
     */
    public function upload($files, $setting, $driver = 'Local', $config = null){
        /* 上传文件 */
        $setting['callback'] = [$this, 'isFile'];
        $setting['removeTrash'] = [$this, 'removeTrash'];
        $Upload = new Upload($setting, $driver, $config);
        $info    = $Upload->upload($files);

        /* 设置文件保存位置 */
        $this->insert[] = ['location','ftp'===strtolower($driver)?1:0];

        if($info){ //文件上传成功，记录文件信息
            foreach ($info as $key => &$value) {
                /* 已经存在文件记录 */
                if(isset($value['id']) && is_numeric($value['id'])){
                    continue;
                }
                if(strtolower($driver) != 'local'){
                    $value['savepath'] =$value['url'];
                }else{
                    $value['savepath'] = str_replace('.','',$setting['rootPath']).$value['savepath'];
                }

                $value['driver'] = $driver;
                /* 记录文件信息 */
                if(($id = $this->allowField(true)->insertGetId($value))){
                    $value['id'] = $id;
                } else {
                    //TODO: 文件上传成功，但是记录文件信息失败，需记录日志
                    unset($info[$key]);
                }
            }
            return $info; //文件上传成功
        } else {
            $this->error = $Upload->getError();
            return false;
        }
    }

    /**
     * 下载指定文件
     * @param  number  $root 文件存储根目录
     * @param  integer $id   文件ID
     * @param  string   $args     回调函数参数
     * @return boolean       false-下载失败，否则输出下载文件
     */
    public function download($root, $id, $callback = null, $args = null){
        /* 获取下载文件信息 */
        $file = $this->find($id);
        if(!$file){
            $this->error = lang('_NO_THIS_FILE_IS_NOT_THERE_WITH_EXCLAMATION_');
            return false;
        }

        /* 下载文件 */
        if($file['driver'] == 'local'){
            $file['rootpath'] = $root;
            return $this->downLocalFile($file, $callback, $args);
        }else{
            redirect($file['savepath']);
        }

    }

    /**
     * 检测当前上传的文件是否已经存在
     * @param  array   $file 文件上传数组
     * @return boolean       文件信息， false - 不存在该文件
     */
    public function isFile($file){
        if(empty($file['md5'])){
            throw new \Exception('缺少参数:md5');
        }
        /* 查找文件 */
        $map = ['md5' => $file['md5'],'sha1'=>$file['sha1']];
        return $this->field(true)->where($map)->find();
    }

    /**
     * 下载本地文件
     * @param  array    $file     文件信息数组
     * @param  callable $callback 下载回调函数，一般用于增加下载次数
     * @param  string   $args     回调函数参数
     * @return boolean            下载失败返回false
     */
    private function downLocalFile($file, $callback = null, $args = null){
        $path = $file['rootpath'].$file['savepath'].$file['savename'];
        if(is_file($path)){
            /* 调用回调函数新增下载数 */
            is_callable($callback) && call_user_func($callback, $args);

            /* 执行下载 */ //TODO: 大文件断点续传
            header("Content-Description: File Transfer");
            header('Content-type: ' . $file['type']);
            header('Content-Length:' . $file['size']);
            if (preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT'])) { //for IE
                header('Content-Disposition: attachment; filename="' . rawurlencode($file['name']) . '"');
            } else {
                header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
            }
            readfile($path);
            exit;
        } else {
            $this->error = lang('_FILE_HAS_BEEN_DELETED_WITH_EXCLAMATION_');
            return false;
        }
    }

    /**
     * 清除数据库存在但本地不存在的数据
     * @param $data
     */
    public function removeTrash($data){
        $this->where(['id'=>$data['id']])->delete();
    }

}
