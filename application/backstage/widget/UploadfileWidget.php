<?php
namespace app\backstage\widget;
use think\Controller;


class UploadfileWidget extends Controller {

    public function render($attributes = array()){

        $attributes['id'] = $attributes['id']?$attributes['id']:$attributes['name'];
        $config = $attributes['config'];
        $class = $attributes['class'];
        $value = $attributes['value'];
        $name = $attributes['name'];
        $width = $attributes['width'] ? $attributes['width'] : 100;
        $height = $attributes['height'] ? $attributes['height'] : 100;
        $isLoadScript = $attributes['isLoadScript']?1:0;
        //$filetype = $this->rules['filetype'];

        $config = $config['config'];

        $attributes['config'] = ['text' => lang('_FILE_SELECT_')];

        if($value){
            $file=db('File')->find($value);
            $this->assign('file',$file);

        }
        $this->assign('isLoadScript',$isLoadScript);
        $this->assign($attributes);
        return $this->fetch('widget/uploadfile');

    }
}