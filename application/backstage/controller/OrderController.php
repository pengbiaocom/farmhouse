<?php
namespace app\backstage\controller;

use app\common\model\OrderModel;
use app\common\model\ProductModel;
use app\common\model\AuthRuleModel;
use app\common\model\UcenterMemberModel;
use app\backstage\builder\BackstageListBuilder;

class OrderController extends BackstageController{

    private $config = [];

    public function   index(){
        $r = config("LIST_ROWS");
        $orderModel = new OrderModel();

        $map = array();//定义条件数据
        
        //状态
        $status = input('status', -1, 'intval');
        if($status !== -1){
            $map['status'] = array('EQ', $status);
        }else{
            $map['status'] = array('EGT', 0);
        }

        $pro = input("pro",-1,'intval');
        $city = input('city',-1,'intval');
        $dis = input("dis",-1,'intval');
        $street = input("street",-1,'intval');

        //区域
        if($pro!=-1){
            $wh['pos_province'] = $pro;
            if($city!=-1) $wh['pos_city'] = $city;
            if($dis!=-1)  $wh['pos_district'] = $dis;
            $address = db("receiving_address")->field("id")->where($wh)->select();

            if(!empty($address)){
                $addressidlist = '';
                foreach($address as $row){
                    $addressidlist .= $row['id'].',';
                }
                $addressidlist = trim($addressidlist,',');
                if(!empty($addressidlist)){
                    $map['address_id'] = ['in',$addressidlist];
                }
            }else{
                $map['address_id'] = ['in',-1];
            }
        }
        
        //时间   所选日期当天的订单
        $create_time = input('create_time', strtotime(date('Y-m-d')), 'intval');
        if(!empty($create_time)){
            $map['create_time'] = array('between', [$create_time, $create_time+86400]);
        }
        
        //打印
        $printd = input('printd', -1, 'intval');
        if($printd !== -1){
            $map['printd'] = array('EQ', $printd);
        }
        
        //退款
        $refund = input('refund', -1, 'intval');
        if($refund !== -1){
            $map['refund'] = array('EQ', $refund);
        }
        
        //搜索
        $keyword = input('keyword','','op_t');
        if(!empty($keyword)){
            $map['out_trade_no'] = array('like', $keyword);
        }


        list($list,$totalCount)=$orderModel->getListByPage($map,'refund asc, printd asc, create_time desc','*',$r);

        $statustext = [0=>"待付款",1=>'待发货',2=>'待收货',3=>'待评价',4=>'已完成'];
        if($list){
            foreach($list as $key=>$row){
                $list[$key]['statustext'] =$statustext[$row['status']];
                $list[$key]['printdtext'] = $row['printd']==0 ? '<span style="color:red;">未打印</span>':'<span style="color:green;">已打印</sapn>';
                $list[$key]['refundtext'] = $row['refund']==0 ? '<span style="color:red;">未退还</span>':'<span style="color:green;">已退还</span>';
                $list[$key]['nickname'] = get_nickname($row['uid']);
                $list[$key]['address'] = db('receiving_address')->find($row['address_id']);
                $list[$key]['create_time'] = date('Y-m-d H:i:s', $row['create_time']);
                
                $product_info = json_decode($row['product_info'], true);
                $list[$key]['goods_info'] = "";
                foreach ($product_info as $item){
                    $list[$key]['goods_info'] .= $item['name'] . '*' . $item['num'] . '<br/>';
                }
            }
        }

        //检测动态权限
        $rule1 = strtolower('backstage/order/print_search');
        $rule2 = strtolower('backstage/order/refunds');
        $rule3 = strtolower('backstage/order/template');

        $is_auth1 = $is_auth2 = $is_auth3 = 0;
        if (!$this->checkRule($rule1, AuthRuleModel::RULE_URL, null)) {
            $is_auth1 = 1;
        }
        if (!$this->checkRule($rule2, AuthRuleModel::RULE_URL, null)) {
            $is_auth2 = 1;
        }
        if (!$this->checkRule($rule3, AuthRuleModel::RULE_URL, null)) {
            $is_auth2 = 1;
        }

        $this->assign("is_auth1",$is_auth1);
        $this->assign("is_auth2",$is_auth2);
        $this->assign("is_auth3",$is_auth3);
        $this->assign("pro",$pro);
        $this->assign("city",$city);
        $this->assign("dis",$dis);
        $this->assign("street",$street);
        $this->assign('list', $list);
        $this->assign('_page',$totalCount);
        $this->assign('meta_title', '订单列表');
        return $this->fetch();
    }
    
    /**
    * 发送模版通知
    * @date: 2019年8月1日 上午9:56:26
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function template(){
        $config = [
            'appid'=>'wxa6737565830cae42',
            'secret'=>'2db64a778849a93bf4481a5815427a54'
        ];
        
        
        $params = $this->request->param();
                
        $resJson = $this->postXmlCurl("", "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . $config["appid"] . "&secret=" . $config["secret"]);
        $resJson = json_decode($resJson, true);
        if(isset($resJson['access_token']) && !empty($resJson['access_token'])){
            $orderModel = new OrderModel();
            $orders = $orderModel::all(function($query) use($params){
                $query->alias('order');
            
                $query->field('uc.openid,address.name as nickname,group_concat(order.out_trade_no) as out_trade_nos,order.formId,province.name as province_name,city.name as city_name,district.name as district_name,street.name as street_name,address.pos_community,group_concat(order.product_info SEPARATOR ";") as product_infos');//整理数据
            
                $query->where('order.status', 'in', '1,2');//待发货的
                $query->where('order.is_message_send', 0);//没有通知的
                $query->where('order.create_time', 'between', [$params['create_time'], $params['create_time']+86400]);
            
                $query->join('__MEMBER__ member', 'order.uid = member.uid', 'LEFT');//需要获取到用户openID
                $query->join('__UCENTER_MEMBER__ uc', 'order.uid = uc.id', 'LEFT');
            
                $query->join('__RECEIVING_ADDRESS__ address', 'order.address_id = address.id', 'LEFT');
                $query->join('__DISTRICT__ province', 'address.pos_province = province.id', 'LEFT');
                $query->join('__DISTRICT__ city', 'address.pos_city = city.id', 'LEFT');
                $query->join('__DISTRICT__ district', 'address.pos_district = district.id', 'LEFT');
                $query->join('__DISTRICT__ street', 'address.street_id = street.id', 'LEFT');
            
                if(isset($params['ids']) && !empty($params['ids'])) {
                    $query->where('order.id', 'in', $params['ids']);
                }else{
                    if(isset($params['street']) && $params['street'] != -1){
                        $query->where('address.street_id', $params['street']);//筛选的街道
                    }else{
                        if(isset($params['dis']) && $params['dis'] != -1){
                            $query->where('address.pos_district', $params['dis']);//筛选的区县
                        }else{
                            if(isset($params['city']) && $params['city'] != -1){
                                $query->where('address.pos_city', $params['city']);//筛选的城市
                            }else{
                                if(isset($params['pro']) && $params['pro'] != -1){
                                    $query->where('address.pos_province', $params['pro']);//筛选的城市
                                }
                            }
                        }
                    }
                }
            
                //分组
                $query->group('order.uid');
            });
            
                foreach ($orders as $order){
                    $product_infos = [];
                    foreach (explode(';', $order->product_infos) as $product_info){
                        $products = json_decode($product_info, true);
                        foreach ($products as $product){
                            $product_infos[] = $product['name'] . '*' . $product['num'];
                        }
                    }
            
                    if(!empty($order->formId)){
                        $template = array(
                            'touser'		=> $order->openid,
                            'template_id'	=> '7xa7B1oydUK9XDBvwe5nQUnKsCeYk6cNHWVsImVAb3I',
                            'form_id'	=> $order->formId,
                            'data' => [
                                'keyword1'=>['value'=>$order->nickname],
                                'keyword2'=>['value'=>date('Y年m月d日H时i分')],
                                'keyword3'=>['value'=>$order->province_name . $order->city_name . $order->district_name . $order->street_name . $order->pos_community],
                                'keyword4'=>['value'=>implode(',', $product_infos)],
                                'keyword5'=>['value'=>'待收货']
                            ],//退款唯一单号，系统生成
                        );
            
                        //请求数据
                        $url = 'https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=' . $resJson['access_token'];
                        $res = self::postXmlCurl(json_encode($template), $url, false);
                        $resData = json_decode($res, true);
                        
                        if($resData['errcode'] == 0){
                            ob_flush();
                            flush();
                            echo '用户：'.$order->nickname.'发送通知完成。<br/>';
                            $orderModel->where('out_trade_no', 'in', $order->out_trade_nos)->update(['is_message_send'=>1]);
                        }else{
                            switch ($resData['errcode']) {
                                case 40037:
                                    echo 'template_id不正确';
                                break;
                                case 41028:
                                    echo 'form_id不正确，或者过期';
                                break;
                                case 41029:
                                    echo '用户：'.$order->nickname.'已经发送（form_id已被使用）';
                                    $orderModel->where('out_trade_no', 'in', $order->out_trade_nos)->update(['is_message_send'=>1]);
                                break;
                                case 41030:
                                    echo 'page不正确';
                                break;
                                case 45009:
                                    echo '接口调用超过限额（目前默认每个帐号日调用限额为100万）';
                                break;
                            }
                        }
                    }
                }
        }
    }
    
    public function export_order()
    {
        vendor("PHPExcel.PHPExcel");
        $objPHPExcel = new \PHPExcel();

        $params = $this->request->param();
        if(!isset($params['create_time'])) $params['create_time'] = strtotime(date('Ymd'));
        
        //处理数据
        $orderModel = new OrderModel();
        $orders = $orderModel::all(function($query) use($params){
            $query->alias('order');
            $query->field('order.product_info,member.nickname,order.uid,address.name,address.mobile');
            $query->join('__MEMBER__ member', 'order.uid = member.uid', 'LEFT');
            $query->join('__RECEIVING_ADDRESS__ address', 'order.address_id = address.id', 'LEFT');
            $query->where('order.status', 1);
            $query->where('order.create_time', 'between', [$params['create_time'], $params['create_time']+86400]);
            
            //$query->group('order.uid');
        });
        
        $products = [];
        $users = [];
        $productModel = new ProductModel();
        foreach ($orders as $order){
			foreach (json_decode($order->product_info, true) as $product){
				$productInfo = $productModel::get(function($query) use($product){
					$query->alias('product');
					$query->field('category.id as cate_id,category.title,product.id,product.spec,product.price');
					$query->where('product.id', $product['id']);
					$query->join('__PRODUCT_CATEGORY__ category', 'product.category = category.id', 'LEFT');
				});
			
				//销售表数据
				$product['category'] = $productInfo->title;
				$product['spec'] = $productInfo->spec;
				$product['price'] = $productInfo->price;
				$product['user_name'] = $order->name;
				$product['mobile'] = $order->mobile;
				
				if (isset($products[$productInfo->cate_id]['list'][$productInfo->id])) {
					$products[$productInfo->cate_id]['list'][$productInfo->id]['num'] += $product['num'];
				}else{
					$products[$productInfo->cate_id]['category_name'] = $product['category'];
					$products[$productInfo->cate_id]['list'][$productInfo->id] = $product;
				}
				
				//用户订单表数据
				if (isset($users[$order->uid]['list'][$productInfo->id])) {
					$users[$order->uid]['list'][$productInfo->id]['num'] += $product['num'];
				}else{
					$users[$order->uid]['name'] = $order->name;
					$users[$order->uid]['nickname'] = $order->nickname;
					$users[$order->uid]['mobile'] = $order->mobile;
					$users[$order->uid]['list'][$productInfo->id] = $product;
				}
			} 
        }
//         dump($users);exit;
//         dump($products);exit;

        //定义配置
        $title = date('Y年m月d日', $params['create_time']) . "销售数据";
        $topNumber = 2;//表头有几行占用
        $xlsTitle = iconv('utf-8', 'gb2312', $title);//文件名称
        $fileName = $title.date('_YmdHis');//文件名称
        $cellKey = array(
            'A','B','C','D','E','F','G','H','I','J','K','L','M',
            'N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
            'AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM',
            'AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ'
        );
        
        /**
         * 第一个sheet
         */
        //处理表头标题
        $objPHPExcel->getActiveSheet()->setTitle('销售数据');
        $objPHPExcel->getActiveSheet()->mergeCells('A1:'.$cellKey[8].'1');//合并单元格（如果要拆分单元格是需要先合并再拆分的，否则程序会报错）
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('A1',date('Y年m月d日', $params['create_time']) . '销售信息');
        $objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(18);
        $objPHPExcel->getActiveSheet()->getStyle('A1')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A1')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

        //处理表头
        $objPHPExcel->getActiveSheet()->setCellValue('A2', '分类');
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
        $objPHPExcel->getActiveSheet()->setCellValue('B2', '名称');
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(60);
        $objPHPExcel->getActiveSheet()->setCellValue('C2', '销量');
        $objPHPExcel->getActiveSheet()->setCellValue('D2', '规格');
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(30);
        $objPHPExcel->getActiveSheet()->setCellValue('E2', '单价');
        $objPHPExcel->getActiveSheet()->setCellValue('F2', '合计（单价*销量）');
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(30);
        
        //处理第一个sheet的数据
        $startHead = 3;
        $total_price = 0;
        foreach ($products as $product){
            $headCount = count($product['list']);
            if($headCount > 1){
                $objPHPExcel->getActiveSheet()->mergeCells('A'.$startHead.':A'.($startHead + $headCount - 1));
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $startHead, $product['category_name']);
                
                $i = 0;
                foreach ($product['list'] as $item){
                    $num_price  = number_format($item['price']*$item['num'], 2);
                    $objPHPExcel->getActiveSheet()->setCellValue('B' . ($startHead + $i), $item['name']);
                    $objPHPExcel->getActiveSheet()->setCellValue('C' . ($startHead + $i), $item['num']);
                    $objPHPExcel->getActiveSheet()->setCellValue('D' . ($startHead + $i), $item['spec']);
                    $objPHPExcel->getActiveSheet()->setCellValue('E' . ($startHead + $i), '￥'.$item['price']);
                    $objPHPExcel->getActiveSheet()->setCellValue('F' . ($startHead + $i), '￥'.$num_price);
                    $total_price += $num_price;
                    $i++;
                }
            }else{
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $startHead, $product['category_name']);
                
                $i = 0;
                foreach ($product['list'] as $item){
                    $num_price  = number_format($item['price']*$item['num'], 2);
                    $objPHPExcel->getActiveSheet()->setCellValue('B' . ($startHead + $i), $item['name']);
                    $objPHPExcel->getActiveSheet()->setCellValue('C' . ($startHead + $i), $item['num']);
                    $objPHPExcel->getActiveSheet()->setCellValue('D' . ($startHead + $i), $item['spec']);
                    $objPHPExcel->getActiveSheet()->setCellValue('E' . ($startHead + $i), '￥'.$item['price']);
                    $objPHPExcel->getActiveSheet()->setCellValue('F' . ($startHead + $i), '￥'.$num_price);
                    $total_price += $num_price;
                    $i++;
                }
            }
            $startHead += $headCount;
        }
        $objPHPExcel->getActiveSheet()->mergeCells('A'.$startHead.':E'.$startHead);
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $startHead, '合计');
        $objPHPExcel->getActiveSheet()->setCellValue('F' . $startHead, '￥' . number_format($total_price, 2));
        
        
        /**
         * 第二个sheet
         */
        //创建
        $objPHPExcel->createSheet();
        $objPHPExcel->setActiveSheetIndex(1);
        $objPHPExcel->getActiveSheet()->setTitle('订单数据');
        $objPHPExcel->getActiveSheet()->mergeCells('A1:'.$cellKey[8].'1');
        $objPHPExcel->setActiveSheetIndex(1)->setCellValue('A1', date('Y年m月d日', $params['create_time']) . '用户订单信息');
        $objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setBold(true);
        $objPHPExcel->getActiveSheet()->getStyle('A1')->getFont()->setSize(18);
        $objPHPExcel->getActiveSheet()->getStyle('A1')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A1')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);

        //处理第二个sheet表头
        $objPHPExcel->getActiveSheet()->setCellValue('A2', '用户姓名');
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
        $objPHPExcel->getActiveSheet()->setCellValue('B2', '联系电话');
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
        $objPHPExcel->getActiveSheet()->setCellValue('C2', '购买商品');
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(60);
        $objPHPExcel->getActiveSheet()->setCellValue('D2', '购买数量');
        $objPHPExcel->getActiveSheet()->setCellValue('E2', '规格');
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(30);
        
        //处理第二个sheet的数据
        $startHead = 3;
        foreach ($users as $user){
            $headCount = count($user['list']);
            if($headCount > 1){
                $objPHPExcel->getActiveSheet()->mergeCells('A'.$startHead.':A'.($startHead + $headCount - 1));
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $startHead, $user['name'] . '('.$user['nickname'].')');

                $objPHPExcel->getActiveSheet()->mergeCells('B'.$startHead.':B'.($startHead + $headCount - 1));
                $objPHPExcel->getActiveSheet()->setCellValue('B' . $startHead, $user['mobile']);
        
                $i = 0;
                foreach ($user['list'] as $item){
                    $objPHPExcel->getActiveSheet()->setCellValue('C' . ($startHead + $i), $item['name']);
                    $objPHPExcel->getActiveSheet()->setCellValue('D' . ($startHead + $i), $item['num']);
                    $objPHPExcel->getActiveSheet()->setCellValue('E' . ($startHead + $i), $item['spec']);
                    $i++;
                }
            }else{
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $startHead, $user['name'] . '('.$user['nickname'].')');
                $objPHPExcel->getActiveSheet()->setCellValue('B' . $startHead, $user['mobile']);
        
                $i = 0;
                foreach ($user['list'] as $item){
                    $objPHPExcel->getActiveSheet()->setCellValue('C' . ($startHead + $i), $item['name']);
                    $objPHPExcel->getActiveSheet()->setCellValue('D' . ($startHead + $i), $item['num']);
                    $objPHPExcel->getActiveSheet()->setCellValue('E' . ($startHead + $i), $item['spec']);
                    $i++;
                }
            }
            $startHead += $headCount;
        }
        
        //导出execl
        header('pragma:public');
        header('Content-type:application/vnd.ms-excel;charset=utf-8;name="'.$xlsTitle.'.xls"');
        header("Content-Disposition:attachment;filename=$fileName.xls");//attachment新窗口打印inline本窗口打印
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }

    /**
    * 打印所选
    * @date: 2018年7月27日 上午9:50:29
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function print_select($ids){
        /* 读取数据库中的配置 */
        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置
        
        //加载商品应收单价（ID、price格式）
        $productModel = new ProductModel();
        $prices = [];
        $products = $productModel::all(function($query){
            $query->field("id,price,price_line,sales");
            $query->where('status', 1);
        });
        
        if($products){
            foreach ($products as $product){
                $curr_price = $product['price'];
                if(!empty($product['price_line'])){
                    $array = preg_split('/[,;\r\n]+/', trim($product['price_line'], ",;\r\n"));
            
                    if(strpos($product['price_line'],'|')){
                        foreach ($array as $val) {
                            list($k, $v) = explode('|', $val);
                            if($product['sales'] >= $k){
                                $curr_price = number_format($v,2,".","");
                            }
                        }
                    }
                }
                $prices[$product['id']] = ['price'=>$product['price'], 'curr_price'=>$curr_price, 'sales'=>$product['sales']];
            }
            
            
            //获取到满足条件的订单数据（包括订单数据、用户数据、地址数据）
            $orderModel = new OrderModel();
            $orders = $orderModel::all(function($query) use($ids){
                $query->alias('order');
                $query->field("order.*,member.nickname,address.name as address_name,address.mobile as address_mobile,address.pos_community,province.name as province_name,city.name as city_name,district.name as district_name,street.name as street_name");
                $query->join('__MEMBER__ member', 'order.uid = member.uid', 'LEFT');
                $query->join('__RECEIVING_ADDRESS__ address', 'order.address_id = address.id', 'LEFT');
                $query->join('__DISTRICT__ province', 'address.pos_province = province.id', 'LEFT');
                $query->join('__DISTRICT__ city', 'address.pos_city = city.id', 'LEFT');
                $query->join('__DISTRICT__ district', 'address.pos_district = district.id', 'LEFT');
                $query->join('__DISTRICT__ street', 'address.street_id = street.id', 'LEFT');
                $query->where('order.id', 'IN', $ids);
            });

            //分析整理最后需要打印的数据
            foreach ($orders as &$item){
                $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
                
                $item['product_info'] = json_decode($item['product_info'], true);
                $goods = [];
                $curr_total_fee = 0;
                foreach ($item['product_info'] as $key=>$good){
                    $good['curr_price'] = $prices[$good['id']]['curr_price'];
                    $good['num_price'] = $prices[$good['id']]['curr_price'] * $good['num'];
                    $curr_total_fee += $good['num_price'];
                    $goods[] = $good;
                    unset($good);
                }
                $item['products'] = $goods;
                $item['curr_total_fee'] = $curr_total_fee;
                unset($goods);unset($item['product_info']);
            }

            $this->assign('orders', $orders);
            $this->assign('config', $config);
        }
        
        return $this->fetch();
    }
    
    /**
    * 打印筛选结果
    * @date: 2018年7月27日 上午9:50:55
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function print_search(){
        /* 读取数据库中的配置 */
        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置
        
        //加载商品应收单价（ID、price格式）
        $productModel = new ProductModel();
        $prices = [];
        $products = $productModel::all(function($query){
            $query->field("id,price,price_line,sales");
            $query->where('status', 1);
        });
        
        if($products){
            foreach ($products as $product){
                $curr_price = $product['price'];
                if(!empty($product['price_line'])){
                    $array = preg_split('/[,;\r\n]+/', trim($product['price_line'], ",;\r\n"));
            
                    if(strpos($product['price_line'],'|')){
                        foreach ($array as $val) {
                            list($k, $v) = explode('|', $val);
                            if($product['sales'] >= $k){
                                $curr_price = number_format($v,2,".","");
                            }
                        }
                    }
                }
                $prices[$product['id']] = ['price'=>$product['price'], 'curr_price'=>$curr_price, 'sales'=>$product['sales']];
            }
            
            //筛选参数
            $ids = input('ids', '', 'op_t');
            $status = input('status', -1, 'intval');
            $create_time = input('create_time', strtotime(date('Y-m-d')), 'intval');
            $keyword = input('keyword','','op_t');
			
			$this->writeGetDataLog(json_encode(["ids"=>$ids,"status"=>$status,"create_time"=>$create_time, "keyword"=>$keyword]));
            
            //获取到满足条件的订单数据（包括订单数据、用户数据、地址数据）
            $orderModel = new OrderModel();
            $orders = $orderModel::all(function($query) use($ids,$status,$create_time,$keyword){
                $query->alias('order');
                $query->field("order.*,member.nickname,address.name as address_name,address.mobile as address_mobile,address.pos_community,province.name as province_name,city.name as city_name,district.name as district_name,street.name as street_name");
                $query->join('__MEMBER__ member', 'order.uid = member.uid', 'LEFT');
                $query->join('__RECEIVING_ADDRESS__ address', 'order.address_id = address.id', 'LEFT');
                $query->join('__DISTRICT__ province', 'address.pos_province = province.id', 'LEFT');
                $query->join('__DISTRICT__ city', 'address.pos_city = city.id', 'LEFT');
                $query->join('__DISTRICT__ district', 'address.pos_district = district.id', 'LEFT');
                $query->join('__DISTRICT__ street', 'address.street_id = street.id', 'LEFT');
                
                if(!empty($ids)) $query->where('order.id', 'IN', $ids);
                
                if($status != -1) $query->where('order.status', $status);
                
                if(!empty($create_time)) $query->where('order.create_time', 'between', [$create_time, $create_time+86400]);
                
                if(!empty($keyword)) $query->where('out_trade_no', 'like', '%'.$keyword.'%');
                
            });
            
            //分析整理最后需要打印的数据
            foreach ($orders as &$item){
                $orderModel::update(['printd'=>1],function($query) use($item){
                    $query->where('id', $item['id']);
                });
                
                $item['distribution'] = '次日09:00-19:00（自取）';//$item['distribution'] == -1 ? $item['distribution'] : ($item['distribution'] == 0 ? '次日09:00-11:30（自取）': '次日15:00-19:00（自取）');
                $item['remark'] = empty($item['remark']) ? '无' : $item['remark'];
                $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
        
                $item['product_info'] = json_decode($item['product_info'], true);
                $goods = [];
                $curr_total_fee = 0;
                foreach ($item['product_info'] as $key=>$good){
                    $good['curr_price'] = $prices[$good['id']]['curr_price'];
                    $good['num_price'] = $prices[$good['id']]['curr_price'] * $good['num'];
                    $curr_total_fee += $good['num_price'];
                    $goods[] = $good;
                    unset($good);
                }
                $item['products'] = $goods;
                
                $coupon_price = config('COUPON_DENOMINATION');//没设置的情况下默认为1分钱
                $item['curr_total_fee'] = $curr_total_fee+$item['freight']-$item['coupon']*$coupon_price;
                
                unset($goods);unset($item['product_info']);
            }
        
            $this->assign('orders', $orders);
            $this->assign('config', $config);
        }
        
        return $this->fetch('print_select');
    }
    
    /**
     * 抓取数据日志写入
     * @param string $content 待写入的内容
     * @param string $root 下级目录
     * @param string $name 文件名
     */
    public function writeGetDataLog($content,$root='',$name=''){
        $filename = date('Ymd').$name.'.txt';
        $fileContent = date('Y-m-d H:i:s').': '.$content."\r\n";
    
        //文件夹不存在先创建目录
        $savePath = "./getDataLog";
        if(!empty($root)) $savePath = "./getDataLog/".$root;
        if(!file_exists($savePath)) mkdir($savePath,0777,true);
    
        $fp=fopen($savePath.'/'.$filename, "a+");
        fwrite($fp,$fileContent);
        fclose($fp);
    } 
    
    /**
    * 获取满足条件的退款订单
    * @date: 2018年7月27日 上午9:00:05
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function refunds(){
        //微信支付参数配置(appid,商户号,支付秘钥)
        $config = [
            'appid'=>'wxa6737565830cae42',
            'pay_mchid'=>'1509902681',
            'pay_apikey'=>'6ba57bc32cfd5044f8710f09ff86c664'
        ];
		$this->config = $config;
		
		$ids = input('ids', '', 'op_t');
		$type = input('type', 1, 'intval');
        $status = input('status', -1, 'intval');
        $create_time = input('create_time', strtotime(date('Y-m-d')), 'intval');
        $keyword = input('keyword','','op_t');

        //获取到满足条件的订单数据（包括订单数据、用户数据、地址数据）
        $orderModel = new OrderModel();
        $orders = $orderModel::all(function($query) use($ids,$status,$create_time,$keyword, $type){
            if($type == 1){
                if(!empty($ids)) $query->where('id', 'in', $ids);
                
                if($status != -1) $query->where('status', $status);
                
                if(!empty($create_time)) $query->where('create_time', 'between', [$create_time, $create_time+86400]);
                
                if(!empty($keyword)) $query->where('out_trade_no', 'like', '%'.$keyword.'%');                
            }else{
                $query->where('id', $ids);
            }
        });        
		
        foreach ($orders as $order){
            if($type == 1){
                if($order->status > 0 && $order->refund == 0){
                    if($this->refund($order)){
                        ob_flush();
                        flush();
                        echo '订单：'.$order->out_trade_no.'退款完成。<br/>';
                    }else{
                        ob_flush();
                        flush();
                        echo '订单：'.$order->out_trade_no.'退款失败。<br/>';
                    }
                }else{
                    ob_flush();
                    flush();
                    echo '订单：'.$order->out_trade_no.'退款失败（未支付订单）。<br/>';
                }                
            }else{
                if($order->status > 0){
                    $del = db("order")->where(['id'=>$order['id']])->update(['status'=>-1]);
                    if($del){
                        //处理退款接口
                        if($this->refund($order, 2)){
                            $goods = json_decode($order['product_info'], true);
                            foreach ($goods as $good){
                                $productModel = new ProductModel();
                                $productModel->where('id', $good['id'])->setInc('stock', $good['num']);
                                $productModel->where('id', $good['id'])->setDec('sales', $good['num']);
                            }
                            
                            $map['create_time'] = ['GT', strtotime(date('Ymd'))];
                            $map['status'] = ['GT', 0];
                            $map['uid'] = $order['uid'];
                            if(db("order")->where($map)->count() == 0 && db('ucenter_member')->where('id', $order['uid'])->value('continuity_buy') > 0) {
                                db('ucenter_member')->where('id', $order['uid'])->setDec('continuity_buy', 1);
                            }
                    
                            $this->success('取消成功！');
                        } else {
                            db("order")->where(['id'=>$order_id])->update(['status'=>1]);
                            $this->error('取消失败！');
                        }
                    }
                }else{
                    if(db("order")->where(['id'=>$order['id']])->update(['status'=>-1])){
                        $goods = json_decode($order['product_info'], true);
                        foreach ($goods as $good){
                            $productModel = new ProductModel();
                            $productModel->where('id', $good['id'])->setInc('stock', $good['num']);
                        }
                        $this->success('取消成功！');
                    }else{
                        $this->error('取消失败！');
                    }
                }
            }
        }
    }
    
    public function profit(){
        $date = input('date', strtotime(date('Ymd')), 'intval');
        
        $ucenterMember = new UcenterMemberModel();
        $users = $ucenterMember::all(function($query){
            $query->field('user.invit, member.nickname, count(user.id) as invitCount');
            $query->alias('user');
            $query->join('__MEMBER__ member', 'user.invit = member.uid', 'left');
            $query->where('user.invit', '>', 0);
            $query->group('user.invit');
        });
        
        $lists = [];
		$uids = [];
        foreach ($users as $user){
            $data['id'] = $user['invit'];
            $data['nickname'] = $user['nickname'];
            $data['invitCount'] = $user['invitCount'];
            
            $rebate = $this->initRebate($user['invit'], $date);
            
            $data['is_today_buy'] = $rebate['is_today_buy'] == 0 ? '否' : '是';
            $data['tal_profit'] = $rebate['tal_profit'];
            
            $data['buy_rebate'] = $rebate['buy_rebate'];
            $data['buy_money'] = $rebate['buy_money'];
            $data['continuity_buy'] = $rebate['continuity_buy'];
            
            $data['invit_rebate'] = $rebate['invit_rebate'];
            $data['invit_money'] = $rebate['invit_money'];
            $data['today_invit_count'] = $rebate['today_invit_count'];
            
            $uids[] = $user['invit'];
            $lists[] = $data;unset($data);unset($rebate);
        }
		
		$orderModel = new OrderModel();
		$orders = $orderModel::all(function($query) use($date){
			$query->field('order.uid, member.nickname');
			$query->alias('order');
			$query->join('__MEMBER__ member', 'order.uid = member.uid', 'left');
			$query->join('__UCENTER_MEMBER__ user', 'order.uid = user.id', 'left');
			$query->where('order.status', '>', 0);
			$query->where('order.create_time', 'between', [$date, $date+86400]);
			$query->group('order.uid');
		});
		
		foreach ($orders as $order){
			if(!in_array($order['uid'], $uids)){
				$data['id'] = $order['uid'];
				$data['nickname'] = $order['nickname'];
				$data['invitCount'] = 0;
				
				$rebate = $this->initRebate($order['uid'], $date);
				
				
				$data['is_today_buy'] = $rebate['is_today_buy'] == 0 ? '否' : '是';
				$data['tal_profit'] = $rebate['tal_profit'];
				
				$data['buy_rebate'] = $rebate['buy_rebate'];
				$data['buy_money'] = $rebate['buy_money'];
				$data['continuity_buy'] = $rebate['continuity_buy'];
				
				$data['invit_rebate'] = $rebate['invit_rebate'];
				$data['invit_money'] = $rebate['invit_money'];
				$data['today_invit_count'] = $rebate['today_invit_count'];
				
				
				$lists[] = $data;unset($data);unset($rebate);				
			}
		}
        
        $builder = new BackstageListBuilder();
        $builder->title('收益总览');
        
        $builder->searchDateTime('日期', 'date', 'date');
        
        $builder->keyId();
        $builder->keyText('nickname', '用户昵称');
        $builder->keyText('today_invit_count', '今日邀请并购买人数');
        $builder->keyText('invitCount', '总邀请人数');
        
        $builder->keyText('is_today_buy', '今日是否购买');
        $builder->keyText('buy_rebate', '购买返利比例');
        $builder->keyText('buy_money', '购买收益');
        
        $builder->keyText('invit_rebate', '邀请提成比例');
        $builder->keyText('invit_money', '邀请收益');
        
        return $builder->data($lists)->show();
    }
    
    private function initRebate($uid, $date){
        /* 读取数据库中的配置 */
        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置
        
        $buyInitScale = config('BUY_INIT_SCALE');//基础
        $buyIncScale = config('BUY_INC_SCALE');//增幅
        $buyMaxScale = config('BUY_MAX_SCALE');//最大
        $invitInitScale = config('INVIT_INIT_SCALE');//基础
        $invitIncScale = config('INVIT_INC_SCALE');//增幅
        $invitMaxScale = config('INVIT_MAX_SCALE');//最大
         
        $boef_time = empty($date) ? strtotime(date('Ymd')) : strtotime(date('Ymd',$date));
         
        /* 购买统计 */
        $ucenterMemberModel = new UcenterMemberModel();
        $users = $ucenterMemberModel::all(function($query) use($uid,$boef_time){
            $query->field('user.continuity_buy, sum(order.total_fee) as total_fee, FROM_UNIXTIME(order.create_time,"%Y%m%d") as create_date');
            $query->alias('user');
            $query->join('__ORDER__ order', 'user.id = order.uid', 'left');
            $query->where('user.id', $uid);
            $query->where('order.status', '>', 0);
            $query->where('order.create_time', 'between', [$boef_time-172800,$boef_time]);
            $query->order('create_date DESC');
            $query->group('FROM_UNIXTIME(order.create_time,"%Y%m%d")');
        });
             
        $orderModel = new OrderModel();
        $map['status'] = array('GT', 0);
        $map['uid'] = $uid;
        $map['create_time'] = array('between', [$boef_time, $boef_time+86400]);
        $today_buy = $orderModel->where($map)->count();
         
        $rebates = [];
        $rebates['is_today_buy'] = $today_buy;
        $rebates['buy_rebate'] = 0;
        $rebates['buy_money'] = '0.00';
        $rebates['tal_profit'] = $ucenterMemberModel->where('id', $uid)->value('tal_profit');
        $rebates['continuity_buy'] = $ucenterMemberModel->where('id', $uid)->value('continuity_buy');
         
        if($rebates['continuity_buy'] == 0){
            $rebates['buy_rebate'] = 0;
        }else{
            $rebate = $buyInitScale + ($rebates['continuity_buy']-1)*$buyIncScale;
            $rebates['buy_rebate'] = min($rebate, $buyMaxScale);            
        }
         
        //没有购买过或者连续购买断裂
        foreach ($users as $user){
            if($rebates['continuity_buy'] == 0){
                $rebates['buy_money'] = '0.00';
            } else {
                if($rebates['is_today_buy'] > 0){
                    $rebates['buy_money'] = sprintf("%.2f", $user['total_fee']*$rebates['buy_rebate']/100);
                }else{
                    $rebates['buy_money'] = '0.00';
                }
            }
    
            if($user['total_fee'] > 0) break;
        }
    
        /* 邀请数据统计 */
        $invits = $ucenterMemberModel::all(function($query) use($uid,$boef_time){
            $query->field('user.invit_time, sum(order.total_fee) as total_fee, order.create_time');
            $query->alias('user');
            $query->join('__ORDER__ order', 'user.id = order.uid', 'left');
            $query->where('user.invit', $uid);
            $query->where('order.status', '>', 0);
            $query->where('order.create_time', 'between', [$boef_time,$boef_time+86400]);
			$query->group('order.uid');
        });
             
        $rebates['today_invit_count'] = 0;
        $today_invit_consumption = 0;
        foreach ($invits as $invit){
            if($invit['invit_time'] == $boef_time) $rebates['today_invit_count'] += 1;
             
            if(strtotime(date('Ymd',$invit['create_time'])) == $boef_time) $today_invit_consumption += $invit['total_fee'];
        }
         
        if($rebates['today_invit_count'] == 0){
            //当天没有要求下线成员，默认5%返利
            $rebates['invit_rebate'] = 5;
            $rebates['invit_money'] = sprintf("%.2f", $today_invit_consumption*$invitInitScale/100);
        }else{
            $rebates['invit_rebate'] = min($invitInitScale+($rebates['today_invit_count']-1)*$invitIncScale, $invitMaxScale);
            $rebates['invit_money'] = sprintf("%.2f", $today_invit_consumption*$rebates['invit_rebate']/100);
        }  
        
        return $rebates;
    }
    
    /**
    * 退款申请
    * @date: 2018年7月27日 上午9:00:18
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    private function refund($order, $type = 1){
        $config = $this->config;
        
        //退款申请参数构造
        if($order){
            $refunddorder = array(
                'appid'			=> $config['appid'],
                'mch_id'		=> $config['pay_mchid'],
                'nonce_str'		=> self::getNonceStr(),
                'out_trade_no'	=> $order->out_trade_no,
                'out_refund_no' => $order->out_trade_no . md5($order->out_trade_no),//退款唯一单号，系统生成
                'total_fee'		=> $order->total_fee * 100,
                'refund_fee'    => $type == 1 ? $order->refund_fee * 100 : $order->total_fee * 100,//退款金额,通过计算得到要退还的金额
            );
            
            $refunddorder['sign'] = self::makeSign($refunddorder);
            
            //请求数据
            $xmldata = self::array2xml($refunddorder);
            $url = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
            $res = self::postXmlCurl($xmldata, $url, true);
            $resData = $this->xml2array($res);
            
            if($resData['return_code'] === 'SUCCESS' && $resData['return_msg'] === 'OK' && $resData['result_code'] === 'SUCCESS'){
                if($type == 1){
                    $orderModel = new OrderModel();
                    $orderModel->where('out_trade_no', $resData['out_trade_no'])->update(['refund'=>1]);                    
                }
                
                return true;
            }
            
            return false;
        }
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    protected function getNonceStr($length = 32) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }
    
    /**
     * 生成签名
     * @return 签名
     */
    protected function makeSign($data){
        //获取微信支付秘钥
        $key = $this->config['pay_apikey'];
        // 去空
        $data=array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a=http_build_query($data);
        $string_a=urldecode($string_a);
        //签名步骤二：在string后加入KEY
        //$config=$this->config;
        $string_sign_temp=$string_a."&key=".$key;
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        // 签名步骤四：所有字符转为大写
        $result=strtoupper($sign);
        return $result;
    }

    /**
     * 将一个数组转换为 XML 结构的字符串
     * @param array $arr 要转换的数组
     * @param int $level 节点层级, 1 为 Root.
     * @return string XML 结构的字符串
     */
    protected function array2xml($arr, $level = 1) {
        $s = $level == 1 ? "<xml>" : '';
        foreach($arr as $tagname => $value) {
            if (is_numeric($tagname)) {
                $tagname = $value['TagName'];
                unset($value['TagName']);
            }
            if(!is_array($value)) {
                $s .= "<{$tagname}>".(!is_numeric($value) ? '<![CDATA[' : '').$value.(!is_numeric($value) ? ']]>' : '')."</{$tagname}>";
            } else {
                $s .= "<{$tagname}>" . $this->array2xml($value, $level + 1)."</{$tagname}>";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
        return $level == 1 ? $s."</xml>" : $s;
    }
    
    /**
     * 将xml转为array
     * @param  string 	$xml xml字符串
     * @return array    转换得到的数组
     */
    protected function xml2array($xml){
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $result= json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $result;
    }    

	/**
	 * 以post方式提交xml到对应的接口url
	 * 
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @throws WxPayException
	 */
	private static function postXmlCurl($xml, $url, $useCert = false, $second = 30, $isPost = true)
	{		
		$ch = curl_init();
		//设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
		curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);//严格校验
		
		//设置header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		
		//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	
		if($useCert == true){
			//设置证书
			//使用证书：cert 与 key 分别属于两个.pem文件
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, './cert/apiclient_cert.pem');
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, './cert/apiclient_key.pem');
		}
		
		//post提交方式
		if($isPost){
		    curl_setopt($ch, CURLOPT_POST, TRUE);
		    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		}
		
		//运行curl
		$data = curl_exec($ch);
		
		//返回结果
		if($data){
			curl_close($ch);
			return $data;
		} else { 
			$error = curl_errno($ch);
			curl_close($ch);
			throw new WxPayException("curl出错，错误码:$error");
		}
	}
}