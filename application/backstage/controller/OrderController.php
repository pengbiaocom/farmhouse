<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageListBuilder;
use app\common\model\OrderModel;

class OrderController extends BackstageController{

    public function   index(){
        $r = config("LIST_ROWS");
        $orderModel = new OrderModel();

        $map = array();//定义条件数据
        
        //搜索
        $keyword = input('keyword','','op_t');
        if(!empty($keyword)){
            $map['out_trade_no'] = array('like', $keyword);
        }
        
        //状态
        $status = input('status', -1, 'intval');
        if($status !== -1){
            $map['status'] = array('EQ', $status);
        }

        list($list,$totalCount)=$orderModel->getListByPage($map,'refund asc, printd asc, create_time desc','*',$r);

        $statustext = [0=>"待付款",1=>'待发货',2=>'待收货',3=>'待评价',4=>'已完成'];
        if($list){
            foreach($list as $key=>$row){
                $list[$key]['statustext'] =$statustext[$row['status']];
                $list[$key]['printdtext'] = $row['printd']==0 ? '<span style="color:red;">未打印</span>':'<span style="color:green;">已打印</sapn>';
                $list[$key]['refundtext'] = $row['refund']==0 ? '<span style="color:red;">未退还</span>':'<span style="color:green;">已退还</span>';
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
            ->ajaxButton('', '', '打印所选项', ['class'=>'layui-btn ajax-post tox-confirm', 'data-confirm'=>'是否要打印所选项小票'])
            ->ajaxButton('', '', '打印筛选结果', ['class'=>'layui-btn ajax-post tox-confirm', 'data-confirm'=>'是否要打印筛选结果小票'])
            ->ajaxButton('', '', '退还所选项', ['class'=>'layui-btn ajax-post tox-confirm', 'data-confirm'=>'是否要退还所选项'])
            ->ajaxButton('', '', '打印筛选结果', ['class'=>'layui-btn ajax-post tox-confirm', 'data-confirm'=>'是否要打印筛选结果'])
            ->keyId('out_trade_no', '订单编号')
            ->setSearchPostUrl(url('order/index'))
            ->searchDateTime('日期', 'create_time', 'date')
            ->searchSelect('订单状态', 'status', 'select', '', '', [['id'=>-1, 'value'=>'请选择'],['id'=>0,'value'=>'待付款'],['id'=>1,'value'=>'待发货'],['id'=>2,'value'=>'待收货'],['id'=>3,'value'=>'待评价'],['id'=>4,'value'=>'已完成']])
            ->searchText('','keyword','text','订单编号')
            ->keyText('nickname','用户名称')
            ->keyText('goods_info','商品信息')
            ->keyText('coupon', '优惠券使用量')
            ->keyText('freight', '运费')
            ->keyText('total_fee','订单价格')
            ->keyHtml('remark', '备注信息')
            ->keyText('printdtext','是否打印')
            ->keyText('refundtext','是否退款')
            ->keyText('statustext','状态')
            ->keyCreateTime()
            ->data($list);
        $builder->pagination($totalCount);
        return $builder->show();
    }

    public function edit(){

    }
}