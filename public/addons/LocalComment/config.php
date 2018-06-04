<?php

return [
    'can_guest_comment' => [
        'title'=>'是否允许游客评论',
        'type'=>'radio',
        'options'=>[
            '0'=>'不允许',
            '1'=>'允许'
        ],
        'value'=>'0',
        'tip'=>'开启后游客可以评论，但不允许游客回复评论，请谨慎开启'
    ]
];
