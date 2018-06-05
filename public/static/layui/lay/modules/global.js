var active;
layui.define(['layer', 'code', 'form', 'element', 'util','laydate'], function(exports){
  var $ = layui.jquery
  ,layer = layui.layer
  ,form = layui.form
  ,util = layui.util
  ,laydate = layui.laydate
  ,device = layui.device;

  layui.selMeltiple($);

  active = {
    openLayer:function(url,title,width,height,isfull){
        var index =  layer.open({
              type: 2,
              title: title,
              area: [width, height],
              content: [url, 'no']
        });
      if(isfull){
        layer.full(index);
      }
    }
  };

  form.on('select(group)', function(data){
    location.href = data.value;
  });

  //友情链接单选按钮改变事件
  form.on('radio(type)', function(data){
    if(data.value==1){
      $("#img_area").show();
    }else{
      $("#img_area").hide();
    }
  });

  form.on('select(dorganiz)',function(data) {

    var text = data.elem.options[data.elem.selectedIndex].text
    var ids = $("#d-organizs").val();
    texts += text + ',';
    dt[data.value] = text
    var _texts = '';
    $(dt).each(function(i, item) {
      if (item) {
        _texts += '<a class="ui label transition visible" style="display: inline-block !important;line-height: 1.65em; padding: .1833em .833em;">' + item + '<i class="layui-icon" style="font-size: 1em; color: #767676;cursor: pointer; margin-left: 1em;" data-val="' + i + '" data-t="' + item + '" onclick="del_icon(this)">ဆ</i></a>';
      }
    });

    ids += data.value + ',';
    $('#d-organizs').val(ids);
    $(data.elem.parentElement)
        .find('.layui-select-title')
        .html('<div class="layui-input layui-unselect">' + _texts + '<i class="layui-edge"></i></div>');
    //选中移除
    $(data.elem.parentElement)
        .find(
        '.layui-anim-upbit li[lay-value="' + data.value + '"]')
        .css("display", "none");
  });

  function del_icon(dom) {
    var v = $(dom).attr('data-val');
    var t = $(dom).attr('data-t');
    $(dom).parents().find('#dorganizs .layui-anim-upbit li[lay-value="'
        + v
        + '"]').css("display", "block");
    var dom1 = $(dom).parent().parent();
    $(dom).parent().remove();
    if(!dom1.html()){
      dom1.html('<input type="text" placeholder="请选择" value="" readonly="" class="layui-input layui-unselect"><i class="layui-edge"></i>');
    }
    dt[v] = undefined ;
    var ids = $("#d-organizs").val().replace(v+',','');

    $("#d-organizs").val(ids);

  }

  form.on('checkbox(rules_all)', function(data){
    $(data.elem).closest('dl').find('dd').find('input').prop('checked',data.elem.checked);
    form.render('checkbox');
  });

  form.on('checkbox(rules_row)', function(data){
    $(data.elem).closest('.rule_check').find('.child_row').find('input').prop('checked',data.elem.checked);
    form.render('checkbox');
  });

  form.on('checkbox(allChoose)', function(data){
    var child = $(data.elem).parents('table').find('tbody input[type="checkbox"]');
    child.each(function(index, item){
      item.checked = data.elem.checked;
    });
    form.render('checkbox');
  });

  form.on('checkbox(oneplusCheckbox)', function(data){
    var fieldName = $(data.elem).attr("data-field-name");
    var checked = $('.oneplus-checkbox[data-field-name=' + fieldName + ']:checked');
    var result = [];
    for (var i = 0; i < checked.length; i++) {
      var checkbox = $(checked.get(i));
      result.push(checkbox.attr('value'));
    }
    result = implode(',', result);
    $('.oneplus-checkbox-hidden[data-field-name=' + fieldName + ']').val(result);
    form.render('checkbox');
  });

  var element = layui.element; //导航的hover效果、二级菜单等功能，需要依赖element模块
  //阻止IE7以下访问
  if(device.ie && device.ie < 8){
    layer.alert('Layui最低支持ie8，您当前使用的是古老的 IE'+ device.ie + '，你丫的肯定不是程序猿！');
  }

  //手机设备的简单适配
  var treeMobile = $('.site-tree-mobile')
  ,shadeMobile = $('.site-mobile-shade')

  treeMobile.on('click', function(){
    $('body').addClass('site-mobile');
  });

  shadeMobile.on('click', function(){
    $('body').removeClass('site-mobile');
  });

  exports('global', {});
});

function implode(x, list) {
  var result = "";
  for (var i = 0; i < list.length; i++) {
    if (result == "") {
      result += list[i];
    } else {
      result += ',' + list[i];
    }
  }
  return result;
}