<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageListBuilder;
use app\common\model\OrderModel;

class OrderController extends BackstageController{

    public function   index(){
        $r = config("LIST_ROWS");
        $orderModel = new OrderModel();

        $map = array();
        $keyword = input('keyword','','op_t');
        if(!empty($keyword)){
            $map['out_trade_no'] = array('like', $keyword);
        }

        list($list,$totalCount)=$orderModel->getListByPage($map,'create_time desc','*',$r);

        $statustext = [0=>"未发货",1=>'已发货',2=>'已收货',3=>'已完成'];
        if($list){
            foreach($list as $key=>$row){
                $list[$key]['statustext'] =$statustext[$row['status']];
                $list[$key]['refundtext'] = $row['refund']==0 ? '未退还':'已退还';
                $list[$key]['nickname'] = get_nickname($row['uid']);
                
                $product_info = json_decode($row['product_info'], true);
                $list[$key]['goods_info'] = "";
                foreach ($product_info as $item){
                    $list[$key]['goods_info'] .= $item['name'] . '*' . $item['num'] . '<br/>';
                }
            }
        }
        
        $builder=new BackstageListBuilder();
        $builder->title('订单列表')
            ->keyId('out_trade_no', '订单编号')
            ->data($list)
            ->setSearchPostUrl(url('order/index'))
            ->searchText('','keyword','text','订单编号')
            ->keyText('nickname','用户名称')
            ->keyText('goods_info','商品信息')
            ->keyText('total_fee','订单价格')
            ->keyText('statustext','状态')
            ->keyCreateTime()
            ->keyDoActionEdit('order/edit?id=###','编辑')
            ->keyDoActionEdit('order/print?id=###','打印');
        $builder->pagination($totalCount);
        return $builder->show();
    }

    public function edit(){

    }
}