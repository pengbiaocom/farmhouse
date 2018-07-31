<?php
namespace app\backstage\controller;

use app\common\model\AuthRuleModel;
use app\common\model\AuthGroupModel;
use app\common\model\MemberModel;
use app\common\model\ModuleModel;
use app\backstage\builder\BackstageConfigBuilder;

/**
 * 权限管理控制器
 * Class AuthManager
 */
class AuthManagerController extends BackstageController
{

    /**
     * 后台节点配置的url作为规则存入auth_rule
     * 执行新节点的插入,已有节点的更新,无效规则的删除三项任务
     */
    public function updateRules()
    {
        //需要新增的节点必然位于$nodes
        $nodes = $this->returnNodes(false);

        $authRuleModel = new AuthRuleModel();
        $map =['module' => 'backstage', 'type' => ['in', '1,2']];//status全部取出,以进行更新
        //需要更新和删除的节点必然位于$rules
        $rules = $authRuleModel->where($map)->order('name')->select();

        //构建insert数据
        $data = [];//保存需要插入和更新的新节点
        foreach ($nodes as $value) {
            $temp['name'] = $value['url'];
            $temp['title'] = $value['title'];
            $temp['module'] = 'backstage';
            if ($value['pid'] > 0) {
                $temp['type'] = AuthRuleModel::RULE_URL;
            } else {
                $temp['type'] = AuthRuleModel::RULE_MAIN;
            }
            $temp['status'] = 1;
            $data[strtolower($temp['name'] . $temp['module'] . $temp['type'])] = $temp;//去除重复项
        }

        $update = [];//保存需要更新的节点
        $ids = [];//保存需要删除的节点的id
        foreach ($rules as $index => $rule) {
            $key = strtolower($rule['name'] . $rule['module'] . $rule['type']);
            if (isset($data[$key])) {//如果数据库中的规则与配置的节点匹配,说明是需要更新的节点
                $data[$key]['id'] = $rule['id'];//为需要更新的节点补充id值
                $update[] = $data[$key];
                unset($data[$key]);
                unset($rules[$index]);
                unset($rule['condition']);
                $diff[$rule['id']] = $rule;
            } elseif ($rule['status'] == 1) {
                $ids[] = $rule['id'];
            }
        }
        if (count($update)) {
            foreach ($update as $k => $row) {
                if ($row != $diff[$row['id']]) {
                    $authRuleModel->save($row,['id'=>$row['id']]);
                }
            }
        }
        if (count($ids)) {
            $authRuleModel->save(['status' => -1],['id' => ['IN', implode(',', $ids)]]);
            //删除规则是否需要从每个权限组的访问授权表中移除该规则?
        }
        if (count($data)) {
            $authRuleModel->saveAll(array_values($data));
        }
        if ($authRuleModel->getError()) {
            trace('[' . __METHOD__ . ']:' . $authRuleModel->getError());
            return false;
        } else {
            return true;
        }
    }


    /**
     * 权限管理首页
     */
    public function index()
    {
        $list = $this->lists('AuthGroup', ['module' => 'backstage'], 'id asc');

        $list = int_to_string($list['list']);
        $this->assign('_list', $list);
        $this->assign('_use_tip', true);
        $this->assign('meta_title',lang('_PRIVILEGE_MANAGEMENT_'));
        return $this->fetch();
    }

    /**
     * 创建管理员权限组
     */
    public function createGroup()
    {
        $builder=new BackstageConfigBuilder();
        $data = [
            'id'=>'',
            'title'=>'',
            'description'=>'',
            'end_time'=>''
        ];
        $builder->title(lang('_NEW_USER_GROUP_'))
            ->keyHidden('id','')
            ->keyText('title','权限组'.lang('_COLON_'),'请输入权限组')
            ->keyTextArea('description','描述'.lang('_COLON_'),'请输入内容')
            ->keyTime('end_time','过期时间(空为永久)')
            ->buttonSubmit(url('AuthManager/writeGroup'))
            ->buttonBack()
            ->data($data);
        return $builder->show();
    }

    /**
     * 编辑管理员权限组
     */
    public function editGroup()
    {
        $id = input('id',0,'intval');
        $authGroupModel = new AuthGroupModel();
        $auth_group = $authGroupModel->where(['module' => 'backstage', 'type' => AuthGroupModel::TYPE_ADMIN])
            ->find($id);

        $builder=new BackstageConfigBuilder();
        $builder->title(lang('_EDIT_USER_GROUP_'))
            ->keyHidden('id','')
            ->keyText('title','权限组'.lang('_COLON_'),'请输入权限组')
            ->keyTextArea('description','描述'.lang('_COLON_'),'请输入内容')
            ->keyTime('end_time','过期时间(空为永久)')
            ->buttonSubmit(url('AuthManager/writeGroup'))
            ->buttonBack()
            ->data($auth_group);
        return $builder->show();
    }

    /**
     * 管理员权限组数据写入/更新
     */
    public function writeGroup()
    {
        $data = $this->request->param();
        if (isset($data['rules'])) {
            sort($data['rules']);
            $data['rules'] = implode(',', array_unique($data['rules']));
        }
        $data['module'] = 'backstage';
        $data['type'] = AuthGroupModel::TYPE_ADMIN;
        $data['end_time'] = empty($data['end_time']) ? '2000000000':$data['end_time'];
        $authGroupModel = new AuthGroupModel();

//         $oldGroup = $authGroupModel->find($data['id']);
//         $data['rules'] = $this->getMergedRules($oldGroup['rules'], explode(',', $data['rules']), 'eq');
        if (empty($data['id'])) {
            $r = $authGroupModel->allowField(true)->isUpdate(false)->save($data);
        } else {
            $r = $authGroupModel->allowField(true)->isUpdate(true)->save($data,['id'=>$data['id']]);
        }
        if ($r === false) {
            $this->error('操作失败！'. $authGroupModel->getError());
        } else {
            $this->success('操作成功！');
        }
    }

    /**
     * 修改权限组描述
     */
    public function descriptionGroup()
    {

        $title = input('post.title');
        $description = input('post.description');
        $id = input('post.id');
        $authGroupModel = new AuthGroupModel();
        $data['description']=$description;
        $data['title']=$title;
        $data['end_time']=input('post.end_time',2000000000,'intval');
        $res=$authGroupModel->save($data,['id'=>$id]);
        if($res)
        {
            $this->success('修改成功!');
        }
        else{
            $this->error('修改失败!');
        }

    }
    /**
     * 状态修改
     */
    public function changeStatus()
    {
        $data = $this->request->param();
        $id = $data['id'];
        $method = $data['method'];
        if (empty($id)) {
            $this->error(lang('_PLEASE_CHOOSE_TO_OPERATE_THE_DATA_'));
        }
        $id = array_unique($id);
        $id = is_array($id) ? implode(',', $id) : $id;
        $map['id'] = ['id' => ['in', $id]];
        switch (strtolower($method)) {
            case 'forbidgroup':
                $this->forbid('AuthGroup',$map);
                break;
            case 'resumegroup':
                $this->resume('AuthGroup',$map);
                break;
            case 'deletegroup':
                if(model('AuthGroup')->where($map)->delete()){
                    $this->success(lang('_OPERATION_SUCCESS_'));
                }else{
                    $this->error(lang('_OPERATION_FAILED_'));
                }
                break;
            default:
                $this->error($method .  '参数非法');
        }
    }

    /**
     * 权限组授权用户列表
     */
    public function user()
    {
        $group_id = input('group_id');
        $authGroupModel = new AuthGroupModel();
        if (empty($group_id)) {
            $this->error(lang('_PARAMETER_ERROR_'));
        }

        $auth_group = $authGroupModel->field('id,id,title,rules')->where(['status' => ['egt', '0'], 'module' => 'backstage', 'type' => AuthGroupModel::TYPE_ADMIN])
            ->select();
        $prefix   = config('database.prefix');
        $l_table = $prefix . (AuthGroupModel::MEMBER);
        $r_table = $prefix . (AuthGroupModel::AUTH_GROUP_ACCESS);
        $map = ['a.group_id' => $group_id, 'm.status' => ['egt', 0]];
        $order= 'm.uid asc';
        $field = "m.uid,m.nickname,m.last_login_time,m.last_login_ip,m.status";
        $total = db()->table($l_table)->alias('m')->join($r_table . ' a ',' m.uid=a.uid')->where($map)->count();
        $REQUEST = $this->request->param();

        if (isset($REQUEST['r'])) {
            $listRows = (int)$REQUEST['r'];
        } else {
            $listRows = config('LIST_ROWS') > 0 ? config('LIST_ROWS') : 10;
        }
        $list = db()->table($l_table)->alias('m')->join($r_table . ' a ',' m.uid=a.uid')->field($field)->where($map)->order($order)->paginate($listRows,false);
        $page = $list->render();
        $list = $list->toArray()['data'];
        int_to_string($list);

        $this->assign('_page', $page ? $page : '');
        $this->assign('_total', $total);
        $this->assign('_list', $list);
        $this->assign('auth_group', $auth_group);
        $this->assign('this_group_id', input('group_id',0,'intval'));
        $this->assign('meta_title',lang('_MEMBER_AUTHORITY_'));
        return $this->fetch();
    }



    public function tree($tree = null)
    {
        $this->assign('tree', $tree);
        return $this->fetch();
    }

    /**
     * 将用户添加到权限组的编辑页面
     */
    public function group()
    {
        $uid = input('uid');
        $authGroupModel = new AuthGroupModel();
        $auth_groups = $authGroupModel->getGroups();
        $user_groups = $authGroupModel->getUserGroup($uid);
        $ids = [];
        foreach ($user_groups as $value) {
            $ids[] = $value['group_id'];
        }
        $memeberModel = new MemberModel();
        $nickname =$memeberModel->getNickName($uid);
        $this->assign('nickname', $nickname);
        $this->assign('auth_groups', $auth_groups);
        $this->assign('user_groups', implode(',', $ids));
        return $this->fetch();
    }

    /**
     * 将用户添加到权限组,入参uid,group_id
     */
    public function addToGroup()
    {
        $data = $this->request->param();
        $uid = $data['uid'];
        $gid = $data['group_id'];
        if (empty($uid)) {
            $this->error(lang('_PARAMETER_IS_INCORRECT_'));
        }
        $authGroupModel = new AuthGroupModel();
        $memeberModel = new MemberModel();
        if (is_numeric($uid)) {
            if (is_administrator($uid)) {
                $this->error(lang('_THE_USER_IS_A_SUPER_ADMINISTRATOR_'));
            }
            if (!$memeberModel->where(['uid' => $uid])->find()) {
                $this->error(lang('_ADMIN_USER_DOES_NOT_EXIST_'));
            }
        }

        if ($gid && !$authGroupModel->checkGroupId($gid)) {
            $this->error($authGroupModel->getError());
        }
        if ($authGroupModel->addToGroup($uid, $gid)) {
            $this->success(lang('_SUCCESS_OPERATE_'),url('user/index'));
        } else {
            $this->error($authGroupModel->getError());
        }
    }

    /**
     * 将用户从权限组中移除  入参:uid,group_id
     */
    public function removeFromGroup()
    {
        $uid = input('uid');
        $gid = input('group_id');
        if ($uid == UID) {
            $this->error(lang('_NOT_ALLOWED_TO_RELEASE_ITS_OWN_AUTHORITY_'));
        }
        if (empty($uid) || empty($gid)) {
            $this->error(lang('_PARAMETER_IS_INCORRECT_'));
        }

        $authGroupModel = new AuthGroupModel();
        if (!$authGroupModel->find($gid)) {
            $this->error(lang('_USER_GROUP_DOES_NOT_EXIST_'));
        }
        if ($authGroupModel->removeFromGroup($uid, $gid)) {
            $this->success(lang('_SUCCESS_OPERATE_'));
        } else {
            $this->error(lang('_FAIL_OPERATE_'));
        }
    }

    /**
     * 将分类添加到权限组  入参:cid,group_id
     */
    public function addToCategory()
    {
        $cid = input('cid');
        $gid = input('group_id');
        if (empty($gid)) {
            $this->error(lang('_PARAMETER_IS_INCORRECT_'));
        }

        $authGroupModel = new AuthGroupModel();
        if (!$authGroupModel->find($gid)) {
            $this->error(lang('_USER_GROUP_DOES_NOT_EXIST_'));
        }
        if ($cid && !$authGroupModel->checkCategoryId($cid)) {
            $this->error($authGroupModel->getError());
        }
        if ($authGroupModel->addToCategory($gid, $cid)) {
            $this->success(lang('_SUCCESS_OPERATE_'));
        } else {
            $this->error(lang('_FAIL_OPERATE_'));
        }
    }

    public function addNode()
    {
        $authRuleModel = new AuthRuleModel();
        if (empty($this->auth_group)) {
            $this->assign('auth_group',['title' => null, 'id' => null, 'description' => null, 'rules' => null]);//排除notice信息
        }
        if (Request()->isPost()) {

            $data = $this->request->param();
            if(empty($data['title'])) $this->error("请输入标题！");
            if ($data) {
                if (intval($data['id']) == 0) {
                    $authRuleModel->allowField(true)->save($data);
                    $id = $authRuleModel->id;
                } else {
                    $authRuleModel->allowField(true)->save($data,['id'=>$data['id']]);
                    $id = $data['id'];
                }

                if ($id) {
                    $this->success(lang('_SUCCESS_EDIT_'),url("AuthManager/index"));
                } else {
                    $this->error(lang('_EDIT_FAILED_'));
                }
            } else {
                $this->error($authRuleModel->getError());
            }
        } else {
            $aId = input('id', 0, 'intval');
            if ($aId == 0) {
                $info['module']=input('module','','op_t');
            }else{
                $info = $authRuleModel->find($aId);
            }

            $moduleModel = new ModuleModel();
            if(!empty($modelName)){
                $module[$modelName['module']] =$modelName['title'].'-'.$modelName['module'];
            }else{
                $module['all'] = lang('_SYSTEM_CORE_MENU_');
            }
            $modules = $moduleModel->getAll();
            if(!empty($modules)){
                foreach($modules as $k=>$v){
                    $module[$v['name']] = $v['alias'];
                }
            }

            $builder=new BackstageConfigBuilder();

            $builder->title(empty($aId)?lang('_NEW_WITH_SINGLE_').lang('_FRONT_RIGHT_NODE_'):lang('_EDIT_WITH_SINGLE_').lang('_FRONT_RIGHT_NODE_'))
                ->keyHidden('id','')
                ->keyText('title',lang('_TITLE_').lang('_COLON_'),lang('_USED_IN_THE_CONFIGURATION_HEADER_'))
                ->keyText('name',lang('_NODE_IDENTITY_').lang('_COLON_'),lang('_USED_TO_DISTINGUISH_RIGHT_FROM_CODE_').lang('_UNIQUE_IDENTIFIER_'))
                ->keySelect('module',lang('_THE_MODULE_').lang('_COLON_'),lang('_MODULES_OF_THE_RIGHT_NODE_'),$module)
                ->buttonSubmit()
                ->buttonBack()
                ->data($info);
            return $builder->show();
        }

    }

    public function deleteNode(){
        $aId=input('id',0,'intval');
        if($aId>0){
            $authRuleModel = new AuthRuleModel();
            $result=   $authRuleModel->where(['id'=>$aId])->delete();
            if($result){
                $this->success(lang('_DELETE_SUCCESS_'));
            }else{
                $this->error(lang('_DELETE_FAILED_'));
            }
        }else{
            $this->error(lang('_YOU_MUST_SELECT_THE_NODE_'));
        }
    }
    /**
     * 访问授权页面
     */
    public function access()
    {
        $authRuleModel = new AuthRuleModel();
        $authGroupModel = new AuthGroupModel();
        $this->updateRules();
        $auth_group = $authGroupModel->where(['status' => ['egt', '0'], 'module' => 'backstage', 'type' => AuthGroupModel::TYPE_ADMIN])
            ->column('id,id,title,rules');

        $node_list = $this->returnNodes();
        $map = ['module' => 'backstage', 'type' => AuthRuleModel::RULE_MAIN, 'status' => 1];
        $main_rules = $authRuleModel->where($map)->column('name,id');

        $map = ['module' => 'backstage', 'type' => AuthRuleModel::RULE_URL, 'status' => 1];
        $child_rules = $authRuleModel->where($map)->column('name,id');

        $this->assign('main_rules', $main_rules);
        $this->assign('auth_rules', $child_rules);
        $this->assign('node_list', $node_list);
        $this->assign('auth_group', $auth_group);
        $this->assign('this_group', $auth_group[input('group_id',0,'intval')]);
        $this->assign('meta_title',lang('_ACCESS_AUTHORIZATION_'));
        return $this->fetch();
    }

    public function accessUser()
    {
        $aId = input('group_id', 0, 'intval');
        $authRuleModel = new AuthRuleModel();
        $authGroupModel = new AuthGroupModel();
        if (Request()->isPost()) {
            $aId = input('id', 0, 'intval');
            $aOldRule = input('post.old_rules', '', 'text');
            $aRules = input('post.rules/a', []);
            $rules = $this->getMergedRules($aOldRule, $aRules);

            $group = db("auth_group")->find($aId);
            $group['rules'] = $rules;

            $result = $authGroupModel->allowField(true)->isUpdate(true)->save($group,['id'=>$aId]);
            if ($result) {
                $this->success(lang('_RIGHT_TO_SAVE_SUCCESS_'));
            } else {
                $this->error(lang('_RIGHT_SAVE_FAILED_'));
            }

        }else{
            $this->updateRules();
            $auth_group = $authGroupModel->where(['status' => ['egt', '0'], 'type' => AuthGroupModel::TYPE_ADMIN])
                ->column('id,id,title,rules');
            $moduleModel = new ModuleModel();
            $node_list = $this->getNodeListFromModule($moduleModel->getAll());

            $map = ['module' => ['neq', 'backstage'], 'type' => AuthRuleModel::RULE_MAIN, 'status' => 1];
            $main_rules = $authRuleModel->where($map)->column('name,id');
            $map = ['module' => ['neq', 'backstage'], 'type' => AuthRuleModel::RULE_URL, 'status' => 1];
            $child_rules = $authRuleModel->where($map)->column('name,id');

            $group = $authGroupModel->find($aId);
            $this->assign('main_rules', $main_rules);
            $this->assign('auth_rules', $child_rules);
            $this->assign('node_list', $node_list);
            $this->assign('auth_group', $auth_group);
            $this->assign('this_group', $group);

            $this->assign('meta_title',lang('_USER_FRONT_DESK_AUTHORIZATION_'));
            return $this->fetch();
        }

    }

    private function getMergedRules($oldRules, $rules, $isAdmin = 'neq')
    {
        $authRuleModel = new AuthRuleModel();
        $map = ['module' => [$isAdmin, 'backstage'], 'status' => 1];
        $otherRules = $authRuleModel->where($map)->field('id')->select();
        $oldRulesArray = explode(',', $oldRules);
        $otherRulesArray = getSubByKey($otherRules, 'id');

        //1.删除全部非Admin模块下的权限，排除老的权限的影响
        //2.合并新的规则
        foreach ($otherRulesArray as $key => $v) {
            if (is_object($v, $oldRulesArray)) {
                $key_search = array_search($v, $oldRulesArray);
                if ($key_search !== false)
                    array_splice($oldRulesArray, $key_search, 1);
            }
        }

        return str_replace(',,', ',', implode(',', array_unique(array_merge($oldRulesArray, $rules))));


    }

    //预处理规则，去掉未安装的模块
    public function getNodeListFromModule($modules)
    {
        $authRuleModel = new AuthRuleModel();
        $node_list = [];
        if(!empty($modules)){
            foreach ($modules as $module) {
                if ($module['is_setup']) {

                    $node = ['name' => $module['name'], 'alias' => $module['alias']];
                    $map = ['module' => $module['name'], 'type' => AuthRuleModel::RULE_URL, 'status' => 1];

                    $node['child'] = $authRuleModel->where($map)->select();
                    $node_list[] = $node;
                }

            }
        }

        return $node_list;
    }
}
