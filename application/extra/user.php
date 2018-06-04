<?php
/**
 * check_username  根据type或用户名来判断注册使用的是用户名、邮箱或者手机
 * @param $username
 * @param $email
 * @param $mobile
 * @param int $type
 * @return bool
 */
function check_username(&$username, &$email, &$mobile, &$type = 0)
{

    if ($type) {
        switch ($type) {
            case 2:
                $email = $username;
                $username = '';
                $mobile = '';
                $type = 2;
                break;
            case 3:
                $mobile = $username;
                $username = '';
                $email = '';
                $type = 3;
                break;
            default :
                $mobile = '';
                $email = '';
                $type = 1;
                break;
        }
    } else {
        $check_email = preg_match("/[a-z0-9_\-\.]+@([a-z0-9_\-]+?\.)+[a-z]{2,3}/i", $username, $match_email);
        $check_mobile = preg_match("/^(1[0-9])[0-9]{9}$/", $username, $match_mobile);
        if ($check_email) {
            $email = $username;
            $username = '';
            $mobile = '';
            $type = 2;
        } elseif ($check_mobile) {
            $mobile = $username;
            $username = '';
            $email = '';
            $type = 3;
        } else {
            $mobile = '';
            $email = '';
            $type = 1;
        }
    }
    return true;
}

/**
 * check_reg_type  验证注册格式是否开启
 * @param $type
 * @return bool
 */
function check_reg_type($type){
    //$t[1] = $t['username'] ='username';
    $t[2] = $t['email'] ='email';
    $t[3] = $t['mobile'] ='mobile';

    $switch = modC('REG_SWITCH','email','USERCONFIG');
    if($switch){
        $switch = explode(',',$switch);
        if(in_array($t[$type],$switch)){
           return true;
        }
    }
    return false;

}


/**
 * check_login_type  验证登录提示信息是否开启
 * @param $type
 * @return bool
 */
function check_login_type($type){
    $t[1] = $t['username'] ='username';
    $t[2] = $t['email'] ='email';
    $t[3] = $t['mobile'] ='mobile';

    $switch = modC('LOGIN_SWITCH','username','USERCONFIG');
    if($switch){
        $switch = explode(',',$switch);
        if(in_array($t[$type],$switch)){
            return true;
        }
    }
    return false;

}

/**
 * get_next_step  获取注册流程下一步
 * @param string $now_step
 * @return string
 */
function get_next_step($now_step =''){

    $step = get_kanban_config('REG_STEP', 'enable','', 'USERCONFIG');
    if(empty($now_step) || $now_step == 'start'){
        $return = $step[0];
    }else{
        $now_key = array_search($now_step,$step);
        $return = $step[$now_key+1];
    }
    if(!in_array($return,array_keys(controller('Ucenter/RegStep','Widget')->mStep)) || empty($return)){
        $return = 'finish';
    }
    return $return;
}


/**
 * check_step
 * @param string $now_step
 * @return string
 */
function check_step($now_step=''){
    $step = get_kanban_config('REG_STEP', 'enable','', 'USERCONFIG');
    if(array_search($now_step,$step)){
        $return = $now_step;
    }
    else{
        $return = $step[0];
    }
    return $return;
}


/**
 * set_user_status   设置用户状态
 * @param $uid
 * @param $status
 * @return bool
 */
function set_user_status($uid,$status){
    $memberModel = new \app\common\model\MemberModel();
    $memberModel->where(['uid'=>$uid])->setField('status',$status);
    UCenterMember()->where(['id'=>$uid])->setField('status',$status);
    return true;
}

/**
 * set_users_status   批量设置用户状态
 * @param $map
 * @param $status
 * @return bool
 */
function set_users_status($map,$status){
    $memberModel = new \app\common\model\MemberModel();
    $memberModel->where($map)->setField('status',$status);
    UCenterMember()->where($map)->setField('status',$status);
    return true;
}

/**
 * check_step_can_skip  判断注册步骤是否可跳过
 */
function check_step_can_skip($step){
    $skip = modC('REG_CAN_SKIP','', 'USERCONFIG');
    $skip = explode(',',$skip);
    if(in_array($step,$skip)){
        return true;
    }
    return false;
}



function check_and_add($args){
    $memberModel = new \app\common\model\MemberModel();

    $uid = $args['uid'];

    $check = $memberModel->find($uid);
    if(!$check){
        $args['status'] =1;
        $memberModel->allowField(true)-> save($args);
    }
    return true;
}

/**
 * get_at_uids  获取@的用户的uid
 * @param $content
 * @return array
 */
function get_at_uids($content)
{
    $uids = get_at_users($content);
    return $uids;
}

/**
 * get_at_usernames  获取@用户的用户名
 * @param $content
 * @return array
 */
function get_at_users($content)
{
    //正则表达式匹配
    $user_pattern = '/\[at:(\d*)\]/';
    preg_match_all($user_pattern, $content, $users);

    //返回用户名列表

    return array_unique($users[1]);
}

/**
 * 获取@用户昵称
 * @param $content
 * @return array
 */
function get_at_usersnickname($content)
{
    //正则表达式匹配
    $user_pattern= '/\@\[[A-Za-z0-9_\x{4e00}-\x{9fa5}\x80-\xff]+\]/u';
    preg_match_all($user_pattern, $content, $users);
    $k=0;
    $nickname=[];
    foreach($users[$k] as $v){
        $nickname[]=substr($v,2,strlen($v)-3);
        $k++;
    }
    return array_unique($nickname);
}