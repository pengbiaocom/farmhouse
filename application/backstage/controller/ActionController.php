<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageConfigBuilder;
use app\common\model\ActionModel;

/**
 * 行为控制器
 */
class ActionController extends BackstageController {

    /**
     * 行为日志列表
     */
    public function actionlog(){
        //获取列表数据
        $aUid=input('uid',0,'intval');
        if($aUid) $map['user_id']=$aUid;

        //按时间和行为筛选
        $sTime=input('sTime',0,'text');
        $eTime=input('post.eTime',0,'text');
        $aSelect=input('post.select',0,'intval');
        if($sTime && $eTime) {
            $map['create_time']=['between',[($sTime),($eTime)]];
        }
        if($aSelect) {
            $map['action_id'] = $aSelect;
        }

        $map['status']    =   ['gt', -1];
        $list   =   $this->lists('ActionLog', $map);

        int_to_string($list['list']);
        foreach ($list['list'] as $key=>$value){
            $list['list'][$key]['ip']=long2ip($value['action_ip']);
        }
        $actionModel = new ActionModel();
        $actionList = $actionModel->select();
        $this->assign('action_list', $actionList);
        $this->assign('sTime',$sTime);
        $this->assign('eTime',$eTime);
        $this->assign('select',$aSelect);

        $this->assign('_list', $list['list']);
        $this->assign('meta_title',lang('_BEHAVIOR_LOG_'));
        return $this->fetch();
    }

    /**
     * 查看行为日志
     */
    public function edit(){
        $id = input('id',0,'intval');
        empty($id) && $this->error(lang('_PARAMETER_ERROR_'));

        $info = db('ActionLog')->field(true)->find($id);
        if($info){
            $info['action_title'] = get_action($info['action_id'], "title");
            $info['nickname'] = get_nickname($info['user_id']);
            $info['action_ip'] = long2ip($info['action_ip']);
        }
        $builder=new BackstageConfigBuilder();

        $builder->title(lang('_CHECK_THE_BEHAVIOR_LOG_'))
            ->keyReadOnly('id','ID')
            ->keyReadOnly('action_title',lang('_BEHAVIOR_NAME_').lang('_COLON_'))
            ->keyReadOnly('nickname',lang('_EXECUTIVE_').lang('_COLON_'))
            ->keyReadOnly('action_ip',lang('_EXECUTE_IP_').lang('_COLON_'))
            ->keyReadOnly('create_time',lang('_EXECUTION_TIME_').lang('_COLON_'))
            ->keyReadOnly('remark',lang('_REMARKS_').lang('_COLON_'))
            ->buttonBack()
            ->data($info);
        return $builder->show();
    }

    /**
     * 删除日志
     */
    public function remove(){
        $ids = $this->request->param('ids/a');
        empty($ids) && $this->error(lang('_PARAMETER_ERROR_'));
        $map = [];
        if(is_array($ids)){
            $map['id'] = ['in', $ids];
        }elseif (is_numeric($ids)){
            $map['id'] = $ids;
        }
        $res = db('ActionLog')->where($map)->delete();
        if($res !== false){
            $this->success(lang('_DELETE_SUCCESS_'));
        }else {
            $this->error(lang('_DELETE_FAILED_'));
        }
    }

    /**
     * 清空日志
     */
    public function clear(){
        $res = db('ActionLog')->where('1=1')->delete();
        if($res !== false){
            $this->success(lang('_LOG_EMPTY_SUCCESSFULLY_'));
        }else {
            $this->error(lang('_LOG_EMPTY_'));
        }
    }

    /**
     * 导出csv
     */
    public function csv()
    {
        $path = realpath("./data/log") . DIRECTORY_SEPARATOR;
        is_writeable($path) || $this->error('备份目录不存在或不可写，请检查后重试！');

        $aIds = input('ids', []);
        $map = [];
        if(count($aIds)) {
            $map['id'] = ['in', $aIds];
        } else {
            $map['status'] = 1;
        }
        $list = db('ActionLog')->where($map)->order('create_time asc')->select();

        $data = lang('_DATA_MORE_')."\n";
        foreach ($list as $val) {
            $val['create_time'] = time_format($val['create_time']);
            $data.=$val['id'].",".get_action($val['action_id'], 'title').",".get_nickname($val['user_id']).",".long2ip($val['action_ip']).",".$val['remark'].",".$val['create_time']."\n";
        }
        $data = iconv('utf-8', 'gb2312', $data);
        $filename = 'ActionLog'.date('YmdHis').'.csv'; //设置文件名

        $myfile = fopen($path . $filename, "w") or die("Unable to open file!");
        if(fwrite($myfile, $data)){
            db('ActionLog')->where($map)->delete();
        }
        fclose($myfile);
        $this->success('行为日志已成功导出到data/log下！');
    }
}
