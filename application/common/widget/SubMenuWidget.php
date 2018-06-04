<?php
namespace app\common\widget;

use think\Controller;

/**通用二级子菜单组件
 * Class SubMenuWidget
 * @package common\widget
 */
class SubMenuWidget extends Controller
{
    public function render($menu_list, $current, $brand=null, $id)
    {
        //tpl仅作为例子
        $tpl = [
            'left' =>
                [
                    ['tab'=>'home','title'=>'顶级菜单A','href'=>url('blog/index/index'),'icon'=>'home'],
                    ['tab'=>'category_1','title'=>'顶级菜单B','href'=>url('blog/article/lists',['category'=>1])],
                    ['tab'=>'chuangye','title'=>'顶级菜单C','href'=>url('blog/article/lists',['category'=>42]),
                        'children'=>[
                            ['tab'=>'child_1','title'=>'子菜单1','href'=>url('blog/index/index'),'icon'=>'home'],
                            ['tab'=>'child_2','title'=>'子菜单2','href'=>url('blog/article/list',['category'=>1])]
                        ]
                    ],
                ],
            'right' => [
                [
                    ['tab'=>'user','title'=>'用户','href'=>url('blog/index/index'),'icon'=>'user',
                        'children' =>[
                            ['tab'=>'child_1','title'=>'个人中心','href'=>url('blog/index/index',['icon'=>'home'])],
                            ['tab'=>'child_2','title'=>'注销','href'=>url('blog/index/index')]
                        ]
                    ],
                    ['title'=>'我的财富'],
                    ['title'=>'我的订单','href'=>url('blog/index/index')]
                ]
            ]
        ];
        $this->assign('current', $current);
        $this->assign('menu_list', $menu_list);
        $brand=$brand==null?Request()->module():$brand;
        if(is_string($brand)){
            $new_brand['title']=$brand;
        }else{
            $new_brand=$brand;
        }
        $this->assign('brand', $new_brand);
        return $this->fetch(T('application://common@widget/menu'));
    }
}