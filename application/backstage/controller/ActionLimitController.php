<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageConfigBuilder;
use app\backstage\builder\BackstageListBuilder;
use app\common\model\ActionLimitModel;
use app\common\model\ModuleModel;
use app\common\model\ActionModel;

/**
 * Class ActionLimitController  后台行为限制控制器
 * @package Backstage\Controller
 */
class ActionLimitController extends BackstageController
{

    public function limitlist()
    {
        $action_name = input('action','','op_t') ;
        !empty($action_name) && $map['action_list'] = [['like', '%[' . $action_name . ']%'],'','or'];
        //读取规则列表
        $map['status'] = ['EGT', 0];
        $model = db('action_limit');
        $List = $model->where($map)->order('id asc')->select();
        $timeUnit = $this->getTimeUnit();
        foreach($List as &$val){
            $val['time'] =$val['time_number']. $timeUnit[$val['time_unit']];
            $val['action_list'] = get_action_name($val['action_list']);
            empty( $val['action_list']) &&  $val['action_list'] = lang('_ALL_ACTS_');

            $val['punish'] = get_punish_name($val['punish']);


        }
        unset($val);
        //显示页面
        $builder = new BackstageListBuilder();
        return $builder->title(lang('_ACTION_LIST_'))
            ->buttonNew(url('editLimit'))
            ->setStatusUrl(url('setLimitStatus'))->buttonEnable()->buttonDisable()->buttonDelete()
            ->keyId()
            ->keyTitle()
            ->keyText('name', lang('_NAME_'))
            ->keyText('frequency', lang('_FREQUENCY_'))
            ->keyText('time', lang('_TIME_UNIT_'))
            ->keyText('punish', lang('_PUNISHMENT_'))
            ->keyText('action_list', lang('_ACT_'))
            ->keyStatus()
            ->keyDoActionEdit('editLimit?id=###')
            ->data($List)->show();
    }

    public function editLimit()
    {
        $aId = input('id', 0, 'intval');
        $model = new ActionLimitModel();
        if (Request()->isPost()) {
            $data['title'] = input('post.title', '', 'op_t');

            $data['name'] = input('post.name', '', 'op_t');
            $data['frequency'] = input('post.frequency', 1, 'intval');
            $data['time_number'] = input('post.time_number', 1, 'intval');
            $data['time_unit'] = input('post.time_unit', '', 'op_t');
            $data['punish'] = input('post.punish', '', 'op_t');
            $data['action_list'] = input('post.action_list', '', 'op_t');
            $data['status'] = input('post.status', 1, 'intval');
            $data['module'] = input('post.module', '', 'op_t');

            $data['action_list'] = explode(',',$data['action_list']);
            foreach($data['action_list'] as &$v){
                $v = '['.$v.']';
            }
            unset($v);
            $data['action_list'] = implode(',', $data['action_list']);
            if ($aId != 0) {
                $data['id'] = $aId;
                $res = $model->editActionLimit($data,'id='.$aId);
            } else {
                $res = $model->addActionLimit($data);
            }
            if($res){
                $this->success(($aId == 0 ? lang('_ADD_') : lang('_EDIT_')) . lang('_SUCCESS_'), $aId == 0 ? url('', ['id' => $res]) : url('',['id'=>$aId]));
            }else{
                $this->error($aId == 0 ? lang('_THE_OPERATION_FAILED_') : lang('_THE_OPERATION_FAILED_VICE_'));
            }
        } else {
            $builder = new BackstageConfigBuilder();
            $moduleModel = new ModuleModel();
            $modules = $moduleModel->getAll();
            $module['all'] = lang('_TOTAL_STATION_');
            if(!empty($modules)){
                foreach($modules as $k=>$v){
                    $module[$v['name']] = $v['alias'];
                }
            }

            if ($aId != 0) {
                $limit = $model->getActionLimit(['id'=>$aId]);
                if(!empty($limit)){
                    $limit['punish'] = explode(',', $limit['punish']);
                    $limit['action_list'] = str_replace('[','',$limit['action_list']);
                    $limit['action_list'] = str_replace(']','',$limit['action_list']);
                    $limit['action_list'] = explode(',', $limit['action_list']);
                }else{
                    $limit = [
                        'id'=>0,
                        'title'=>'',
                        'name'=>'',
                        'module'=>'',
                        'frequency'=>'',
                        'time_unit'=>'',
                        'time_number'=>1,
                        'punish'=>'',
                        'action_list'=>'',
                        'status'=>1,
                    ];
                }
            } else {
                $limit = [
                    'title'=>'',
                    'name'=>'',
                    'module'=>'',
                    'frequency'=>'',
                    'time_unit'=>'',
                    'time_number'=>1,
                    'punish'=>'',
                    'action_list'=>'',
                    'status'=>1,
                ];
            }
            $opt_punish = $this->getPunish();
            $actionModel = new ActionModel();
            $opt = $actionModel->getActionOpt();
            $builder->title(($aId == 0 ? lang('_NEW_') : lang('_EDIT_')) . lang('_ACT_RESTRICTION_'));
            if($aId != 0){
                $builder->keyId();
            }
             return $builder->keyTitle()
                ->keyText('name', lang('_NAME_'))
                ->keySelect('module', lang('_MODULE_'),'',$module)
                ->keyText('frequency', lang('_FREQUENCY_'))
                ->keyMultiInput('time_number|time_unit',lang('_TIME_UNIT_'),lang('_TIME_UNIT_'),[['type'=>'text','style'=>'width:295px;margin-right:5px'],['type'=>'select','opt'=>$this->getTimeUnit(),'style'=>'width:100px']])

                ->keyChosen('punish', lang('_PUNISHMENT_'), lang('_MULTI_SELECT_'), $opt_punish)
                ->keyChosen('action_list', lang('_ACT_'), lang('_MULTI_SELECT_DEFAULT_'), $opt)
                ->keyStatus()
                ->data($limit)
                ->buttonSubmit(url('editLimit'))->buttonBack()->show();
        }
    }


    public function setLimitStatus($ids, $status)
    {
        $builder = new BackstageListBuilder();
        $builder->doSetStatus('action_limit', $ids, $status);
    }

    private function getTimeUnit()
    {
        return get_time_unit();
    }


    private function getPunish()
    {
        $obj = new ActionLimitModel();
        return $obj->get_punish();

    }

}
