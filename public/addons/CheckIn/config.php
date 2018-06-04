<?php

return array_merge(
    [
        'action'=>[
            'title'=>'签到绑定行为：',
            'type'=>'checkbox',
            'options'=>get_option(),
        ]
    ],
    get_option2()
);


function get_option(){
    $actionModel = new \app\common\model\ActionModel();
    $opt = $actionModel->getActionOpt();
    $return = ['no_action'=>'不绑定'];
    foreach($opt as $v){
        $return[$v['name']] = $v['title'];
    }
    return $return;

}

function get_option2(){
    $type= db('ucenter_score_type');
    $opt=$type->select();
    foreach($opt as $v)
    {
        $arr[ 'score'.$v['id']] =
           [
               'title'=>$v['title'],
                'type'=>'text',
                'value'=>0
           ];
    }
    return $arr;
}