/**
 * Created by Administrator on 2017/9/28.
 */
$(function(){
    $('input,area').placeholder();//修复ieplace holder
    if (is_login()) {
        bindMessageChecker();//绑定用户消息
    } else {
        bindLogin();//快捷登录
        bindRegister();
    }
});


/**
 * 绑定登录按钮
 * [data-login="quick_login"] 强制弹出快捷登录窗
 * [data-login="do_login"] 根据条件选择登录方式(弹窗/跳转登录页面)
 * @returns {boolean}
 */
function bindLogin() {
    if (!is_login()) {
        $('[data-login="quick_login"]').click(quickLogin);
        $('[data-login="do_login"]').click(doLogin);
    }
    return true;
}

/**
 * 强制弹出快捷登录窗
 */
var quickLogin = function () {//快捷登录
    if (!is_login()) {
        var myModalTrigger = new ModalTrigger({
            remote: U('Ucenter/Member/quickLogin'),
            title: "登录"
        });
        myModalTrigger.show();
    }
}

/**
 * 根据条件选择登录方式(弹窗/跳转登录页面)
 */
var doLogin = function () {//登录界面
    if (!is_login()) {
        if (OPEN_QUICK_LOGIN == 1) {
            var myModalTrigger = new ModalTrigger({
                remote: U('Ucenter/Member/quickLogin'),
                title: "登录"
            });
            myModalTrigger.show();
        } else {
            window.location.href = U('Ucenter/Member/login');
        }
    }
}
function bindRegister() {
    if (!is_login()) {
        $('[data-role="do_register"]').click(doRegister);
    }
}
var doRegister = function () {
    if (!is_login()) {
        if (ONLY_OPEN_REGISTER == "1") {
            var myModalTrigger = new ModalTrigger({
                remote: U('Ucenter/Member/inCode'),
                title: "邀请用户才能注册！"
            });
            myModalTrigger.show();
        } else {
            var url = $(this).attr('data-url');
            location.href = url;
        }
    }
}

/**
 * 绑定消息检查
 */
function bindMessageChecker() {
    $hint_count = $('#nav_hint_count');
    $nav_bandage_count = $('#nav_bandage_count');
    if (Config.GET_INFORMATION) {
        setInterval(function () {
            checkMessage();
        }, Config.GET_INFORMATION_INTERNAL);
    }

}

function Lang(key, obj) {
    if('undefined' == typeof(LANG[key])) {
        return key;
    }
    if('object' != typeof(obj)) {
        return LANG[key];
    } else {
        var r = LANG[key];
        for(var i in obj) {
            r = r.replace("{"+i+"}", obj[i]);
        }
        return r;
    }
};

var message_session={
    init_message:function(){
        var $tag=$('#message-box #message-content-box');
        $('[data-role="open-message-box"]').unbind();
        $('[data-role="open-message-box"]').click(function () {
            var $session_list_tag=$tag.find('.content-list .session-list');
            $tag.find('.content-list .message-list').html('');
            $session_list_tag.html('');
            OS_Loading.loading($session_list_tag,'loading5','#19bca1');
            $.post(U('Ucenter/Message/messageSession'),{},function(html){
                OS_Loading.remove($session_list_tag);
                $session_list_tag.html(html);
                $('[data-role="open-message-list"]').first().click();
            });
        });
    },
    init_message_session:function(){
        var $tag=$('#message-box #message-content-box');
        $('[data-role="open-message-list"]').unbind();
        $('[data-role="open-message-list"]').click(function () {
            $tag.find('.session-list>li').removeClass('current');
            var $this=$(this);
            $this.parent().addClass('current');
            var session = $(this).attr('data-type');
            if(!$('#message_block_' + session).is(":visible")){
                $tag.find('.message-list').find('.list-block').hide();
                OS_Loading.loading($tag.find('.message-list'),'loading1','#19bca1');
                if ($('#message_block_' + session).length == 0) {
                    $.post(U('Ucenter/Message/messageDetail'),{message_session:session},function(html){
                        $tag.find('.message-list').append(html);
                        $this.find('.unread-num').hide();
                        $this.find('.unread-tip').hide();
                        $tag.find('.message-list').find('.list-block').hide();
                        OS_Loading.remove($tag.find('.message-list'));
                        $('#message_block_' + session).show();
                        checkMessage();//检查消息
                    });
                }else{
                    $tag.find('.message-list').find('.list-block').hide();
                    OS_Loading.remove($tag.find('.message-list'));
                    $('#message_block_' + session).show();
                }
            }
        });
    },
    init_message_list:function(){
        $('[data-role="load-more"]').unbind();
        $('[data-role="load-more"]').click(function(){
            var now_count=parseInt($(this).attr('data-already')),
                now_session=$(this).attr('data-session');
            var $tag=$('#message_block_'+now_session);
            $tag.find('.load-more .do-button').html('');
            OS_Loading.loading($tag.find('.load-more .do-button'),'loading1','#19bca1');
            var num=5;
            $.post(U('Ucenter/Message/loadMore'),{start:now_count,message_session:now_session,num:num},function(html){
                OS_Loading.remove($tag.find('.load-more .do-button'));
                if(html.length){
                    $tag.find('.load-more-block').append(html);
                    $tag.find('.load-more .do-button').attr('data-already',now_count+num);
                    $tag.find('.load-more .do-button').html('查看更多...');
                }else{
                    $tag.find('.load-more .do-button').html('没有更多了');
                    $tag.find('.load-more .do-button').attr('disabled','disabled');
                    $tag.find('.load-more .do-button').unbind();
                }
            },'json');
        });
    }
}

/**
 * 消息中心提示有新的消息
 * @param text
 * @param title
 */
function tip_message(text, title) {
    toast.info(text);
}

var flash_title={
    step:0,
    id:0,
    decument_title:document.title,
    flash:function(){
        flash_title.step++
        if (flash_title.step>40) {
            flash_title.step=0;
        }else if(flash_title.step<12){
            if (flash_title.step%2==1) {document.title='【新消息】'+flash_title.decument_title}
            if (flash_title.step%2==0) {document.title='【　　　】'+flash_title.decument_title}
        }
        if(flash_title.id==0){
            flash_title.id=setInterval("flash_title.flash()",380);
        }
    },
    close:function(){
        clearInterval(flash_title.id);
        document.title=flash_title.decument_title;
        return true;
    }
}

/**
 * 检查是否有新的消息
 */
function checkMessage() {
    $.get(U('Ucenter/Public/getInformation'), {}, function (msg) {
        if (msg.messages) {
            var message = msg['messages'];
            for (var index in msg.messages) {
                if(message[index]['content']['untoastr']===undefined||message[index]['content']['untoastr']!=1){
                    tip_message(message[index]['content']['content'] + '<div style="text-align: right"> ' + message[index]['ctime'] + '</div>', message[index]['content']['title']);
                }
            }
        }

        //$('[data-role="now-message-num"]').html(msg.message_count);
        if(msg.message_count==0){
            flash_title.close();
            $('[data-role="now-message-num"]').hide();
        }else{
            flash_title.flash();
            $('[data-role="now-message-num"]').show();
        }
        if (msg.new_talks) {
            //发现有新的聊天
            $.each(msg.new_talks, function (index, talk) {
                    talker.prepend_session(talk.talk);
                }
            );
        }


        function message_box_showing(talk_message) {
            return ($('#chat_id').val() == talk_message.talk_id) && ($('#chat_box').is(":visible"));
        }

        if (msg.new_talk_messages) {
            //发现有新的聊天
            $.each(msg.new_talk_messages, function (index, talk_message) {
                    if (message_box_showing(talk_message)) {
                        talker.append_message(talker.fetch_message_tpl(talk_message, MID));
                        //发起一个获取聊天的请求来将该聊天设为已读
                        $.get(U('Ucenter/Session/getSession'), {id: talk_message.talk_id}, function () {
                        }, 'json');
                    }
                    else {
                        talker.set_session_unread(talk_message.talk_id);
                    }
                }
            );
        }
        return true;
    }, 'json');

}