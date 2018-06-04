<?php
namespace app\common\controller;

use app\common\model\ContentHandlerModel;

class PublicController extends HomeBaseController{

    public function getVideo(){
        $aLink = input('link');
        $contentHandler = new ContentHandlerModel();
        $this->ajaxReturn(['data'=>$contentHandler->getVideoInfo($aLink)]);
    }
}