<?php

namespace addons\CheckIn\Model;
use think\Model;

/**
 * Class CheckInModel 签到模型
 * @package addons\CheckIn\Model
 */
class CheckInModel extends Model{
    protected $tableName = 'checkin';
    public function getCheck($uid){
        $time = get_some_day(0);
        $res = cache('check_in_'.$uid.'_'.$time);
        if(empty($res)){
            $res = $this->where(['uid'=>$uid,'create_time'=>['egt',$time]])->find();
            $check = query_user(['con_check','total_check'],$uid);
            $res = array_merge($res,$check);
            cache('check_in_'.$uid.'_'.$time,$res,60*60*24);
        }
        return $res;
    }

    public function addCheck($uid){
        $data['uid'] = $uid;
        $data['create_time'] = time();
        return $this->save($data);
    }

    public function resetConCheck()
    {
        $memberModel = db('Member');
        $time = get_some_day(0);
        $time_yesterday = get_some_day(1);
        $users = $memberModel->where(['con_check' => ['gt', 0]])->field('uid')->select();
        foreach($users as $val) {
            $check = $this->where(['uid' => $val['uid'], 'create_time' => ['between', [$time_yesterday, $time]]])->find();
            if(!$check) {
                $memberModel->where(['uid' => $val['uid']])->setField('con_check', 0);
            }
        }
    }

    public function getRank($type){
        $time = get_some_day(0);
        $time_yesterday = get_some_day(1);
        $memberModel = db('Member');
        switch($type){
            case 'today' :
                $list = $this->where(['create_time'=>['egt',$time]])->order('create_time asc')->limit(5)->select();
                break;
            case 'con' :
                $uids = $this->where(['create_time'=>['egt',$time_yesterday]])->field('uid')->select();
                $uids = getSubByKey($uids,'uid');
                $list = $memberModel ->where(['uid'=>['in',$uids]])->field('uid,con_check')->order('con_check desc,uid asc')->limit(5)->select();
                break;
            case 'total' :
                $list = $memberModel ->field('uid,total_check')->order('total_check desc,uid asc')->limit(5)->select();
                break;
        }

        foreach($list as &$v){
            $v['user'] = query_user(['avatar32','avatar64','space_url', 'nickname', 'uid'], $v['uid']);
        }
        unset($v);
        return $list;
    }

}
