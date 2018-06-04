<?php
namespace app\common\widget;

use think\Controller;

class ShareWidget extends Controller{

    public function detailShare($data=[])
    {
        //支持参数“share_text”设置分享的文本内容
        $this->assign($data);
        return $this->fetch(T('application://common@widget/share'));
    }

} 