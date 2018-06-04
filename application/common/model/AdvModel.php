<?php

namespace app\common\model;

class AdvModel extends BaseModel
{
    protected $tableName = 'adv';

    /*  展示数据  */
    public function getAdvList($name, $path)
    {

        $list = cache('adv_list_' . $name . $path);
        if ($list === false) {
            $advposModel = new AdvPosModel();
            $advPos = $advposModel->getInfo($name, $path); //找到当前调用的广告位

            $advMap['pos_id'] = $advPos['id'];
            $advMap['status'] = 1;
            $advMap['start_time'] = ['lt', time()];
            $advMap['end_time'] = ['gt', time()];
            $data = $this->where($advMap)->order('sort asc')->select()->toArray();


            foreach ($data as &$v) {
                $d = json_decode($v['data'], true);
                if (!empty($d)) {
                    $v = array_merge($d, $v);

                }
            }
            unset($v);
            cache('adv_list_' . $name . $path, $list);
        }

        return $data;
    }

    /*——————————————————分隔线————————————————*/


}