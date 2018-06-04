<?php
return [
    'mob' => [
        'title' => '是否开启手机网页版:',//表单的文字
        'type' => 'radio',         //表单的类型：text、textarea、checkbox、radio、select等
        'options' => [
            '1' => '开启',         //值=>文字
            '0' => '关闭',
        ],
        'value' => '1',             //表单的默认值
    ],
    'android' =>[
        'title' => '安卓版下载地址:',
        'type' => 'text',
    ],
    'ios' => [
        'title' => 'IOS版下载地址:',
        'type' => 'text',
    ]
];
                        