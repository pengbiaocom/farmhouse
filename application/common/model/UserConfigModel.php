<?php

namespace app\common\model;


class UserConfigModel extends BaseModel
{
    public function addData($data = [])
    {
        $res = $this->save($data);
        return $res;
    }

    public function findData($map = [])
    {
        $res = $this->where($map)->find();
        return $res;
    }

    public function saveValue($map = [], $value = '')
    {
        if ($this->findData($map)) {
            $res = $this->where($map)->setField('value', $value);
        } else {
            $map['value'] = $value;
            $res = $this->where($map)->addData($map);
        }

        return $res;
    }
} 