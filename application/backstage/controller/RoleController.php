<?php
namespace app\backstage\controller;

use app\backstage\builder\BackstageListBuilder;
use app\backstage\builder\BackstageSortBuilder;
use app\backstage\builder\BackstageConfigBuilder;

use app\common\model\RoleModel;
use app\common\model\RoleConfigModel;
use app\common\model\AuthGroupModel;
use app\common\model\MemberModel;
use app\common\model\ModuleModel;
use app\common\model\PictureModel;

/**
 * 后台身份控制器
 * Class Role
 * @package Backstage\Controller
 */
class RoleController extends BackstageController
{

    //身份基本信息及配置 start

    public function index()
    {
        $r = config("LIST_ROWS");
        $roleModel = new RoleModel();
        $map['status'] = ['egt', 0];
        list($roleList, $page) = $roleModel->selectPageByMap($map, $r, 'sort asc');
        $map_group['id'] = ['in', array_column($roleList, 'group_id')];

        $group = db('RoleGroup')->where($map_group)->field('id,title')->select();
        $group = array_combine(array_column($group, 'id'), $group);
        $authGroupModel = new AuthGroupModel();
        $authGroupList = $authGroupModel->where(['status' => 1])->field('id,title')->select()->toArray();
        $authGroupList = array_combine(array_column($authGroupList, 'id'), array_column($authGroupList, 'title'));
        foreach ($roleList as &$val) {
            $user_groups = explode(',', $val['user_groups']);
            $val['group'] = $group[$val['group_id']]['title'];
            foreach ($user_groups as &$vl) {
                $vl = $authGroupList[$vl];
            }
            unset($vl);
            $val['user_groups'] = implode(',', $user_groups);
        }
        unset($val);

        $builder = new BackstageListBuilder;
        $builder->title(lang('_IDENTITY_LIST_'));
        $builder->setStatusUrl(url('setstatus'))
            ->buttonEnable()
            ->buttonDisable();
        $builder->keyId()
            ->keyText('title', lang('_ROLE_NAME_'))
            ->keyText('name', lang('_ROLE_MARK_'))
            ->keyText('group', lang('_GROUP_'))
            ->keyText('description', lang('_DESCRIPTION_'))
            ->keyText('user_groups', lang('_DEFAULT_USER_GROUP_'))
            ->keyText('sort', lang('_SORT_'))
//             ->keyYesNo('invite', lang('_DO_YOU_NEED_AN_INVITATION_TO_REGISTER_'))
//             ->keyYesNo('audit', lang('_REGISTRATION_WILL_NEED_TO_AUDIT_'))
            ->keyStatus()
            ->keyCreateTime()
            ->keyUpdateTime()
            ->keyDoActionEdit('role/editrole?id=###')
            ->data($roleList)
            ->pagination($page);
        return $builder->show();
    }

    /**
     * 编辑身份
     */
    public function editrole()
    {
        $roleModel = new RoleModel();
        $aId = input('id', 0, 'intval');
        $is_edit = $aId ? 1 : 0;
        $title = $is_edit ? lang('_EDIT_IDENTITY_') : lang('_NEW_IDENTITY_');

        if (Request()->isPost()) {
            $data['name'] = input('post.name', '', 'text');
            $data['title'] = input('post.title', '', 'text');
            $data['description'] = input('post.description', '', 'text');
            $data['group_id'] = input('post.group_id', 0, 'intval');
            $data['invite'] = input('post.invite', 0, 'intval');
            $data['audit'] = input('post.audit', 0, 'intval');
            $data['status'] = input('post.status', 1, 'intval');
            $data['user_groups'] = input('post.user_groups/a');

            if ($data['user_groups'] != '') {
                $data['user_groups'] = implode(',', $data['user_groups']);
            }

            if ($is_edit) {
                $result = $roleModel->updateByid($data,['id'=>$aId]);
            } else {
                $result = $roleModel->insert($data);
            }
            cookie('role', null);
            $aId = $roleModel->where(['name' => $data['name']])->value('id');
            if ($result) {
                $this->success($title . lang('_SUCCESS_'), url('Role/configmodule', ['id' => $aId]));
            } else {
                $error_info = $roleModel->getError();
                $this->error($title . lang('_FAILURE!__') . $error_info);
            }
        } else {
            $role = cookie('role');
            if ($role) {
                $data = $role;
            }

            $data['status'] = 1;
            $data['invite'] = 0;
            $data['audit'] = 0;
            if ($is_edit) {
                $data = $roleModel->getByMap(['id' => $aId]);
                $data['user_groups'] = explode(',', $data['user_groups']);
            }
           $authGroupModel = new AuthGroupModel();
            $authGroupList = $authGroupModel->where(['status' => 1])->field('id,title')->select()->toArray(); //权限组列表

            $group = db('RoleGroup')->field('id,title')->select();

            if (!$group) {
                $group = [0 => ['id' => '0', 'title' => lang('_NO_GROUP_')]];
            } else {
                $group = array_merge([0 => ['id' => '0', 'title' => lang('_NO_GROUP_')]], $group);
            }

            $this->assign('is_edit', $is_edit);
            $this->assign('group_list', $authGroupList);
            $this->assign('group', $group);
            $this->assign('this_role', ['id' => $aId]);
            $this->assign('data', $data);
            $this->assign('tab', 'edit');
            return $this->fetch('editrole');

        }
    }

    /**
     * 对身份进行排序
     */
    public function sort($ids = null)
    {
        $roleModel = new RoleModel();
        if (Request()->isPost()) {
            $builder = new BackstageSortBuilder;
            $builder->doSort('Role', $ids);
        } else {
            $map['status'] = ['egt', 0];
            $list = $roleModel->selectByMap($map, 'sort asc', 'id,title,sort');
            foreach ($list as $key => $val) {
                $list[$key]['title'] = $val['title'];
            }
            $builder = new BackstageSortBuilder;
            $builder->title(lang('_IDENTITY_SORT_'));
            $builder->data($list);
            $builder->buttonSubmit(url('sort'))->buttonBack();
            return $builder->show();
        }
    }

    /**
     * 身份状态设置
     * @param mixed|string $ids
     * @param $status
     */
    public function setstatus($ids, $status)
    {
        $roleModel = new RoleModel();
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        if (in_array(1, $ids)) {
            $this->error(lang('_ID_1_PRIORITY_'));
        }
        if ($status == 1) {
            $builder = new BackstageListBuilder;
            $builder->doSetStatus('Role', $ids, $status);
        } else if ($status == 0) {
            $result = $this->checkSingleRoleUser($ids);
            if ($result['status']) {
                $builder = new BackstageListBuilder;
                $builder->doSetStatus('Role', $ids, $status);
            } else {
                $this->error(lang('_IDENTITY_') . $result['role']['name'] . '（' . $result["role"]["id"] . '）【' . $result["role"]["title"] . '】中存在单身份用户，移出单身份用户后才能禁用该身份！');
            }
        } else if ($status == -1) { //（真删除）
            $result = $this->checkSingleRoleUser($ids);
            if ($result['status']) {
                $result = $roleModel->where(['id' => ['in', $ids]])->delete();
                if ($result) {
                    $userRoleList = db('UserRole')->where(['role_id' => ['in', $ids]])->select();
                    foreach ($userRoleList as $val) {
                        $this->setDefaultShowRole($val['role_id'], $val['uid']);
                    }
                    unset($val);
                    db('UserRole')->where(['role_id' => ['in', $ids]])->delete();
                    $this->success(lang('_DELETE_SUCCESS_'), url('Role/index'));
                } else {
                    $this->error(lang('_DELETE_FAILED_'));
                }
            } else {
                $this->error(lang('_IDENTITY_') . $result['role']['name'] . '（' . $result["role"]["id"] . '）【' . $result["role"]["title"] . '】中存在单身份用户，移出单身份用户后才能删除该身份！');
            }
        }
    }

    /**
     * 检测要删除的身份中是否存在单身份用户
     * @param $ids 要删除的身份ids
     * @return mixed
     */
    private function checkSingleRoleUser($ids)
    {
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        $memberModel = new MemberModel();
        $user_ids = $memberModel->where(['status' => -1])->field('uid')->select();
        $user_ids = array_column($user_ids, 'uid');

        $error_role_id = 0; //出错的身份id
        foreach ($ids as $role_id) {
            //获取拥有该身份的用户ids
            $uids = db('UserRole')->where(['role_id' => $role_id])->field('uid')->select();
            $uids = array_column($uids, 'uid');
            if (count($user_ids)) {
                $uids = array_diff($uids, $user_ids);
            }
            if (count($uids) > 0) { //拥有该身份
                $uids = array_unique($uids);
                //获取拥有其他身份的用户ids
                $have_uids = db('UserRole')->where(['role_id' => ['not in', $ids], 'uid' => ['in', $uids]])->field('uid')->select();
                if ($have_uids) {
                    $have_uids = array_column($have_uids, 'uid');
                    $have_uids = array_unique($have_uids);

                    //获取不拥有其他身份的用户ids
                    $not_have = array_diff($uids, $have_uids);
                    if (count($not_have) > 0) {
                        $error_role_id = $role_id;
                        break;
                    }
                } else {
                    $error_role_id = $role_id;
                    break;
                }
            }
        }
        unset($role_id, $uids, $have_uids, $not_have);
        $roleModel = new RoleModel();
        $result['status'] = 1;
        if ($error_role_id) {
            $result['role'] = $roleModel->where(['id' => $error_role_id])->field('id,name,title')->find();
            $result['status'] = 0;
        }
        return $result;
    }

    /**
     * 身份基本信息配置
     */
    public function config()
    {
        $builder = new BackstageConfigBuilder;
        $data = $builder->handleConfig();

        $builder->title(lang('_IDENTITY_BASIC_INFORMATION_CONFIGURATION_'))
            ->data($data)
            ->buttonSubmit()
            ->buttonBack();
        return $builder->show();
    }

    //身份基本信息及配置 end

    //身份用户管理 start

    public function userlist()
    {
        $r = config("LIST_ROWS");
        $roleModel = new RoleModel();
        $aRoleId = input('role_id', 0, 'intval');
        $aUserStatus = input('user_status', 0, 'intval');
        $aSingleRole = input('single_role', 0, 'intval');
        $role_list = $roleModel->field('id,title as value')->order('sort asc')->select();
        $role_id_list = array_column($role_list, 'id');
        if ($aRoleId && in_array($aRoleId, $role_id_list)) {//筛选身份
            $map_user_list['role_id'] = $aRoleId;
        } else {
            $map_user_list['role_id'] = $role_list[0]['id'];
        }
        if ($aUserStatus) {//筛选状态
            $map_user_list['status'] = $aUserStatus == 3 ? 0 : $aUserStatus;
        }

        $memberModel = new MemberModel();

        $user_ids = $memberModel->where(['status' => -1])->field('uid')->select();
        $user_ids = array_column($user_ids, 'uid');
        if ($aSingleRole) { //单身份筛选
            $uids = db('UserRole')->group('uid')->field('uid')->having('count(uid)=1')->select();
            $uids = array_column($uids, 'uid');//单身份用户id列表
            if ($aSingleRole == 1) {
                if (count($user_ids)) {
                    $map_user_list['uid'] = ['in', array_diff($uids, $user_ids)];
                } else {
                    $map_user_list['uid'] = ['in', $uids];
                }
            } else {
                if (count($uids) && count($user_ids)) {
                    $map_user_list['uid'] = ['not in', array_merge($user_ids, $uids)];
                } else if (count($uids)) {
                    $map_user_list['uid'] = ['not in', $uids];
                } else if (count($user_ids)) {
                    $map_user_list['uid'] = ['not in', $user_ids];
                }
            }
        } else {
            if (count($user_ids)) {
                $map_user_list['uid'] = ['not in', $user_ids];
            }
        }
        $list = db('UserRole')->where($map_user_list)->order('id desc')->paginate($r,false);
        $page = $list->render();
        $user_list = $list->toArray()['data'];
        foreach ($user_list as &$val) {
            $user = query_user(['nickname', 'avatar64'], $val['uid']);
            $val['nickname'] = $user['nickname'];
            $val['avatar'] = $user['avatar64'];
        }

        unset($user, $val);

        $statusOptions = [
            0 => ['id' => 0, 'value' => lang('_ALL_')],
            1 => ['id' => 1, 'value' => lang('_ENABLE_')],
            2 => ['id' => 2, 'value' => lang('_NOT_AUDITED_')],
            3 => ['id' => 3, 'value' => lang('_DISABLE_')],
        ];

        $singleRoleOptions = [
            0 => ['id' => 0, 'value' => lang('_ALL_')],
            1 => ['id' => 1, 'value' => lang('_SINGLE_USER_')],
            2 => ['id' => 2, 'value' => lang('_NON_SINGLE_USER_')],
        ];

        $builder = new BackstageListBuilder();
        $builder->title(lang('_IDENTITY_USER_LIST_'))
            ->setSearchPostUrl(url('Role/userlist'));
        if ($map_user_list['status'] == 2) {
            $builder->setStatusUrl(url('Role/setuseraudit', ['role_id' => $map_user_list['role_id']]))->buttonEnable('', lang('_AUDIT_THROUGH_'))->buttonDelete('', lang('_AUDIT_FAILURE_'));
        } else {
            $builder->setStatusUrl(url('Role/setuserstatus', ['role_id' => $map_user_list['role_id']]))->buttonEnable()->buttonDisable();
        }

        $builder->buttonModalPopup(url('Role/changerole', ['role_id' => $map_user_list['role_id']]), [], lang('_MIGRATING_USER_'), ['data-title' => lang('_MIGRATING_USER_TO_ANOTHER_IDENTITY_'), 'target-form' => 'ids'])
            ->button(lang('_INITIALIZE_THE_USER_'), ['href' => url('Role/initunhaveuser'),'class'=>'layui-btn layui-btn-primary ajax-post','hide-data'=>'true'])
            ->searchSelect(lang('_IDENTITY:_'), 'role_id', 'select', '', '', $role_list)->searchSelect(lang('_STATUS:_'), 'user_status', 'select', '', '', $statusOptions)->searchSelect('', 'single_role', 'select', '', '', $singleRoleOptions)
            ->keyId()
            ->keyImage('avatar', lang('_AVATAR_'))
            ->keyLink('nickname', lang('_NICKNAME_'), 'ucenter/index/information?uid={$uid}')
            ->keyStatus()
            ->pagination($page)
            ->data($user_list);
        return $builder->show();
    }

    /**
     * 移动用户
     */
    public function changerole()
    {
        if (Request()->isPost()) {
            $aIds = input('post.ids');
            $aRole_id = input('post.role_id', 0, 'intval');
            $aRole = input('post.role', 0, 'intval');
            $result['status'] = 0;
            if ($aRole_id == $aRole || $aRole == 0) {
                $result['info'] = lang('_ILLEGAL_OPERATION_');
                return $result;  //json
            }
            $ids = explode(',', $aIds);
            if (!count($ids)) {
                $result['info'] = lang('_NO_NEED_TO_TRANSFER_THE_USER_');
                return $result;  //json
            }

            $map['id'] = ['in', $ids];
            $uids = db('UserRole')->where($map)->field('uid')->select();
            $uids = array_column($uids, 'uid');

            $map_already['uid'] = ['in', $uids];
            $map_already['role_id'] = $aRole;
            $already_uids = db('UserRole')->where($map_already)->field('uid')->select();

            if (count($already_uids)) {
                $already_uids = array_column($already_uids, 'uid');
                $uids = array_diff($uids, $already_uids);//去除已存在的
            }


            $data['role_id'] = $aRole;
            $data['status'] = 1;
            $data['step'] = 'finish';
            $data['init'] = 1;
            foreach ($uids as $val) {
                $data['uid'] = $val;
                $data_list[] = $data;
            }
            unset($val);
            if (isset($data_list)) {
                db('UserRole')->insertAll($data_list);
            }
            $res = db('UserRole')->where($map)->delete();
            if ($res) {
                $result['status'] = 1;
            } else {
                $result['info'] = lang('_OPERATION_FAILED_');
            }
            return $result; //json
        } else {
            $aIds = input('ids');
            $aRole_id = input('role_id', 0, 'intval');
            $ids = implode(',', $aIds);
            $map['id'] = ['neq', $aRole_id];
            $map['status'] = 1;
            $roleModel = new RoleModel();
            $role_list = $roleModel->where($map)->field('id,title as value')->order('sort asc')->select();
            $this->assign('role_list', $role_list);
            $this->assign('ids', $ids);
            return $this->fetch('changerole');
        }
    }

    /**
     * 设置用户身份状态，启用、禁用
     * @param $ids
     * @param int $status
     * @param int $role_id
     */
    public function setuserstatus($ids, $status = 1, $role_id = 0)
    {
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        if ($status == 1) {
            $map_role['role_id'] = $role_id;
            $map_role['init'] = 0;
            $user_role = db('UserRole')->where($map_role)->field('id,uid')->select();
            $to_init_ids = array_column($user_role, 'id');
            $to_init_uids = array_combine($to_init_ids, $user_role);
            $to_init_ids = array_intersect($ids, $to_init_ids);//交集获得需要初始化的ids
            $memberModel = new MemberModel();
            foreach ($to_init_ids as $val) {
                $memberModel->initUserRoleInfo($role_id, $to_init_uids[$val]['uid']);
            }
            $builder = new BackstageListBuilder;
            $builder->doSetStatus('UserRole', $ids, $status);
        } else if ($status == 0) {
            $uids = db('UserRole')->where(['id' => ['in', $ids]])->field('uid')->select();
            if (count($uids)) {
                $uids = array_column($uids, 'uid');
                $map['role_id'] = ['neq', $role_id];
                $map['uid'] = ['in', $uids];
                $map['status'] = ['gt', 0];
                $has_other_role_user_ids = db('UserRole')->where($map)->field('uid')->select();
                if (count($has_other_role_user_ids)) {
                    $unHave = array_diff($uids, array_column($has_other_role_user_ids, 'uid'));
                } else {
                    $unHave = $uids;
                }
                if (count($unHave) > 0) {
                    $map_ids['uid'] = ['in', $unHave];
                    $map_ids['role_id'] = $role_id;
                    $error_ids = db('UserRole')->where($map_ids)->field('id')->select();
                    $error_ids = implode(',', array_column($error_ids, 'id'));

                    $this->error(lang('_ERROR_DISABLE_CANNOT_PARAM_', ['error_ids' => $error_ids]));
                }
                foreach ($uids as $val) {
                    $this->setDefaultShowRole($role_id, $val);
                }
                unset($val);
                $builder = new BackstageListBuilder;
                $builder->doSetStatus('UserRole', $ids, $status);
            } else {
                $this->error(lang('_NO_OPERATIONAL_DATA_'));
            }
        } else {
            $this->error(lang('_ILLEGAL_OPERATION_'));
        }
    }

    /**
     * 审核用户，通过，不通过
     * @param $ids
     * @param int $status
     * @param int $role_id
     */
    public function setuseraudit($ids, $status = 1, $role_id = 0)
    {
        $memberModel = new MemberModel();
        $ids = is_array($ids) ? $ids : explode(',', $ids);
        if ($status == 1) {
            $map_role['role_id'] = $role_id;
            foreach ($ids as $val) {
                $map_role['id'] = $val;
                $user_role = db('UserRole')->where($map_role)->find();
                if ($user_role['init'] == 0) {
                    $memberModel->initUserRoleInfo($role_id, $user_role['uid']);
                }
            }
            $builder = new BackstageListBuilder;
            $builder->doSetStatus('UserRole', $ids, $status);
        } else if ($status == -1) {
            $uids = db('UserRole')->where(['id' => ['in', $ids]])->field('uid')->select();
            if (count($uids)) {
                $builder = new BackstageListBuilder;
                $builder->doSetStatus('UserRole', $ids, $status);
            } else {
                $this->error(lang('_NO_OPERATIONAL_DATA_'));
            }
        } else {
            $this->error(lang('_ILLEGAL_OPERATION_'));
        }
    }


    /**
     * 重新设置用户默认身份及最后登录身份
     * @param $role_id
     * @param $uid
     * @return bool
     */
    private function setDefaultShowRole($role_id, $uid)
    {
        $memberModel = new MemberModel();
        $roleModel = new RoleModel();
        $user = query_user(['show_role', 'last_login_role'], $uid);
        if ($role_id == $user['show_role']) {
            $roles = db('UserRole')->where(['role_id' => ['neq', $role_id], 'uid' => $uid, 'status' => ['gt', 0]])->field('role_id')->select();
            $roles = array_column($roles, 'role_id');
            $show_role = $roleModel->where(['id' => ['in', $roles]])->order('sort asc')->find();
            $show_role_id = intval($show_role['id']);
            $data['show_role'] = $show_role_id;
            if ($role_id == $user['last_login_role']) {
                $data['last_login_role'] = $data['show_role'];
            }
            $memberModel->save($data,['uid' => $uid]);
        } else {
            $data = [];
            if ($role_id == $user['last_login_role']) {
                $data['last_login_role'] = $user['show_role'];
            }
            if(!empty($data)){
                $memberModel->save($data,['uid' => $uid]);
            }
        }
        return true;
    }

    //身份用户管理 end

    //身份分组 start

    /**
     * 分组列表
     */
    public function group()
    {
        $roleModel = new RoleModel();
        $group = db('RoleGroup')->field('id,title,update_time')->select();
        foreach ($group as &$val) {
            $map['group_id'] = $val['id'];
            $roles = $roleModel->selectByMap($map, 'id asc', 'title');
            $val['roles'] = implode(',', array_column($roles, 'title'));
        }
        unset($roles, $val);
        $builder = new BackstageListBuilder;
        $builder->title(lang('_ROLE_GROUP_2_') . lang('_ROLE_EXCLUSION_ONE_GROUP_'))
            ->buttonNew(url('Role/editgroup'))
            ->keyId()
            ->keyText('title', lang('_TITLE_'))
            ->keyText('roles', lang('_GROUP_IDENTITY_'))
            ->keyUpdateTime()
            ->keyDoActionEdit('Role/editgroup?id=###')
            ->keyDoAction('Role/deletegroup?id=###', lang('_DELETE_'))
            ->data($group);
        return $builder->show();
    }

    /**
     * 编辑分组
     */
    public function editgroup()
    {
        $roleModel = new RoleModel();
        $aGroupId = input('id', 0, 'intval');
        $is_edit = $aGroupId ? 1 : 0;
        $title = $is_edit ? lang('_EDIT_GROUP_') : lang('_NEW_GROUP_');
        if (Request()->isPost()) {
            $role['title'] = input('rank', '', 'text');
            $role['name'] = input('name', '', 'text');
            $role['description'] = input('description', '', 'text');
            $arr = array_filter($role);
            if (!empty($arr)) {
                cookie('role', $role, 600);
            }

            $data['title'] = input('post.title', '', 'op_t');
            $data['update_time'] = time();
            $roles = input('post.roles');
            if ($is_edit) {
                $result = db('RoleGroup')->where(['id' => $aGroupId])->update($data);
                if ($result) {
                    $result = $aGroupId;
                }
            } else {
                if (db('RoleGroup')->where(['title' => $data['title']])->count()) {
                    $this->error("{$title}" . lang('_FAIL_GROUP_EXIST_') . lang('_EXCLAMATION_'));
                } elseif ($data['title']) {
                    $result = db('RoleGroup')->insert($data);
                }
            }
            if ($result) {
                $roleModel->where(['group_id' => $result])->setField('group_id', 0); //所有该分组下的身份全部移出
                if (!is_null($roles)) {
                    $roleModel->where(['id' => ['in', $roles]])->setField('group_id', $result); //选中的身份全部移入分组
                }
                $this->success("{$title}" . lang('_SUCCESS_') . lang('_EXCLAMATION_'), url('Role/editRole'));
            } else {
                $this->error("{$title}" . lang('_FAILURE_') . lang('_EXCLAMATION_'));
            }
        } else {
            $data =[];
            if ($is_edit) {
                $data = db('RoleGroup')->where(['id' => $aGroupId])->find();
                $map['group_id'] = $aGroupId;
                $roles = $roleModel->selectByMap($map, 'id asc', 'id');
                $data['roles'] = array_column($roles, 'id');
            }
            $roles = $roleModel->field('id,group_id,title')->select();
            foreach ($roles as &$val) {
                $val['title'] = $val['group_id'] ? $val['title'] . lang('_ID_CURRENT_GROUP_') . lang('_COLON_') . "  {$val['group_id']})" : $val['title'];
            }
            unset($val);
            $builder = new BackstageConfigBuilder;
            $builder->title("{$title}" . lang('_ROLE_EXCLUSION_ONE_GROUP_'));
            $builder->keyId()
                ->keyText('title', lang('_TITLE_'))
                ->keyChosen('roles', lang('_GROUP_IDENTITY_SELECTION_'), lang('_AN_IDENTITY_CAN_ONLY_EXIST_IN_ONE_GROUP_AT_THE_SAME_TIME_'), $roles)
                ->buttonSubmit()
                ->buttonBack()
                ->data($data);
            return $builder->show();
        }
    }

    /**
     * 删除分组（真删除）
     */
    public function deletegroup()
    {
        $roleModel = new RoleModel();
        $aGroupId = input('id', 0, 'intval');
        if (!$aGroupId) {
            $this->error(lang('_PARAMETER_ERROR_'));
        }
        $roleModel->where(['group_id' => $aGroupId])->setField('group_id', 0);
        $result = db('RoleGroup')->where(['id' => $aGroupId])->delete();
        if ($result) {
            $this->success(lang('_DELETE_SUCCESS_'));
        } else {
            $this->error(lang('_DELETE_FAILED_'));
        }
    }

    //身份分组end

    //身份其他配置 start

    public function configmodule()
    {
        $aRoleId = input('id', 0, 'intval');
        if (!$aRoleId) {
            $this->error(lang('_PLEASE_CHOOSE_YOUR_IDENTITY_'));
        }
        $moduleModel =new ModuleModel();
        $roleModel = new RoleModel();
        $modules = $moduleModel->getAll(1);

        if(Request()->isPost()){
            $aAllowModel=input('post.allow_module',[],'intval');
            foreach ($modules as $val){
                if(!in_array($val['name'],['Core','Ucenter'])){
                    if($val['auth_role'][0] == ''){
                        if(!in_array($val['id'],$aAllowModel)){
                            $moduleModel->setModuleRole($val['id'],$aRoleId);
                        }
                    }else{
                        if(in_array($val['id'],$aAllowModel)){
                            $val['auth_role'][]=$aRoleId;
                            $moduleModel->setModuleRole($val['id'],implode(',',array_unique($val['auth_role'])));
                        }else{
                            $auth_role=implode(',',array_diff($val['auth_role'],[$aRoleId]));
                            $moduleModel->setModuleRole($val['id'],$auth_role);
                        }
                    }
                }
            }
            $this->success('操作成功！');
        }else{
            foreach ($modules as $key=>$val){
                if(in_array($val['name'],['Core','Ucenter'])){
                    unset($modules[$key]);
                }
            }
            $this->assign('modules', $modules);

            $mRole_list = $roleModel->field('id,title')->select();
            $this->assign('role_list', $mRole_list);
            $this->assign('this_role', ['id' => $aRoleId]);

            $this->assign('tab','module');
            return $this->fetch('module');
        }
    }

    //身份其他配置 end

    /**
     * 上传图片（上传默认头像）
     */
    public function uploadpicture()
    {
        //TODO: 用户登录检测

        /* 返回标准数据 */
        $return = ['status' => 1, 'info' => lang('_UPLOAD_SUCCESS_'), 'data' => ''];

        /* 调用文件上传组件上传文件 */
        $pictureModel = new PictureModel();
        $pic_driver = config('picture_upload_driver');
        $info = $pictureModel->upload(
            $_FILES,
            config('picture_upload'),
            config('picture_upload_driver'),
            config("picture_{$pic_driver}_config")
        ); //TODO:上传到远程服务器
        /* 记录图片信息 */
        if ($info) {
            $return['status'] = 1;
            empty($info['download']) && $info['download'] = $info['file'];
            $return = array_merge($info['download'], $return);
            $return['path256'] = getThumbImageById($return['id'], 256, 256);
            $return['path128'] = getThumbImageById($return['id'], 128, 128);
            $return['path64'] = getThumbImageById($return['id'], 64, 64);
            $return['path32'] = getThumbImageById($return['id'], 32, 32);
        } else {
            $return['status'] = 0;
            $return['info'] = $pictureModel->getError();
        }
        /* 返回JSON数据 */
        $this->ajaxReturn($return);
    }


    /**
     * 初始化没身份的用户
     */
    public function initunhaveuser()
    {
        $memberModel = new MemberModel();
        $roleModel = new RoleModel();

        $uids = $memberModel->field('uid')->select();
        $uids = array_column($uids, 'uid');

        $role = $roleModel->selectByMap(['status' => 1]);
        $role = array_column($role, 'id');
        $map['role_id'] = ['in', $role];

        $have_uids = db('UserRole')->where($map)->field('uid')->select();
        if (count($have_uids)) {
            $have_uids = array_column($have_uids, 'uid');
            $have_uids = array_unique($have_uids);
            $not_have_uids = array_diff($uids, $have_uids);
        }

        $data['status'] = 1;
        $data['role_id'] = 1;
        $data['step'] = "finish";
        $data['init'] = 1;
        $dataList = [];

        foreach ($not_have_uids as $val) {
            $data['uid'] = $val;
            $dataList[] = $data;
            $memberModel->initUserRoleInfo(1, $val);
            $memberModel->initDefaultShowRole(1, $val);
        }
        unset($val);
        db("UserRole")->insertAll($dataList);
        $this->success(lang('_OPERATION_SUCCESS_'));
    }

    /**-----------------------------模块按身份可用--------------------------------**/

    /**
     * 模块身份访问权限设置
     */
    public function modulerole()
    {
        $moduleModel = new ModuleModel();
        $roleModel = new RoleModel();
        $modules = $moduleModel->getAll(1);
        if (Request()->isPost()) {
            $role_module = input('post.role_module', [], 'intval');
            foreach ($modules as $val) {
                if (!$role_module[$val['id']]) {
                    $auth_role = '';
                } else {
                    $auth_role = implode(',', $role_module[$val['id']]);
                }
                $moduleModel->setModuleRole($val['id'], $auth_role);
            }
            $moduleModel->cleanModulesCache();
            $this->success('保存成功！');
        } else {
            foreach ($modules as $key=>$val){
                if(in_array($val['name'],['Core','Ucenter'])){
                    unset($modules[$key]);
                }
            }
            $this->assign('modules', $modules);

            $role_list = $roleModel->selectByMap(['status' => 1]);
            $this->assign('role_list', $role_list);
            return $this->fetch();
        }

    }
} 