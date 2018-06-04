<?php
namespace app\common\model;

class ActionLimitModel extends BaseModel
{
    protected $resultSetType = 'collection';
    var $item = [];
    var $state = true;
    var $url;
    var $info = '';
    var $punish = [
        ['warning','警告并禁止'],
        ['logout_account', '强制退出登陆'],
        ['ban_account', '封停账户'],
        ['ban_ip', '封IP'],
    ];
    function __construct()
    {
        parent::__construct();
        $this->url = '';
        $this->info = '';
        $this->state = true;
    }

    public function get_punish(){
        return [
            ['warning','警告并禁止'],
            ['logout_account', '强制退出登陆'],
            ['ban_account', '封停账户'],
            ['ban_ip', '封IP']
        ];
    }

    public function addActionLimit($data)
    {
        $res = $this->allowField(true)->save($data);
        return $res->id;
    }

    public function getActionLimit($where){
        $limit = db("action_limit")->where($where)->find();
        return $limit;
    }

    public function getList($where){
        $list = db("action_limit")->where($where)->select();
        return $list;
    }

    public function editActionLimit($data,$where)
    {
        $res = $this->allowField(true)->save($data,$where);
        return $res;
    }

    function addCheckItem($action = null, $model = null, $record_id = null, $user_id = null, $ip = false)
    {
        $this->item[] = ['action' => $action, 'model' => $model, 'record_id' => $record_id, 'user_id' => $user_id, 'action_ip' => $ip];
        return $this;
    }

    function check()
    {
        $items = $this->item;
        foreach ($items as &$item) {
            $this->checkOne($item);
        }
        unset($item);
    }

    function checkOne($item)
    {
        $item['action_ip'] = $item['action_ip'] ? get_client_ip(1) : null;
        foreach ($item as $k => $v) {
            if (empty($v)) {
                unset($item[$k]);
            }
        }
        unset($k, $v);
        $time = time();
        $map['action_list'] = [['like','%['.$item['action'].']%'],'','or'];
        $map['status'] = 1;
        $limitList = db("action_limit")->where($map)->select();
        $action =  db("action")->where(['name' => $item['action']])->cache(true,60)->find();
        !empty($item['action']) && $item['action_id'] = $action['id'];

        foreach ($limitList as &$val) {
            $ago = get_time_ago($val['time_unit'], $val['time_number'], $time);
            $item['create_time'] = ['egt', $ago];
            unset($item['action']);
            $log = db('action_log')->where($item)->order('create_time desc')->select();
            if (count($log) >= $val['frequency']) {
                $punishes = explode(',', $val['punish']);
                foreach ($punishes as $punish) {
                    //执行惩罚
                    if (method_exists($this, $punish)) {
                        $this->$punish($item,$val);
                    }
                }
                unset($punish);
            }
        }
        unset($val);
    }

    /**
     * ban_account  封停帐号
     * @param $item
     */
    function ban_account($item)
    {
        set_user_status($item['user_id'], 0);
    }

    function ban_ip($item,$val)
    {
        //TODO 进行封停IP的操作
    }

    function warning($item,$val){
        $this->state = false;
        $this->info = lang('_OPERATION_IS_FREQUENT_PLEASE_').$val['time_number'].get_time_unit($val['time_unit']).lang('_AND_THEN_');
        $this->url = url('home/index/index');
    }
}