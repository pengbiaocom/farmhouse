<?php
namespace app\common\taglib;

use think\template\TagLib;

/**
 * 标签库
 * Class Think
 * @package app\common\taglib
 */
class Think extends TagLib{
    // 标签定义
    protected $tags = [
        // 标签定义： attr 属性列表 close 是否闭合（0 或者1 默认1） alias 标签别名 level 嵌套层次
        'navmenu'      =>  ['attr' => 'field,name', 'close' => 1],//获取导航
        'query'   =>  ['attr'=>'sql,result','close'=>0],
    ];

    /* 导航列表 */
    public function tagNavmenu($tag, $content){
        $field  = empty($tag['field']) ? 'true' : $tag['field'];
        $tree   =   empty($tag['tree'])? false : true;
        $parse  = $parse   = '<?php ';
        $parse .= '$__NAV__ = model(\'Channel\')->lists('.$field.');';
        if($tree){
            $parse .= '$__NAV__ = list_to_tree($__NAV__, "id", "pid", "_");';
        }
        $parse .= '?><volist name="__NAV__" id="'. $tag['name'] .'">';
        $parse .= $content;
        $parse .= '</volist>';

        return $parse;
    }

    // sql查询
    public function tagQuery($tag,$content) {
        $sql       =    $tag['sql'];
        $result    =    !empty($tag['result'])?$tag['result']:'result';
        $parseStr  =    '<?php $'.$result.' = db()->query("'.$sql.'");';
        $parseStr .=    'if($'.$result.'):?>'.$content;
        $parseStr .=    "<?php endif;?>";
        return $parseStr;
    }
}