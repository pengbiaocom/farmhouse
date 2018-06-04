<?php
namespace app\backstage\Controller;

use app\backstage\builder\BackstageConfigBuilder;
use app\common\model\ModuleModel;


class HomeController extends BackstageController
{

    public function config()
    {

        $builder = new BackstageConfigBuilder();
        $data = $builder->handleConfig();

        $data['OPEN_LOGIN_PANEL'] = $data['OPEN_LOGIN_PANEL'] ? $data['OPEN_LOGIN_PANEL'] : 1;
        $data['HOME_INDEX_TYPE'] = $data['HOME_INDEX_TYPE'] ? $data['HOME_INDEX_TYPE'] : 'static_home';

        $builder->title(lang('_HOME_SETTING_'));
        $builder->keyRadio('HOME_INDEX_TYPE','系统首页类型','',['static_home'=>'静态首页','index'=>'聚合首页','login'=>'登录页']);
        $moduleModel = new ModuleModel();
        $modules = $moduleModel->getAll();
        foreach ($modules as $m) {
            if ($m['is_setup'] == 1 && $m['entry'] != '') {
                if (file_exists(APP_PATH . $m['name'] . '/widget/HomeBlockWidget.php')) {
                    $module[] = ['data-id' => $m['name'], 'title' => $m['alias']];
                }
            }
        }
        $module[] = ['data-id' => 'slider', 'title' => lang('_CAROUSEL_')];

        foreach ($modules as $m) {
            if ($m['is_setup'] == 1 && $m['entry'] != '') {
                if (file_exists(APP_PATH . $m['name'] . '/widget/SearchWidget.class.php')) {
                    $mod[] = ['data-id' => $m['name'], 'title' => $m['alias']];
                }
            }
        }

        $builder->group('首页类型', 'HOME_INDEX_TYPE');

        $builder->buttonSubmit();

        $builder->data($data);

        return $builder->show();
    }


}
