<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageListBuilder;
use app\backstage\builder\BackstageConfigBuilder;
use app\backstage\builder\BackstageSortBuilder;
use app\common\model\ModuleModel;
use app\common\model\SeoRuleModel;

class SeoController extends BackstageController
{
    public function index()
    {
        $r = config("LIST_ROWS");
        //读取规则列表
        $aApp=input('app','','text');
        $map = ['status' => ['EGT', 0]];
        if($aApp!=''){
            $map['app']=$aApp;
        }
        $model =  new SeoRuleModel();
        $ruleList = $model->where($map)->order('sort asc')->paginate($r,false);
        $page = $ruleList->render();
        $data = $ruleList->toArray()['data'];
        $moduleModel = new ModuleModel();
        $module = $moduleModel->getAll();
        $app = [];
        foreach ($module as $m) {
            if ($m['is_setup'])
                $app[] = ['id'=>$m['name'],'value'=>$m['alias']];
        }

        //显示页面
        $builder = new BackstageListBuilder();
        $builder->setSearchPostUrl(url('index'));
        return $builder->title(lang('_SEO_RULE_CONFIGURATION_'))
            ->setStatusUrl(url('setrulestatus'))->buttonEnable()->buttonDisable()->buttonDelete()
            ->buttonNew(url('editrule'))->buttonSort(url('sortRule'))
            ->keyId()->keyTitle()->keyText('app', lang('_MODULE_PLAIN_'))->keyText('controller', lang('_CONTROLLER_'))->keyText('action', lang('_METHOD_'))
            ->keyText('seo_title', lang('_SEO_TITLE_'))->keyText('seo_keywords', lang('_SEO_KEYWORD_'))->keyText('seo_description', lang('_SEO_DESCRIPTION_'))
            ->searchSelect(lang('_MODULE_BELONGED_').lang('_COLON_'), 'app', 'select', '', '', array_merge([['id' => '', 'value' => lang('_ALL_')]], $app))
            ->keyStatus()->keyDoActionEdit('editrule?id=###')
            ->data($data)
            ->pagination($page)
            ->show();
    }

    public function ruletrash()
    {
        $r = config("LIST_ROWS");
        //读取规则列表
        $map = ['status' => -1];
        $model = new SeoRuleModel();
        $ruleList = $model->where($map)->order('sort asc')->paginate($r,false);
        $page = $ruleList->render();
        $data = $ruleList->toArray()['data'];

        //显示页面
        $builder = new BackstageListBuilder();
        return $builder->title(lang('_SEO_RULE_RECYCLING_STATION_'))
            ->setStatusUrl(url('setrulestatus'))->setDeleteTrueUrl(url('doclear'))->buttonRestore()->buttonDeleteTrue()
            ->keyId()->keyTitle()->keyText('app', lang('_MODULE_PLAIN_'))->keyText('controller', lang('_CONTROLLER_'))->keyText('action', lang('_METHOD_'))
            ->keyText('seo_title', lang('_SEO_TITLE_'))->keyText('seo_keywords', lang('_SEO_KEYWORD_'))->keyText('seo_description', lang('_SEO_DESCRIPTION_'))
            ->data($data)
            ->pagination($page)
            ->show();
    }

    public function setrulestatus($ids, $status)
    {
        $builder = new BackstageListBuilder();
        $builder->doSetStatus('SeoRule', $ids, $status);
    }

    public function doclear($ids)
    {
        $builder = new BackstageListBuilder();
        $builder->doDeleteTrue('SeoRule', $ids);
    }

    public function sortrule()
    {
        $seoRuleModel = new SeoRuleModel();
        //读取规则列表
        $list = $seoRuleModel->where(['status' => ['EGT', 0]])->order('sort asc')->select()->toArray();

        //显示页面
        $builder = new BackstageSortBuilder();
        return $builder->title(lang('_SORT_SEO_RULE_'))
            ->data($list)
            ->buttonSubmit(url('dosortrule'))
            ->buttonBack()
            ->show();
    }

    public function dosortrule($ids)
    {
        $builder = new BackstageSortBuilder();
        $builder->doSort('SeoRule', $ids);
    }

    public function editrule($id = null)
    {
        //判断是否为编辑模式
        $isEdit = $id ? true : false;
        $seoRuleModel = new SeoRuleModel();
        //读取规则内容
        if ($isEdit) {
            $rule = $seoRuleModel->where(['id' => $id])->find();
        } else {
            $rule = ['status' => 1];
        }

        $rule['action2'] = $rule['action'];

        //显示页面
        $builder = new BackstageConfigBuilder();
        $moduleModel = new ModuleModel();
        $modules = $moduleModel->getAll();

        $app = ['' => lang('_MODULE_ALL_')];
        foreach ($modules as $m) {
            if ($m['is_setup']) {
                $app[$m['name']] = $m['alias'];
            }
        }

        $rule['summary']=nl2br($rule['summary']);
        return $builder->title($isEdit ? lang('_EDIT_RULES_') : lang('_ADD_RULE_'))
            ->keyId()->keyText('title', lang('_NAME_'), lang('_RULE_NAME,_CONVENIENT_MEMORY_'))->keySelect('app', lang('_MODULE_NAME_'), lang('_NOT_FILLED_IN_ALL_MODULES_'), $app)->keyText('controller', lang('_CONTROLLER_'), lang('_DO_NOT_FILL_IN_ALL_CONTROLLERS_'))
            ->keyText('action2', lang('_METHOD_'), lang('_DO_NOT_FILL_OUT_ALL_THE_METHODS_'))->keyText('seo_title', lang('_SEO_TITLE_'), lang('_DO_NOT_FILL_IN_THE_USE_OF_THE_NEXT_RULE,_SUPPORT_VARIABLE_'))
            ->keyText('seo_keywords', lang('_SEO_KEYWORD_'), lang('_DO_NOT_FILL_IN_THE_USE_OF_THE_NEXT_RULE,_SUPPORT_VARIABLE_'))->keyTextArea('seo_description', lang('_SEO_DESCRIPTION_'), lang('_DO_NOT_FILL_IN_THE_USE_OF_THE_NEXT_RULE,_SUPPORT_VARIABLE_'))
            ->keyReadOnly('summary',lang('_VARIABLE_DESCRIPTION_'),lang('_VARIABLE_DESCRIPTION_VICE_'))
            ->keyStatus()
            ->data($rule)
            ->buttonSubmit(url('doeditrule'))->buttonBack()
            ->show();
    }

    public function doeditrule($id = null, $title, $app, $controller, $action2, $seo_title, $seo_keywords, $seo_description, $status)
    {
        //判断是否为编辑模式
        $isEdit = $id ? true : false;


        //写入数据库
        $data = ['title' => $title, 'app' => $app, 'controller' => $controller, 'action' => $action2, 'seo_title' => $seo_title, 'seo_keywords' => $seo_keywords, 'seo_description' => $seo_description, 'status' => $status];
        $model = new SeoRuleModel();
        if ($isEdit) {
            $result = $model->allowField(true)->isUpdate(true)->save($data,['id' => $id]);
        } else {
            $result = $model->allowField(true)->isUpdate(false)->save($data);
        }

        clean_all_cache();
        //如果失败的话，显示失败消息
        if (!$result) {
            $this->error($isEdit ? lang('_EDIT_FAILED_') : lang('_CREATE_FAILURE_'));
        }

        //显示成功信息，并返回规则列表
        $this->success($isEdit ? lang('_EDIT_SUCCESS_') : lang('_CREATE_SUCCESS_'), url('index'));
    }
}