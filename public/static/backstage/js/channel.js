var active;

$(function(){
    layui.define([ 'form'], function(){
        var form = layui.form();
        active={
            binds:function(){
                active.changeSelect();
                form.render();
            },
            changeSelect:function(){
                form.on('select(nav-type)', function(data){
                    switch (data.value) {
                        case 'module':
                            $(data.elem).closest('li>div').children('div.module').show();
                            var text = $(data.elem).closest('li>div').children('div.module').children('select.modules').find("option:selected").text();
                            $(data.elem).closest('li>div').children('input.title').val(text);
                            $(data.elem).closest('li>div').children('input.url').hide();
                            break;
                        case 'custom':
                            $(data.elem).closest('li>div').children('div.module').hide();
                            $(data.elem).closest('li>div').children('input.url').show();
                            $(data.elem).closest('li>div').children('input.title').val('');
                            $(data.elem).closest('li>div').children('input.url').val('');
                            break;
                    }
                    form.render();
                });
                form.on('select(modules)', function(data){
                    var obj = $(data.elem);
                    var text = obj.find("option:selected").text();
                    var value = data.value;
                    obj.closest('li>div').children('input.title').val(text);
                    obj.closest('li>div').children('input.url').val(value);
                    form.render();
                });
            }
        };

        active.binds();

    });
    re_bind();
});

var re_bind = function () {

    fix_form();
    add_one();
    add_two();
    remove_li();
    add_child();
    add_flag();
    target_change();
    layui.define([ 'form'], function(){
        var form = layui.form();

        form.render();
    });
}

var target_change = function(){
    $('.target').change(function(){
        $(this).closest('.new-blank').find('.target_input').val($(this).is(':checked')?1:0);
    })
}

var fix_form = function () {
    $('.channel-ul').sortable({trigger: '.sort-handle-1', selector: 'li', dragCssClass: '',finish:function(){
        re_bind()
    }
    });
    $('.channel-ul .ul-2').sortable({trigger: '.sort-handle-2', selector: 'li', dragCssClass: '',finish:function(){
        re_bind()
    }});

}


var add_one = function () {
    $('.add-one').unbind('click');
    $('.add-one').click(function () {
        $(this).closest('.pLi').after($('#one-nav').html());
        re_bind();
    })
}

var add_two = function () {
    $('.add-two').unbind('click');
    $('.add-two').click(function () {
        $(this).closest('li').after($('#two-nav').html());
        re_bind();
    })
}


var remove_li = function () {
    $('.remove-li').unbind('click');
    $('.remove-li').click(function () {
        if($(this).parent().parent().hasClass('pLi')){
            if( $(this).parents('form').find('.pLi').length > 1){
                $(this).closest('li').remove()
                re_bind()
            }else{
                updateAlert('不能再减了~');
            }
        }else{
            $(this).closest('li').remove()
            re_bind()
        }


    })
}


var add_child = function () {
    $('.add-child').unbind('click');
    $('.add-child').click(function () {
        if ($(this).closest('li').find('.ul-2').length == 0) {
            $(this).closest('li').append('<div class="clearfix"></div><ul class="ul-2"  style="display: block;"></ul>')
        }
        $(this).closest('li').find('.ul-2').prepend($('#two-nav').html());
        re_bind()
    })
}


var add_flag = function () {
    $('.channel-ul .pLi').each(function (index, element) {
        $(this).attr('data-id', index);
        $(this).find('.sort').val($(this).attr('data-order'));
    })
    $('.ul-2 li').each(function (index, element) {
        $(this).find('.pid').val($(this).parents('.pLi').attr('data-id'));
        $(this).find('.sort').val($(this).attr('data-order'));
    })
}


