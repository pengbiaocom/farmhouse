<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageListBuilder;
use app\common\model\OrderModel;

class OrderController extends BackstageController{

    public function   index(){
        $r = config("LIST_ROWS");
        $orderModel = new OrderModel();

        $keyword = input('keyword','','op_t');
        if(!empty($keyword)){
            $map[] = "(name like '%".$keyword."%' or id=".intval($keyword)." )";
        }

        $map['status']=1;

        list($list,$totalCount)=$orderModel->getListByPage($map,'create_time desc','*',$r);

        $statustext = [0=>"未发货",1=>'已发货',2=>'已收货',3=>'已完成'];
        if($list){
            foreach($list as $key=>$row){
                $list[$key]['statustext'] =$statustext[$row['status']];
                $list[$key]['refundtext'] = $row['refund']==0 ? '未退还':'已退还';
            }
        }

        $builder=new BackstageListBuilder();
        $builder->title('订单列表')
            ->data($list)
            ->setSearchPostUrl(url('order/index'))
            ->searchText('','keyword','text','订单名称/编号')
            ->buttonDelete(url('order/edit'),'退款')
            ->keyId()
            ->keyText('name','商品名称')
            ->keyText('uid','用户名称')
            ->keyText('price','价格')
            ->keyText('product_num','商品价格')
            ->keyText('statustext','状态')
            ->keyText('refundtext','退款')
            ->keyCreateTime()
            ->keyDoActionEdit('order/edit?id=###','退还');
        $builder->pagination($totalCount);
        return $builder->show();
    }

    public function edit(){

    }
}