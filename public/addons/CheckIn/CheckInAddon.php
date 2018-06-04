<?php

namespace addons\CheckIn;

use app\common\controller\Addon;
use app\common\model\MemberModel;
use app\common\model\ScoreModel;

/**
 * 签到插件
 */
class CheckInAddon extends Addon
{

    public $info = [
        'name' => 'CheckIn',
        'title' => '签到',
        'description' => '签到插件',
        'status' => 1,
        'author' => 'think28',
        'version' => '1.0.0'
    ];

    public function install()
    {
        $prefix = config("database.prefix");
        db()->execute("DROP TABLE IF EXISTS `{$prefix}checkin`");
        db()->execute(<<<SQL
CREATE TABLE IF NOT EXISTS `{$prefix}checkin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `create_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
SQL
        );

        db()->execute(<<<SQL
        ALTER TABLE  `{$prefix}member` ADD  `con_check` INT NOT NULL DEFAULT  '0',
ADD  `total_check` INT NOT NULL DEFAULT  '0';
SQL
        );


        return true;
    }

    public function uninstall()
    {

        $prefix = config("database.prefix");
        db()->execute("DROP TABLE IF EXISTS `{$prefix}checkin`");

        db()->execute(<<<SQL
ALTER TABLE `{$prefix}member`
  DROP `con_check`,
  DROP `total_check`;
SQL
        );
        return true;
    }


    public function checkIn($param)
    {
        $model = $this->checkInModel();
        $uid = is_login();
        $check = $model->getCheck($uid);
        $user_info = query_user(array('con_check', 'total_check'), $uid);
        $this->assign('user_info', $user_info);
        $this->assign('check', $check);
        $this->assignDate();
        echo $this->fetch('View/checkin');
    }

    private function checkInModel()
    {
        $objModel = get_Addons_model('CheckIn');
        $checkInfoModel                 = new $objModel;
        return $checkInfoModel;
    }

    private function assignDate()
    {
        $week = date('w');
        switch ($week) {
            case '0':
                $week = '周日';
                break;
            case '1':
                $week = '周一';
                break;
            case '2':
                $week = '周二';
                break;
            case '3':
                $week = '周三';
                break;
            case '4':
                $week = '周四';
                break;
            case '5':
                $week = '周五';
                break;
            case '6':
                $week = '周六';
                break;
        }
        $this->assign('day', date('Y.m.d'));
        $this->assign('week', $week);

    }


    public function doCheckIn()
    {

        $time = get_some_day(0);
        $uid = is_login();

        $model = $this->checkInModel();
        $memberModel = new MemberModel();
        $scoreModel= new ScoreModel() ;
        $check = $model->getCheck($uid);
        if (!$check) {
            $model->addCheck($uid);
            $memberModel->where(['uid' => $uid])->setInc('total_check');
            //签到积分奖励 从addons表获得设置的类型和积分数

            $arrconf=get_addon_config('CheckIn');
            array_shift($arrconf);
            $new=array_filter($arrconf);

            foreach($new as $k=>$v){
                $k1=substr($k,5,strlen($k)-5);
                $scoreModel->setUserScore($uid, $v,$k1,'inc', 'weibo',$uid,'签到['.$k.']类型积分+['.$v.']');
                $scoreModel->addScoreLog($uid, $k1, 'inc',$v, 'weibo',$uid,'签到['.$k.']类型积分+['.$v.']');
            }

            $memberModel->where(['uid' => $uid])->setInc('con_check');
            clean_query_user_cache($uid, ['con_check', 'total_check','score1']);
            cache('check_rank_today_' . $time, null);
            cache('check_rank_con_' . $time, null);
            cache('check_rank_total_' . $time, null);

            return true;
        } else {
            return false;
        }
    }


    public function handleAction($param)
    {
        $typedata=db('ucenter_score_type');
        $arrconf=get_addon_config('CheckIn');
        array_shift($arrconf);
        $arrkey=array_keys($arrconf);
        foreach($arrkey as $k){
            $k=substr($k,5,strlen($k)-5);
            $typename[]=$typedata->where('id='.$k)->value('title');
        }
        $p=0;
        foreach($arrconf as $f){
            $newarr[$typename[$p]]=$f;
            $p++;
        }
        unset($f);
        $new=array_filter($newarr);//类型名+积分数  除去空元素
        $str='';
        foreach($new as $t=>$v){
            $str.=$t.'+'.$v.'!';
        }
        $config = $this->getConfig();
        if (!empty($config['action'])) {
            $action_info = db('Action')->where(['name'=>['in',$config['action']]])->field('id')->select();
            $action_info=array_column($action_info,'id');
            if (in_array($param['action_id'],$action_info)) {
                $res = $this->doCheckIn();
                if($res){
                    $param['log_score'] .= '签到成功!'.$str;
                    return $res;
                }
            }
        }
        return false;
    }

}