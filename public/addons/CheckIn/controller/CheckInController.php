<?php

namespace addons\CheckIn\Controller;

use app\home\controller\AddonsController;

class CheckInController extends AddonsController
{

    /**
     * 我的签到列表（某个月）
     */
    public function signList()
    {
        if (!is_login()) {
            return false;
        }
        $aTime = input('post.show_month', '0', 'intval');
        if (!$aTime) {
            return false;
        }
        $startTime = $this->_getStartTime($aTime);
        $map['uid']=is_login();
        $map['create_time']=['between',[$startTime,strtotime(time_format($startTime)."+42 day")]];
        $objModel = get_Addons_model('CheckIn');
        $checkInfoModel                 = new $objModel;
        $checkInList=$checkInfoModel->where($map)->select();
        $checkInList=array_column($checkInList,'create_time');
        $checked_days= [];
        foreach($checkInList as $val){
            //当前日历面板上第一天已签到（从0开始计算）
            $checked_days[]=intval((intval($val)-$startTime)/(24*60*60));
        }
        unset($val);
        $checked_days=array_unique($checked_days);
        $this->ajaxReturn($checked_days);
    }

    /**
     * 获取日历上第一天的时间戳
     * @param $aTime 本月的某一天
     * @return int 日历上第一天开始时间戳
     */
    private function _getStartTime($aTime)
    {
        $firstday = date('Y-m-01 00:00', $aTime);
        $week = date("w", strtotime($firstday));
        $week==0&&$week=7;
        $startTime =strtotime("$firstday -".(intval($week)-1)." day");
        return $startTime;
    }

    public function doCheckIn()
    {

        if (!is_login()) {
            $this->error('请先登陆！');
        }


        $name = get_addon_class('CheckIn');
        $class = new $name();
        $config = $class->getConfig();

        if (empty($config['action'])||in_array('no_action',$config['action'])) {
            $res = $class->doCheckIn();
            if ($res) {
                $uid = is_login();
                $check = query_user(['con_check', 'total_check'], $uid);
                $this->ajaxReturn(['status' => 1, 'info' => '签到成功!', 'con_check' => $check['con_check'], 'total_check' => $check['total_check']]);
            } else {
                $this->error('已经签到了！');
            }
        } else {
            $action_info = db('Action')->where(['name'=>['in',$config['action']]])->select();
            $str='';
            foreach($action_info as $val){
                $str.='['.$val['title'].']';
            }
            unset($val);
            $this->error('只支持' . $str . '来签到！');
        }

    }

    public function ranking()
    {
        $aPage = input('page', 1, 'intval');
        $aOrder = input('order', 'total_check', 'op_t');
        $objModel = get_Addons_model('CheckIn');
        $checkInfoModel                 = new $objModel;
        $memberModel = db('Member');
        $limit = 50;
        if ($aOrder == 'today') {
            $user_list = $checkInfoModel->field('uid,create_time')->page($aPage, $limit)->where(['create_time' => ['egt', get_some_day(0)]])->order('create_time asc, uid asc')->select();
            $totalCount = $checkInfoModel->where(['create_time' => ['egt', get_some_day(0)]])->count();
            foreach ($user_list as $key => &$val) {

                $val['ranking'] = ($aPage - 1) * $limit + $key + 1;
                if ($val['ranking'] <= 3) {
                    $val['ranking'] = '<span style="color:#EB7112;">' . $val['ranking'] . '</span>';
                }
                $val['status'] = '<span>已签到 ' . friendlyDate($val['create_time']) . '</span>';
                $user = query_user(['uid', 'nickname', 'total_check', 'con_check'], $val['uid']);
                $val = array_merge($val, $user);
            }
            unset($key, $val);

        } else {
            $user_list = $memberModel->field('uid,nickname,total_check,con_check')->page($aPage, $limit)->order($aOrder . ' desc,uid asc')->select();
            $totalCount = $memberModel->count();
            foreach ($user_list as $key => &$val) {
                $val['ranking'] = ($aPage - 1) * $limit + $key + 1;
                if ($val['ranking'] <= 3) {
                    $val['ranking'] = '<span style="color:#EB7112;">' . $val['ranking'] . '</span>';
                }
                $check = $checkInfoModel->getCheck($val['uid']);
                if ($check) {
                    $val['status'] = '<span>已签到 ' . friendlyDate($check['create_time']) . '</span>';
                } else {
                    $val['status'] = '<span style="color: #BDBDBD;">未签到</span>';
                }
            }
        }

        foreach ($user_list as &$u) {
            $temp_user = query_user(['nickname'], $u['uid']);
            $u['nickname'] = $temp_user['nickname'];
        }
        unset($u);

        $this->assign('user_list', $user_list);
        $this->assign('totalCount', $totalCount);
        if (is_login()) {
            //获取用户信息
            $user_info = query_user(['uid', 'nickname', 'space_url', 'avatar64', 'con_check', 'total_check'], is_login());

            $check = $checkInfoModel->getCheck(is_login());
            if ($check) {
                $user_info['is_sign'] = $check['create_time'];
            } else {
                $user_info['is_sign'] = 0;
            }

            if ($aOrder == 'today') {
                $ranking = $checkInfoModel->field('uid')->where(['create_time' => ['egt', get_some_day(0)]])->order('create_time asc, uid asc')->select();
            } else {
                $ranking = $memberModel->field('uid')->order($aOrder . ' desc,uid asc')->select();
            }


            $ranking = getSubByKey($ranking, 'uid');
            if (array_search(is_login(), $ranking) === false) {
                $user_info['ranking'] = count($ranking) + 1;
            } else {
                $user_info['ranking'] = array_search(is_login(), $ranking) + 1;
            }

            $uid = is_login();
            $user_info['con_check'] = $memberModel->where(['uid' => $uid])->value('con_check');
            $user_info['total_check'] = $memberModel->where(['uid' => $uid])->value('total_check');

            $this->assign('user_info', $user_info);
        }
        $this->assign('order', $aOrder);
        echo  $this->fetch(T('Addons://CheckIn@CheckIn/ranking'));
    }
}