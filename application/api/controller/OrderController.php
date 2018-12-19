<?php
namespace app\api\controller;

use think\Controller;
use think\Request;
use app\common\model\ProductModel;
use app\common\model\OrderModel;
use app\common\model\CouponModel;
use think\Db;
use app\common\model\FundsModel;
use phpDocumentor\Reflection\Types\This;
use SebastianBergmann\Exporter\Exporter;

class OrderController extends Controller{
    private $stock_product_name = "";
    
    public function create_order(Request $request){
        //接收订单信息
        $uid = $request->param('uid', '', 'intval');
        $product_info = $request->param('product_info/a');
        $address_id = $request->param('address_id', '', 'intval');
        $coupon_num = $request->param('coupon_num', 0, 'intval');
        $remark = $request->param('remark', '', 'op_t');
        $distribution = $request->param('index', -1, 'intval');
		
		$this->writeGetDataLog("预计购买的商品信息：".$product_info);
        
        if(empty($uid) || empty($product_info) || empty($address_id)) return json(['code'=>1, 'msg'=>'调用失败', 'data'=>['info'=>'参数错误']]);
        
        //分析订单数据
        $total_fee = $this->total_fee($uid, $product_info, $address_id, $coupon_num, $remark, $distribution);
        if(isset($total_fee['total_fee'])){
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$total_fee]);
        }else{
            return json(['code'=>1, 'msg'=>$total_fee, 'data'=>[]]);
        }
    }
    
    /**
    * 退款明细
    * @date: 2018年7月24日 上午9:12:16
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function refund(Request $request){
        $uid = $request->param('uid', '', 'intval');
        $limit = $request->param('limit', 10, 'intval');
        $page = $request->param('page', 1, 'intval');
        
        if(empty($uid)) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);
        
        $fundsModel = new FundsModel();
        
        $funds = $fundsModel::all(function($query) use($uid,$page,$limit){
            $query->where('uid', $uid);
            $query->where('date', '>', 0);
            $query->order('addtime desc');
            $query->limit(($page-1)*$limit, $limit);
        });
        
        if($funds){
            foreach ($funds as &$fund){
                $fund['product_info'] = json_decode($fund['product_info'], true);
            }
            
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$funds, 'paginate'=>array('page'=>sizeof($funds) < $limit ? $page : $page+1, 'limit'=>$limit)]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }
    
    /**
    * 计算提交订单最终总价
    * @date: 2018年7月4日 上午9:41:29
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    private function total_fee($uid, $product_info, $address_id, $coupon_num, $remark, $distribution){
        $user = Db::table('__UCENTER_MEMBER__')->alias('um')->field("um.id,um.username,c.coupon_num")->join('__COUPON__ c', 'um.id = c.uid', 'LEFT')->where('um.id = ' . $uid)->find();
        
        /* 读取数据库中的配置 */
        $config = cache('DB_CONFIG_DATA');
        if (!$config) {
            $config = controller("common/ConfigApi")->lists();
            cache('DB_CONFIG_DATA', $config);
        }
        config($config); //添加配置
        
        $freight = config('FREIGHT_QUOTA');//默认为0，没有满免
        $coupon_max = config('COUPON_MAXCOUNT');//最大使用优惠券的数量   默认为5
        $coupon_price = config('COUPON_DENOMINATION');//没设置的情况下默认为1分钱
        $coupon_num = intval($coupon_num);
        
        if(!empty($user['id']) && $user['id'] != 1 && $coupon_num <= $coupon_max && $coupon_num <= $user['coupon_num']){
            $total_fee = 0;//订单总价
            $productList = [];//商品信息
            $order_data = [];//订单数据
            
//            $product_info = json_decode($product_info, true);
            $productArr = [];
            $productIds = [];
            if(sizeof($product_info) > 0){
                foreach ($product_info as $key=>$val){
                    $productArr[$val['good_id']] = $val['num'];
                    $productIds[] = $val['good_id'];
                }
            }else{
                return -3;
            }            
            
            $productModel = new ProductModel();
            $products = $productModel::all(function($query) use($productIds){
                $query->where('id', 'in', $productIds);
                $query->where('status', 1);
            });



            Db::startTrans();
            try {
                foreach($products as $item=>$product){
                    if($product->status < 1){
                        exception('“'.$product->name.'”已经下架，请删除该商品后再支付订单');
                    }else{
                        //判断限购、库存
                        if($product->stock < $productArr[$product->id] || ($product->isXg == 1 && $productArr[$product->id] > 1)){
                            exception('“'.$product->name.'”库存不足');
                        }else{
                            if(!Db::table("cms_product")->where('id', $product->id)->setDec('stock', $productArr[$product->id])){
                                exception('“'.$product->name.'”库存不足');
                            }
                        }
                    
                        $total_fee += $product->price*$productArr[$product->id];
                        
                        $productList[] = [
                            'id'=>$product->id,
                            'name'=>$product->name,
                            'cover'=>get_cover(explode(',', $product['cover'])[0], 'path'),
                            'price'=>$product->price,
                            'num'=>$productArr[$product->id]
                        ]; 
                    }
                }
				
				if(count($productList) > 0){
					$order_data['uid'] = $uid;
					$order_data['product_info'] = json_encode($productList);
					$order_data['coupon'] = $coupon_num;
					$order_data['address_id'] = $address_id;
					$order_data['remark'] = $remark;
					$order_data['create_time'] = time();
					$order_data['out_trade_no'] = 'YF'.date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
					$order_data['distribution'] = $distribution;
		
					//处理运费满减
					if($total_fee < $freight) {
						$total_fee += 2;//如果总价没到满免运费的情况下，统一收取2元的运费
						$order_data['freight'] = 2;
					}
					
					//处理优惠券
					if($coupon_num > 0) {
						if(!Db::table("cms_coupon")->where('uid', $uid)->setDec('coupon_num', $coupon_num)){
							exception('下单失败');
						}
						
						$total_fee -= $coupon_num*$coupon_price;
					}
					
					$order_data['total_fee'] = $total_fee;

					if(Db::table("cms_order")->insert($order_data)){
						Db::commit();
						return ['total_fee'=>$total_fee, 'out_trade_no'=>$order_data['out_trade_no']];
					}else{
						exception('下单失败');
					}					
				}else{
					exception('下单失败');
				}
            } catch (\Exception $e) {
                Db::rollback();
                $this->writeGetDataLog($e->getMessage());
                return $e->getMessage();
            }
        }else{
            return '参数异常';//错误的用户ID
        }
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
}