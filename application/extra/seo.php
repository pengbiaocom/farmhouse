<?php
//读取SEO规则
function get_seo_meta($vars,$seo)
{
    $serRuleModel = new \app\common\model\SeoRuleModel();
    //获取还没有经过变量替换的META信息
    $meta = $serRuleModel->getMetaOfCurrentPage($seo);

    //返回被替换的META信息
    return $meta;
}
