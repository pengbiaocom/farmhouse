<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageConfigBuilder;
use app\common\model\ConfigModel;

/**
 * 后台配置控制器
 */
class ConfigController extends BackstageController
{

    /**
     * 配置管理
     */
    public function index()
    {
        /* 查询条件初始化 */
        $map = ['status' => 1, 'title' => ['neq', '']];
        $group =  input('group',0);
        $name = input('name');
        if (!empty($group)) {
            $map['group'] = input('group', 0);
        }
        if (isset($name)) {
            $map['name'] = ['like', '%' . (string)input('name') . '%'];
        }
        $list = $this->lists('Config', $map, 'sort,id');
        // 记录当前列表页的cookie
        Cookie('__forward__', $_SERVER['REQUEST_URI']);
        $this->assign('group', config('CONFIG_GROUP_LIST'));
        $this->assign('group_id',$group);
        $this->assign('list', $list['list']);
        $this->assign('_page',$list['page']);
        $this->assign('meta_title',lang('_CONFIG_MANAGER_'));
        return $this->fetch();
    }

    /**
     * 新增配置
     */
    public function add()
    {
        if (Request()->isPost()) {
            $Config = new ConfigModel();
            $data   = $this->request->param();
            if ($data) {
                if ($Config->allowField(true)->save()) {
                    cache('DB_CONFIG_DATA', null);
                    $this->success(lang('_SUCCESS_ADD_'), url('index'));
                } else {
                    $this->error(lang('_FAIL_ADD_'));
                }
            } else {
                $this->error($Config->getError());
            }
        } else {
            $this->assign('meta_title',lang('_CONFIG_ADD_'));
            $this->assign('info', null);
            return $this->fetch('edit');
        }
    }

    /**
     * 编辑配置
     */
    public function edit()
    {
        $id = input('id',0,'intval');
        $Config = new ConfigModel();
        if (Request()->isPost()) {
            $data   = $this->request->param();
            if ($data) {
                if ($Config->allowField(true)->isUpdate(true)->data($data,true)->save()) {
                    cache('DB_CONFIG_DATA', null);
                    //记录行为
                    action_log('update_config', 'config', $data['id'], UID);
                    $this->success(lang('_SUCCESS_UPDATE_'), Cookie('__forward__'));
                } else {
                    $this->error(lang('_FAIL_UPDATE_'));
                }
            } else {
                $this->error($Config->getError());
            }
        } else {
            /* 获取数据 */
            $info = $Config->field(true)->find($id);

            if (false === $info) {
                $this->error(lang('_ERROR_CONFIG_INFO_GET_'));
            }
            $this->assign('info', $info);
            $this->assign('meta_title',lang('_CONFIG_EDIT_'));
            return $this->fetch();
        }
    }

    /**
     * 批量保存配置
     */
    public function save()
    {
        $config = $this->request->param('config/a');
        if ($config && is_array($config)) {
            $configModel = new ConfigModel();
            foreach ($config as $name => $value) {
                $map = ['name' => $name];
                $configModel->isUpdate(true)->save(['value'=>$value],$map);
            }
        }
        cache('DB_CONFIG_DATA', null);
        $this->success(lang('_SUCCESS_SAVE_').lang('_EXCLAMATION_'));
    }

    /**
     * 删除配置
     */
    public function del()
    {
        $id = array_unique(input('id/a', 0));

        if (empty($id)) {
            $this->error(lang('_DATA_OPERATE_SELECT_'));
        }
        $Config = new ConfigModel();
        $map = ['id' => ['in', $id]];
        if ($Config->where($map)->delete()) {
            cache('DB_CONFIG_DATA', null);
            //记录行为
            action_log('update_config', 'config', $id, UID);
            $this->success(lang('_SUCCESS_DELETE_').lang('_EXCLAMATION_'));
        } else {
            $this->error(lang('_FAIL_DELETE_').lang('_EXCLAMATION_'));
        }
    }

    // 获取某个标签的配置参数
    public function group()
    {
        $Config = new ConfigModel();
        $id = input('id', 1);
        $type = config('CONFIG_GROUP_LIST');
        $list = $Config->where(['status' => 1, 'group' => $id])->field('id,name,title,extra,value,remark,type')->order('sort')->select();
        if ($list) {
            $this->assign('list', $list);
        }
        $this->assign('id', $id);
        $this->assign('meta_title',$type[$id] . lang('_SETTINGS_'));
        return $this->fetch();
    }

    /**
     * 配置排序
     */
    public function sort()
    {
        $Config = new ConfigModel();
        if (Request()->isGet()) {
            $ids   = $this->request->param('ids/a');

            //获取排序的数据
            $map = ['status' => ['gt', -1], 'title' => ['neq', '']];
            if (!empty($ids)) {
                $ids = is_array($ids) ? implode(',', $ids) : $ids;
                $map['id'] = ['in', $ids];
            } elseif (input('group')) {
                $map['group'] = input('group');
            }
            $list = $Config->where($map)->field('id,title')->order('sort asc,id asc')->select();

            $this->assign('list', $list);
            $this->assign('meta_title',lang('_CONFIG_SORT_'));
            return $this->fetch();
        } elseif (Request()->isPost()) {
            $ids = $this->request->param("ids/a");
            $ids = explode(',', $ids);
            foreach ($ids as $key => $value) {
                $res = $Config->where(['id' => $value])->setField('sort', $key + 1);
            }
            if ($res !== false) {
                $this->success(lang('_SUCCESS_SORT_').lang('_EXCLAMATION_'), Cookie('__forward__'));
            } else {
                $this->error(lang('_FAIL_SORT_').lang('_EXCLAMATION_'));
            }
        } else {
            $this->error(lang('_BAD_REQUEST_').lang('_EXCLAMATION_'));
        }
    }

    /**
     * 网站信息设置
     */
    public function website()
    {
        $builder = new BackstageConfigBuilder();
        $data = $builder->handleConfig();
        $builder->title(lang('_SITE_INFO_'))->suggest(lang('_SITE_INFO_VICE_'));
        $builder->keyText('WEB_SITE_NAME', lang('_SITE_NAME_'), lang('_SITE_NAME_VICE_'));
        $builder->keyText('ICP', lang('_LICENSE_NO_'), lang('_LICENSE_NO_VICE_'));

        $builder->keySingleImage('LOGO', lang('_SITE_LOGO_'), lang('_SITE_LOGO_VICE_'));
        $builder->keySingleImage('QRCODE_BOTTOM', '底部二维码', '设置在网站底部显示的二维码，建议尺寸120*120');
        $builder->keySingleImage('QRCODE', lang('_QR_WEIXIN_'), lang('_QR_WEIXIN_VICE_'));


        $builder->keySingleImage('JUMP_BACKGROUND', lang('_IMG_BG_REDIRECTED_'), lang('_IMG_BG_REDIRECTED_'));
        $builder->keyText('SUCCESS_WAIT_TIME', lang('_TIME_SUCCESS_WAIT_'), lang('_TIME_SUCCESS_WAIT_VICE_'));
        $builder->keyText('ERROR_WAIT_TIME', lang('_TIME_FAIL_WAIT_'), lang('_TIME_FAIL_WAIT_VICE_'));

        $builder->keyEditor('COPY_RIGHT', lang('_INFO_COPYRIGHT_'), lang('_INFO_COPYRIGHT_VICE_'));

        $addons = \think\Hook::get('uploadDriver');
        $opt = ['local' => lang('_LOCAL_')];
        foreach ($addons as $name) {
            if (class_exists($name)) {
                $class = new $name();
                $config = $class->getConfig();
                if ($config['switch']) {
                    $opt[$class->info['name']] = $class->info['title'];
                }

            }
        }

        $builder->keySelect('PICTURE_UPLOAD_DRIVER', lang('_PICTURE_UPLOAD_DRIVER_'), lang('_PICTURE_UPLOAD_DRIVER_'), $opt);
        $builder->keySelect('DOWNLOAD_UPLOAD_DRIVER', lang('_ATTACHMENT_UPLOAD_DRIVER_'), lang('_ATTACHMENT_UPLOAD_DRIVER_'), $opt);

        $builder->group(lang('_BASIC_INFORMATION_'), ['WEB_SITE_NAME', 'ICP', 'LOGO', 'QRCODE_BOTTOM', 'QRCODE', 'LANG']);

        $builder->group(lang('_THE_FOOTER_INFORMATION_'),['COPY_RIGHT']);

        $builder->group(lang('_JUMP_PAGE_'), ['JUMP_BACKGROUND', 'SUCCESS_WAIT_TIME', 'ERROR_WAIT_TIME']);
        $builder->group(lang('_UPLOAD_CONFIGURATION_'), ['PICTURE_UPLOAD_DRIVER', 'DOWNLOAD_UPLOAD_DRIVER']);

        $builder->data($data);
        $builder->keyDefault('WEBSOCKET_PORT', 8000);
        $builder->keyDefault('WEBSOCKET_ADDRESS', gethostbyname($_SERVER['SERVER_NAME']));


        $builder->keyDefault('SUCCESS_WAIT_TIME', 2);
        $builder->keyDefault('ERROR_WAIT_TIME', 5);
        $builder->keyDefault('LANG', 'zh-cn');
        $builder->keyDefault('GET_INFORMATION',1);
        $builder->keyDefault('GET_INFORMATION_INTERNAL',10);
        $builder->keyDefault('OPEN_IM',1);

        $builder->buttonSubmit();
        return $builder->show();
    }
}
