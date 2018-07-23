<?php
namespace app\api\controller;
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/12
 * Time: 16:53
 */
use think\Controller;
use think\Request;
use app\common\model\CommentsModel;

class CommentController extends Controller{
    
    /**
    * 商品评价
    * @date: 2018年7月19日 下午2:23:40
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function goodRank(Request $request){
        $gid = $request->param('id', 0, 'intval');
        $rank = $request->param('idx_value', 0, 'intval');
        $limit = $request->param('limit', 10, 'intval');
        $page = $request->param('page', 1, 'intval');
        
        if($gid == 0) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);
        
        $commentsModel = new CommentsModel();
        $ranks = $commentsModel::all(function($query) use($gid,$rank,$page,$limit){
            $query->where('gid', $gid);
            if(!empty($rank)){
                $query->where('rank', $rank);
            }
            $query->where('status', 1);
            $query->order('addtime desc');
            $query->limit(($page-1)*$limit, $limit);
        });
        
        if(!empty($ranks)){
            foreach($ranks as $key=>$row){
                $ranks[$key]['addtime'] = date("Y-m-d H:i:s",$row['addtime']);
                $ranks[$key]['buyer_nickname'] = db("member")->where(['uid'=>$row['uid']])->value('nickname');
            }
            return json(['code'=>0, 'msg'=>'调用成功', 'data'=>$ranks, 'paginate'=>array('page'=>sizeof($ranks) < 10 ? $page : $page+1, 'limit'=>$limit)]);
        }else{
            return json(['code'=>1, 'msg'=>'调用失败', 'data'=>[]]);
        }
    }

    /**
     * 获取 评论数量
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_commentnum(Request $request){
        $gid = $request->param('id', 0, 'intval');

        if($gid == 0) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);

        $commentsModel = new CommentsModel();
        $commentNums[0] = $commentsModel->where(['gid'=>$gid,'rank'=>1])->count();

        $commentNums[1] = $commentsModel->where(['gid'=>$gid,'rank'=>2])->count();

        $commentNums[2] = $commentsModel->where(['gid'=>$gid,'rank'=>3])->count();

        if($commentNums){
            return json(['code'=>0,'msg'=>'success','data'=>$commentNums]);
        }else{
            return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);
        }
    }
    
    /**
    * 添加评价
    * @date: 2018年7月19日 下午2:24:12
    * @author: onep2p <324834500@qq.com>
    * @param: variable
    * @return:
    */
    public function addRank(Request $request){
        $gid = $request->param('gid', 0, 'intval');
        $uid = $request->param('uid', 0, 'intval');
        $rank = $request->param('rank', 1, 'intval');
        $message = $request->param('message', '', 'op_t');
        
        if($gid == 0 || $uid == 0 || empty($message)) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);
        
        $data['gid'] = $gid;
        $data['uid'] = $uid;
        $data['rank'] = $rank;
        $data['message'] = $message;
        $data['addtime'] = time();
        
        $commentsModel = new CommentsModel();
        $commentsModel->data($data);
        
        if($commentsModel->save()){
            return json(['code'=>0,'msg'=>'评价成功']);
        }else{
            return json(['code'=>1,'msg'=>'评价失败']);
        }
    }


    /**
     * 商品评价添加
     * @param Request $request
     * @return \think\response\Json
     * User: 离殇<pengxuancom@163.com>
     */
    public function reputation(Request $request){
        $orderId = $request->param('orderId',0,'intval');
        $uid = $request->param('uid',0,'intval');
        $reputations = $request->param('reputations/a');
        if($orderId == 0 || $uid == 0 || empty($reputations)) return json(['code'=>1, 'msg'=>'参数错误', 'data'=>[]]);

        $data = [];
        $commentsModel = new CommentsModel();
        $res = 0;
        foreach($reputations as $row){
            $data['orderid'] = $orderId;
            $data['gid'] = $row['id'];
            $data['rank'] = $row['reputation'];
            $data['message'] = $row['remark'];
            $data['uid'] = $uid;
            $data['addtime'] = time();
            $commentsModel->data($data);
            if($commentsModel->save()){
                $res++;
            }
        }

        if($res==count($reputations)){
            db("order")->where(['id'=>$orderId,'uid'=>$uid])->update(['status'=>4]);
            return json(['code'=>0,'msg'=>'评价成功']);
        }else{
            return json(['code'=>1,'msg'=>'评价失败']);
        }

    }


}