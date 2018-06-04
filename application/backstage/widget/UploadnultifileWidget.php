<?php
namespace app\backstage\widget;
use think\Controller;


class UploadmultifileWidget extends Controller {

    public function render($attributes = array()){

        $attributes['id'] = $attributes['id']?$attributes['id']:$attributes['name'];
        $config = $attributes['config'];
        $class = $attributes['class'];
        $value = $attributes['value'];
        $name = $attributes['name'];
        $width = $attributes['width'] ? $attributes['width'] : 100;
        $height = $attributes['height'] ? $attributes['height'] : 100;
        $isLoadScript = $attributes['isLoadScript']?1:0;

        $config = $config['config'];

        $attributes['config'] = ['text' =>  lang('_FILE_SELECT_')];

        $files_ids=explode(',',$value);
        if($files_ids){
            foreach ($files_ids as $v) {
                $files[]=db('File')->find($v);
            }
            unset($v);

        }
        $this->assign('isLoadScript',$isLoadScript);
        $this->assign('files',$files);
        $this->assign($attributes);
        return $this->fetch('widget/uploadmultifile');

    }
}