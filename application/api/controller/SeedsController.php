<?php
namespace app\api\controller;

use think\Controller;
use think\Request;

class SeedsController extends Controller{

    /**
     * 列表
     * @date: 2018年6月6日 下午2:07:02
     * @param: variable
     * @return:
     */
    public function seeds_list(Request $request){
        $model = db('seeds');
        $limit = $request->param('limit', 10, 'intval');
        $page = $request->param('page', 1, 'intval');
        $uid = $request->param('uid', 0, 'intval');

        if($page == 'undefined' || $page == '') $page = 1;

        $list = $model
            ->where('status > 0 ')
            ->order('sort ASC')
            ->limit(($page-1)*$limit, $limit)
            ->select();

        if(!empty($list)){
            foreach ($list as &$item){
                $item['cover'] = get_cover(explode(',', $item['cover'])[0], 'path');
            }

            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$list, 'paginate'=>array('page'=>sizeof($list) < $limit ? $page : $page+1, 'limit'=>$limit)]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }

    /**
     * 详情
     * @date: 2018年6月6日 下午2:48:33
     * @param: variable
     * @return:
     */
    public function detail(Request $request){
        $model = db('seeds_user');
        $uid = $request->param('uid', 0, 'intval');

        if($uid == 0) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);

        $info = $model
            ->where(['status'=>0,'uid'=>$uid])
            ->find();

        if(!empty($info)){
            $seeds = db("seeds")->where(['id'=>$info['sid']])->find();
            if(!empty($seeds['cover'])){
                foreach (explode(',', $seeds['cover']) as $item){
                    $images[] = get_cover($item, 'path');
                }
                $info['cover'] = $images;
            }
            if(!empty($seeds['adult_cover'])){
                foreach (explode(',', $seeds['adult_cover']) as $item){
                    $images1[] = get_cover($item, 'path');
                }
                $info['adult_cover'] = $images1;
            }
            if($info['exp'] == $info['sum_exp'])  $info['cover'] = $info['adult_cover'];
            $info['name'] = $seeds['name'];
            $info['stock'] = $seeds['stock'];
            $info['price'] = $seeds['price'];
            $info['unit'] = $seeds['unit'];
            $info['content'] = $seeds['content'];
            $info['total_sales'] = $seeds['total_sales'];
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$info]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }

    /**
     * 领取种子
     * @param Request $request
     * @return \think\response\Json
     */
    public function  ling_seeds(Request $request){
        $model = db("seeds_user");
        $sid = $request->param("sid",0,'intval');
        $uid = $request->param('uid',0,'intval');

        if($sid == 0)   return json(['code'=>1,'msg'=>'缺少参数']);

        if($model->where(['uid'=>$uid,'status'=>0])->count()==0){
            $info = db("seeds")->where(['id'=>$sid])->find();
            $data['sid'] = $sid;
            $data['uid'] = $uid;
            $data['sum_exp'] = $info['sum_exp'];
            $data['status'] = 0;
            $data['create_time'] = $data['update_time'] = time();
            if($model->insert($data)){
                return json(['code'=>0,'msg'=>'领取成功']);
            }else{
                return json(['code'=>1,'msg'=>'服务器繁忙']);
            }
        }else{
            return json(['code'=>1,'msg'=>'还有成长中的种子']);
        }
    }

    /**
     * 检查用户是否有成长中的种子
     * @param Request $request
     * @return \think\response\Json
     */
    public function check_seeds(Request $request){
        $model = db("seeds_user");
        $uid = $request->param('uid',0,'intval');

        if(empty($uid))  return json(['code'=>1,'msg'=>'缺少参数']);

        $count = $model->where(['uid'=>$uid,'status'=>0])->count();

        return json(['code'=>0,'data'=>$count]);

   }

    /**
     * 点赞灌水
     * @param Request $request
     * @return \think\response\Json
     * @throws \think\Exception
     */
    public function  dian_seeds(Request $request){
        $model = db("seeds_dian");
        $uid = $request->param('uid',0,'intval');
        $suid = $request->param("suid",0,'intval');
        if(empty($uid) || empty($suid))  return json(['code'=>1,'msg'=>'缺少参数']);

        $start_time = strtotime(date('Ymd'));
        $end_time = strtotime(date('Ymd')) + 86400;

        if(db("seeds_user")->where(['id'=>$suid,'status'=>1])->count()>0)   return json(['code'=>1,'msg'=>'该种子已长大']);

        $where =  "suid=".$suid." and uid=".$uid." and (create_time>=".$start_time." and create_time<=".$end_time.")";

        $count = $model->where($where)->count();

        if($count==0){
            $data['suid'] = $suid;
            $data['uid'] = $uid;
            $data['create_time'] = time();
            $data['exp_value'] = mt_rand(50,400);
            $data['remark'] = "为你增加了".$data['exp_value']."点经验";
            if($model->insert($data)){
                $info = db("seeds_user")->where(['id'=>$suid])->find();
                $exp = $info['exp']+$data['exp_value'];
                if($exp<$info['sum_exp']){
                    db("seeds_user")->where(['id'=>$suid])->update(['exp'=>$exp,'update_time'=>time()]);
                }else if($exp==$info['sum_exp']){
                    db("seeds_user")->where(['id'=>$suid])->update(['exp'=>$exp,'update_time'=>time(),'status'=>1]);
                }
                return json(['code'=>0,'msg'=>'点赞成功']);
            }else{
                return json(['code'=>1,'msg'=>'系统繁忙']);
            }
        }else{
            return json(['code'=>1,'msg'=>'每人每天只能点赞一次']);
        }

    }

    /**
     * 获取点赞列表
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_dian_seeds(Request $request){
        $model = db("seeds_dian");
        $suid = $request->param("suid",0,'intval');
        $limit = $request->param('limit', 10, 'intval');
        $page = $request->param('page', 1, 'intval');
        if(empty($suid))  return json(['code'=>1,'msg'=>'缺少参数']);

        $list = $model
            ->where(['suid'=>$suid])
            ->order('create_time desc')
            ->limit(($page-1)*$limit, $limit)
            ->select();

        if(!empty($list)){
            foreach ($list as $key=>$row){
                $list[$key]['create_time'] = date("Y-m-d H:i:s",$row['create_time']);
                $list[$key]['buyer_nickname'] = db("member")->where(['uid'=>$row['uid']])->value('nickname');
            }
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$list, 'paginate'=>array('page'=>sizeof($list) < $limit ? $page : $page+1, 'limit'=>$limit)]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }

    }
}