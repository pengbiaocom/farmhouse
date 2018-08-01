<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageConfigBuilder;
use app\backstage\builder\BackstageListBuilder;
use app\common\model\AdvModel;
use app\common\model\AdvPosModel;
use app\common\model\ModuleModel;


class AdvController extends BackstageController
{

    public function pos()
    {
        $aModule = input('module', '', 'text');
        $aStatus = input('status', 1, 'intval');
        $_GET['status'] = $aStatus;
        if ($aModule == '') {
            $moduleModel = new ModuleModel();
            $module = $moduleModel->getAll(1);

            $advPosModel = new AdvPosModel();
            foreach ($module as $key => &$v) {
                $v['count'] = $advPosModel->where(['status' => 1, 'path' => ['like', ucfirst($v['name']) . '/%']])->count();
                $v['count'] = $v['count'] == 0 ? $v['count'] : '<strong class="text-danger" style="font-size:18px">' . $v['count'] . '</strong>';
                $v['alias_html'] = '<a href="' . url('pos?module=' . $v['name']) . '">' . $v['alias'] . '</a>';
                $v['do'] = '<a href="' . url('pos?module=' . $v['name']) . '">' . '管理内部广告位' . '</a>';
            }

            $builder = new BackstageListBuilder();
            $builder->title('广告位管理 - 按模块选择');
            $builder->keyhtml('alias_html', '模块名')->keyHtml('do', '操作')->keyHtml('count', '模块内广告位数量');
            $builder->data($module);
            return $builder->show();
        }else{
            $adminList = new BackstageListBuilder();

            $map['path'] = ['like', ucfirst($aModule . '/%')];
            $map['status'] = $aStatus;

            $advPosModel = new AdvPosModel();
            $advModel = db('Adv');
            $advPoses = $advPosModel->where($map)->select();
            foreach ($advPoses as &$v) {
                switch ($v['type']) {
                    case 1:
                        $v['type_html'] = '<span class="text-danger">单图</span>';
                        break;
                    case 2:
                        $v['type_html'] = '<span class="text-warning">多图轮播</span>';
                        break;
                    case 3:
                        $v['type_html'] = '<span class="text-success">文字链接</span>';
                        break;
                    case 4:
                        $v['type_html'] = '<span class="text-error">代码块</span>';
                        break;
                }

                $count = $advModel->where(['pos_id' => $v['id'], 'status' => 1])->count();
                $v['do'] = '<a href="' . url('editPos?copy=' . $v['id']) . '">复制</a>&nbsp;'
                    . '<a href="' . url('editPos?id=' . $v['id']) . '">设置</a>&nbsp;'
                    . '<a href="' . url('adv?pos_id=' . $v['id']) . '" >管理广告(' . $count . ')</a>&nbsp;'
                    . '<a href="' . url('editAdv?pos_id=' . $v['id']) . '">添加广告</a>&nbsp;';
            }
            unset($v);

            $adminList->title('广告位管理');
            $adminList->buttonNew(url('editPos'), '添加广告位');
            $adminList->buttonDelete(url('setPosStatus'));
            $adminList->buttonDisable(url('setPosStatus'));
            $adminList->buttonEnable(url('setPosStatus'));
            $adminList->keyId()->keyTitle()
                ->keyHtml('do', '操作', '320px')
                ->keyText('name', '广告位英文名')->keyText('path', '路径')->keyHtml('type_html', '广告类型')->keyStatus()->keyText('width', '宽度')->keyText('height', '高度')->keyText('margin', '边缘留白')->keyText('padding', '内部留白');

            $status_array =[['id' => 1, 'value' => '正常'], ['id' => 0, 'value' => '禁用'], ['id' => -1, 'value' => '已删除']];
            $adminList->searchSelect('状态：', 'status', 'select', '广告位状态', '', $status_array);
            $adminList->data($advPoses);
            return $adminList->show();
        }
    }

    private static function getValue($array, $index, $col = 'title')
    {
        return $array[$index][$col];
    }

    public function setPosStatus()
    {
        $aIds = input('ids', '', 'intval');
        $aStatus = input('get.status', '1', 'intval');
        $advPosModel = new AdvPosModel();
        $map['id'] = ['in', implode(',', $aIds)];
        $result = $advPosModel->where($map)->setField('status', $aStatus);
        db('Adv')->where(['pos_id' =>['in', implode(',', $aIds)]])->setField('status', $aStatus);
        if ($result === false) {
            $this->error('设置状态失败。');
        } else {
            $this->success('设置状态成功。影响了' . $result . '条数据。');
        }
    }
    public function setAdvStatus()
    {
        $aIds = input('ids', '', 'intval');
        $aStatus = input('get.status', '1', 'intval');
        $advModel = new AdvModel();
        $map['id'] = ['in', implode(',', $aIds)];
        $result = $advModel->where($map)->setField('status', $aStatus);

        if ($result === false) {
            $this->error('设置状态失败。');
        } else {
            $this->success('设置状态成功。影响了' . $result . '条数据。');
        }
    }
    public function editPos()
    {
        $aId = input('id', 0, 'intval');
        $aCopy = input('copy', 0, 'intval');
        $advPosModel = new AdvPosModel();
        if (Request()->isPost()) {
            //是提交
            $pos['name'] = input('name', '', 'text');
            $pos['title'] = input('title', '', 'text');
            $pos['path'] = input('path', '', 'text');
            $pos['type'] = input('type', 1, 'intval');
            $pos['status'] = input('status', 1, 'intval');
            $pos['width'] = input('width', '', 'text');
            $pos['height'] = input('height', '', 'text');
            $pos['margin'] = input('margin', '', 'text');
            $pos['padding'] = input('padding', '', 'text');
            switch ($pos['type']) {
                case 2:
                    //todo 多图
                    $pos['data'] = json_encode(['style' => input('style', 1, 'intval')]);
            }

            if ($aId == 0) {
                $result = $advPosModel->allowField(true)->save($pos);
            } else {
                $result = $advPosModel->allowField(true)->isUpdate(true)->save($pos,['id'=>$aId]);
            }

            if ($result === false) {
                $this->error('保存失败。');
            } else {
                cache('adv_pos_by_pos_' . $pos['path'] . $pos['name'], null);
                $this->success('保存成功。');
            }
        } else {
            $builder = new BackstageConfigBuilder();
            if ($aCopy != 0) {
                $pos = db("advPos")->find($aCopy);
                unset($pos['id']);
                $pos['name'] .= '   请重新设置!';
                $pos['title'] .= '   请重新设置!';
            } else {
                $pos = db("advPos")->find($aId);
            }

            if ($aId == 0) {
                if ($aCopy != 0) {
                    $builder->title('复制广告位——' . $pos['title']);
                } else {
                    $builder->title('新增广告位');
                }
            } else {
                $builder->title($pos['title'] . '【' . $pos['name'] . '】' . ' 设置——' . $advPosModel->switchType($pos['type']));
            }

            $builder->keyId()->keyTitle()->keyText('name', '广告位英文名', '标识，同一个页面上不要出现两个同名的')->keyText('path', '路径', '模块名/控制器名/方法名，例如：Home/Index/detail')->keyRadio('type', '广告类型', '', [1 => '单图广告', 2 => '多图轮播', 3 => '文字链接', 4 => '代码'])
                ->keyStatus()->keyText('width', '宽度', '支持各类长度单位，如px，em，%')->keyText('height', '高度', '支持各类长度单位，如px，em，%')
                ->keyText('margin', '边缘留白', '支持各类长度单位，如px，em，%；依次为：上  右  下  左，如 5px 2px 0 3px')->keyText('padding', '内部留白', '支持各类长度单位，如px，em，%；依次为：上  右  下  左，如 5px 2px 0 3px');
            $data = json_decode($pos['data'], true);
            if (!empty($data)) {
                $pos = array_merge($pos, $data);
            }

            if ($pos['type'] == 2) {
                $builder->keyRadio('style', '轮播_风格', '', [1 => 'TouchSlider 风格', 2 => 'KinmaxShow 风格'])->keyDefault('style', 1);
            }

            $builder->keyDefault('type', 1)->keyDefault('status', 1);
            $builder->data($pos);
            $builder->buttonSubmit()->buttonBack();
            return $builder->show();

        }

    }

    public function adv($r = 20)
    {
        $aPosId = input('pos_id', 0, 'intval');
        $advPosModel = new AdvPosModel();
        $pos = $advPosModel->where(['id'=>$aPosId])->find();
        if ($aPosId != 0) {
            $map['pos_id'] = $aPosId;
        }
        $map['status'] = 1;
        $advModel = new AdvModel();
        $count = $advModel->where($map)->count();

        $data['data'] = $advModel->where($map)->order('pos_id desc,sort asc')->paginate($r,false);
        $page = $data['data']->render();
        $data['data'] = $data['data']->toArray()['data'];
        $data['count'] = $count;

        foreach ($data['data'] as &$v) {
            $p = $advPosModel->where(['id'=>$v['pos_id']])->find();
            $v['pos'] = '<a class="text-danger" href="' . url('adv?pos_id=' . $p['id']) . '">' . $p['title'] . '</a>';
        }

        //todo 广告管理列表
        $builder = new BackstageListBuilder();
        if ($aPosId == 0) {
            $builder->title('广告管理');
        } else {
            $builder->title($pos['title'] . '【' . $pos['name'] . '】' . ' 设置——' . $advPosModel->switchType($pos['type']));
        }
        $builder->keyId()->keyLink('title', '广告说明', 'editAdv?id=###');
        $builder->keyText('pos', '所属广告位');
        $builder->buttonNew(url('editAdv?pos_id=' . $aPosId), '新增广告');
        $builder->buttonDelete(url('setAdvStatus'));
//        if ($aPosId != 0) {
//            $builder->button('广告排期查看', ['href' => url('schedule?pos_id=' . $aPosId)]);
//            $builder->button('设置广告位', ['href' => url('editPos?id=' . $aPosId)]);
//        }
        $builder->keyText('url', '链接地址')->keyText('sort', '排序')->keyCreateTime()->keyStatus();
        $builder->data($data['data']);
        $builder->pagination($page);
        return $builder->show();
    }

    public function editAdv()
    {

        $advModel = new AdvModel();

        $aId = input('id', 0, 'intval');

        if ($aId != 0) {
            $adv = db("adv")->find($aId);
            $aPosId = $adv['pos_id'];
        } else {
            $aPosId = input('pos_id', 0, 'intval');
        }

        $advPosModel = new AdvPosModel();
        $pos = db("advPos")->find($aPosId);
        if (Request()->isPost()) {
            cache('adv_list_' . $pos['name'] . $pos['path'], null);
            if ($pos['type'] == 2) {
                //todo 多图
                $datas = Request()->param();
                $aTitles = $datas["title"];
                $aUrl = $datas["url"];
                $aSort = $datas["sort"];
                $aTarget = $datas["target"];
                $added = 0;
                $pic = $datas["pic"];
                if(!empty($pic)){
                    $advModel->where(['pos_id' => $aPosId])->delete();
                    foreach ($pic as $key => $v) {
                        $data['pic'] = $v;

                        $data['target'] = $aTarget[$key];
                        $adv_temp['title'] = $aTitles[$key];
                        $adv_temp['pos_id'] = $aPosId;
                        $adv_temp['url'] = $aUrl[$key];
                        $adv_temp['sort'] = $aSort[$key];
                        $adv_temp['status'] = 1;
                        $adv_temp['create_time'] = time();
                        $adv_temp['target'] = $aTarget[$key];
                        $adv_temp['data'] = json_encode($data);
                        $result = db("adv")->insert($adv_temp);
                        if ($result !== false) {
                            $added++;
                        }
                        //todo添加
                    }
                }
                $this->success('成功改动' . $added . '个广告。',url('Adv/adv',['pos_id'=>$adv['pos_id']]));

            } else {
                $adv['title'] = input('title', '', 'text');
                $adv['pos_id'] = $aPosId;
                $adv['url'] = input('url', '', 'text');
                $adv['sort'] = input('sort', 1, 'intval');
                $adv['status'] = input('status', 1, 'intval');
                $adv['create_time'] = input('create_time', '', 'intval');
                $adv['target'] = input('target', '', 'text');
                switch ($pos['type']) {
                    case 1:
                        //todo 单图
                        $data['pic'] = input('pic', 0, 'intval');
                        $data['target'] = input('target', 0, 'text');
                        break;
                    case 3:
                        $data['text'] = input('text', '', 'text');
                        $data['text_color'] = input('text_color', '', 'text');
                        $data['text_font_size'] = input('text_font_size', '', 'text');
                        $data['target'] = input('target', 0, 'text');
                        //todo 文字
                        break;
                    case 4:
                        //todo 代码
                        $data['code'] = input('code', '', '');
                        break;
                }

                $adv['data'] = json_encode($data);


                if ($aId == 0) {
                    $result = $advModel->allowField(true)->save($adv);
                } else {
                    $result = $advModel->allowField(true)->isUpdate(true)->save($adv,['id'=>$aId]);
                }

                if ($result === false) {
                    $this->error('保存失败。',url('Adv/adv',['pos_id'=>$adv['pos_id']]));
                } else {
                    $this->success('保存成功。',url('Adv/adv',['pos_id'=>$adv['pos_id']]));
                }
            }

        } else {

            //快速添加广告位逻辑
            //todo 快速添加
            $builder = new BackstageConfigBuilder();

            $adv['pos'] = $pos['title'] . '——' . $pos['name'] . '——' . $pos['path'];
            $adv['pos_id'] = $aPosId;
            $builder->keyReadOnly('pos', '所属广告位');
            $builder->keyReadOnly('pos_id', '广告位ID');
            $builder->keyId()->keyTitle('title', '广告说明');

            $builder->title($pos['title'] . '设置——' . $advPosModel->switchType($pos['type']));

            $builder->keyText('sort', '排序')->keyCreateTime()->keyStatus();

            $builder->buttonSubmit()->buttonLink('返回广告列表',['href'=>url('adv?pos_id='.$aPosId),'class'=>'layui-btn btn-danger']);

            $data = json_decode($adv['data'], true);
            if (!empty($data)) {
                $adv = array_merge($adv, $data);
            }
            if ($aId) {
                $builder->data($adv);
            } else {
                $builder->data(['pos' => $adv['pos'], 'pos_id' => $aPosId]);
            }
            switch ($pos['type']) {
                case 1:
                    //todo 单图
                    $builder->keySingleImage('pic', '图片', '选图上传，建议尺寸' . $pos['width'] . '*' . $pos['height']);
                    $builder->keyText('url', '链接地址');
                    $builder->keySelect('target', '打开方式', null, ['_blank' => '新窗口:_blank', '_self' => '当前层:_self', '_parent' => '父框架:_parent', '_top' => '整个框架:_top']);
                    break;
                case 2:
                    //todo 多图

                    break;
                case 3:
                    $builder->keyText('text', '文字内容', '广告展示文字');
                    $builder->keyText('url', '链接地址');
                    $builder->keyColor('text_color', '文字颜色', '文字颜色')->keyDefault('data[text_color]', '#000000');
                    $builder->keyText('text_font_size', '文字大小，需带单位，例如：14px')->keyDefault('data[text_font_size]', '12px');
                    $builder->keySelect('target', '打开方式', null, ['_blank' => '新窗口:_blank', '_self' => '当前层:_self', '_parent' => '父框架:_parent', '_top' => '整个框架:_top']);

                    //todo 文字
                    break;
                case 4:
                    //todo 代码
                    $builder->keyTextArea('code', '代码内容', '不对此字段进行过滤，可填写js、html');
                    break;
            }
            $builder->keyDefault('status', 1)->keyDefault('sort', 1);

            $builder->keyDefault('title', $pos['title'] . '的广告 ' . date('m月d日', time()) . ' 添加')->keyDefault('end_time', time() + 60 * 60 * 24 * 7);
            if ($pos['type'] == 2) {
                $this->_meta_title = $pos['title'] . '设置——' . $advPosModel->switchType($pos['type']);
                $adv['create_time'] = isset($adv['create_time']) ? $adv['create_time'] : time();
                $adv['sort'] = isset($adv['sort']) ? $adv['sort'] : 1;
                $adv['status'] = isset($adv['status']) ? $adv['status'] : 1;

                $advs = db('Adv')->where(['pos_id' => $aPosId])->order("sort asc")->select();
                foreach ($advs as &$v) {
                    $data = json_decode($v['data'], true);
                    if (!empty($data)) {
                        $v = array_merge($v, $data);
                    }
                }
                unset($v);
                $this->assign('list', $advs);
                $this->assign('pos', $pos);
                return $this->fetch('editslider');
            } else {
                return $builder->show();
            }

        }

    }

}
