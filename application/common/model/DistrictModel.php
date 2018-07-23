<?php
namespace app\common\model;
/**
 * 地区库
 * User: 丶陌路灬离殇 <pengxuancom@163.com>
 */

class DistrictModel extends BaseModel{

    /**
     * 根据地理id 获取 地理面包屑
     * @param $classId
     * @param int $end
     * @return string
     */
    public function adminTipOffset($classId,$end=0){
        $classId= trim($classId,',');
        if(false !== strpos($classId,',')){
            $classId = preg_replace('/^.*,(\d+)$/','\\1',$classId);
        }
        $offset = '';
        do{
            $data = $this->where("id='{$classId}'")->field('id,name,upid')->find();
            if($data){
                if($classId==$end){
                    break;
                }
                $offset = "<a href='".url('backstage/District/index',['id'=>$data['id']])."'>".$data['name']."</a>".' <span class="layui-box">&gt;</span> '.$offset;
                $classId = $data['upid'];
            }else{
                break;
            }
        }while($classId);

        return $offset;
    }

    /**
     * 添加分类
     * @param mixed|string $arr
     * @return int
     */
    function add($arr){
        $classId = db("District")->insertGetId($arr);
        if($classId){
            $idList = $this->getAllParentId($arr['upid']).','.$classId.',';
            $savearr['level'] = strlen($idList)-strlen(str_replace(',','',$idList));
            db("District")->where('id='.$classId)->update(['level'=>$savearr['level']]);
        }
        return $classId;
    }

    /**
     * 根据汉字，得到这组汉字的拼音
     *@param string $str utf-8编码中文汉字
     *@return string
     */
    function getCharIndex($str){
        $pinyin = new  PinYinModel();// 实例化拼音类
        $chars = $pinyin->Pinyin($str);
        return $chars;
    }

    /**
     * 取得分传入分类IdList
     * 返回：3,4,
     *@param integer $classId
     *@return string
     */
    public function getAllParentId($classId){
        $classId=intval($classId);
        if(empty($classId)) return;
        if($classId<1)	return $classId;
        $row = $this->where('id='.$classId)->field('upid')->find();
        if($row['upid']<1)
            return $classId;
        else{
            return $this->getAllParentId($row['upid']).','.$classId;
        }
    }

    /**
     * 修改地理位置
     * @param $arr
     * @param $where
     * @return int
     */
    function edit($arr,$where){
        $data = $this->where($where)->find();
        if($data['upid'] != $arr['upid']){
            //重新计算当天项的idList
            $arr['idlist'] = $this->getAllParentId($arr['upid']).','.$data['id'].',';
            $arr['idlist'] = ltrim($arr['idlist'],',');
            $arr['level']  = strlen($arr['idlist'])-strlen(str_replace(',','',$arr['idlist']));
        }
        unset($arr['idlist']);
        $res = $this->isUpdate(true)->where($where)->save($arr);
        return $res;
    }

    /**
     * 根据分类id查出下面所有分类
     * 该函数用递归方式查询分类字符串
     * 返回字数据集：$arr。
     *@param integer $parentid 查询的根目录
     *@param integer $tab 缩进级数(一般从0开始)
     *@return array
     */
    public function folder($parentid = 0, $tab = 0) {
        $rows = $this->field("id,upid,name,level")->where("upid=".$parentid)->order("id asc")->select();
        if(!empty($rows))
        {
            foreach($rows as $key=>$row){
                // 输出推荐使用 echo 命令
                $arr[] = $row;
                $arr = array_merge($arr,(array)$this->folder($row["id"], $tab + 1));
            }
        }
        return $arr;
    }

    public function folderById($parentid = 0, $tab = 0) {
        $rows = $this->field("id")->where("upid=".$parentid)->order("id asc")->select();
        if(!empty($rows))
        {
            foreach($rows as $key=>$row){
                // 输出推荐使用 echo 命令
                $arr[] = $row;
                $arr = array_merge($arr,(array)$this->folderById($row["id"], $tab + 1));
            }
        }
        return $arr;
    }

    /**
     * 删除分类,级联删除所有子类及图片
     * @param string $where
     * @return mixed
     */
    function del($where='1=2'){
        $datas = $this->where($where)->field("id")->select();
        foreach($datas as $row){
            $rows = $this->where('upid = "'.$row['id'].'"')->field('id')->select();
            if(!empty($rows)){
                $cateIdList="";
                foreach($rows as $rs){
                    $cateIdList .= ','.$rs['id'];
                }
                self::del('id in ('.$cateIdList.')');
            }
        }
        return $this->where($where)->delete();
    }

    /**
     *取得传入分类idlist
     * 返回：3,4,  所有下级分类
     *@param integer $parentId
     *@return string
     */
    public function getClassId($parentId){
        $rows=$this->field('id')->where('upid in ('.$parentId.')')->select();
        if(empty($rows)){
            return $parentId;
        }else{
            $classIdList='';
            foreach($rows as $row){
                $classIdList.=','.$row['id'];
            }
            $classIdList=trim($classIdList,',');
            return self::getClassId($classIdList).','.$parentId;
        }
    }

    public function areaClass($parentId=0){
        $parentId = intval($parentId);
        if($parentId<0){
            $parentId = 0;
        }
        return $this->where("upid='{$parentId}'")->field('id,name')->order('id asc')->select();
    }

    public function getClassNameById($classId){
        $row = $this->where('id='.intval($classId))->field('name')->find();
        return empty($row['name'])?'根目录':$row['name'];
    }


    function updateFields($array){
        if(is_array($array)){
            foreach($array as $fieldName=>$updatelist){
                foreach($updatelist as $k=>$v){
                    $this->isUpdate(true)->where("`id`='{$k}'")->save([$fieldName=>$v]);
                }
            }
        }
    }

}