<?php
namespace app\news\widget;

use app\news\model\NewsCategoryModel;
use app\news\model\NewsModel;
use think\Controller;

class HotWidget extends Controller{
    /* 显示指定分类的同级分类或子分类列表 */
    public function lists($category=0, $timespan = 604800, $limit = 5)
    {
        $newsCategoryModel = new NewsCategoryModel();
        $newsModel = new NewsModel();
        $map['status']=1;
        if ($category != 0) {
            $cates=$newsCategoryModel->getCategoryList(['pid'=>$category,'status'=>1]);
            $cates=array_column($cates,'id');
            $map['category']= ['in',array_merge([$category],$cates)];
        }
        $map['update_time']= ['gt',time()-$timespan];//一周以内
        $lists = $newsModel->getList($map,'view desc',5,'id,title,cover,uid,create_time,view');
        foreach($lists as &$val){
            $val['user']=query_user(['space_url','nickname'],$val['uid']);
        }
        unset($val);
        $this->assign('lists', $lists);
        $this->assign('category',$category);
        return $this->fetch(T('application://news@widget/hot'));
    }
} 