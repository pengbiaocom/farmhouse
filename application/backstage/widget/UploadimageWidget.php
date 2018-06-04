<?php
namespace app\backstage\widget;
use think\Controller;

/**
 * Class UploadImageWidget  上传图片组件
 * @package backstage\widget
 */
class UploadimageWidget extends Controller
{

    public function render($attributes = array())
    {

        $attributes_id = $attributes['id'];
        $config = $attributes['config'];
        $class = $attributes['class'];
        $value = $attributes['value'];
        $name = $attributes['name'];
        $width = $attributes['width'] ? $attributes['width'] : 100;
        $height = $attributes['height'] ? $attributes['height'] : 100;

        $config = $config['config'];

        $id = $attributes_id;
        $attributes['config'] = ['text' => lang('_FILE_SELECT_')];

        if (intval($value) != 0) {
            $url = getThumbImageById($value, $width, $height);
            $img = '<img src="' . $url . '"/>';
        } else {
            $img = '';
        }

        $this->assign('img',$img);
        $this->assign($attributes);
        return $this->fetch('widget/uploadimage');
    }
} 