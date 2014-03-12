bw_add_box_counter = 0;

jQuery(window).ready(function(){
  //bandit title
  jQuery(document).on('click', '.bw-admin-title-delete', function(){
    jQuery(this).parent().remove();
  });
  jQuery(document).on('click', '.bw-admin-title-add', function(){
    bw_generate_title_box();
  });

  bw_generate_add_button();
});

function bw_generate_title_box(title, tag){
  title = title || '';
  tag = tag || get_random_tag();
  var html =
    '<div>' +
    '<input class="bw-admin-title" type="text" name="bw-titles['+tag+']" size="30" value="'+title+'" autocomplete="off">' +
    '<input type="button" class="bw-admin-title-delete button" value="Delete">' +
    '</div>';
  jQuery('#bw-add-button-wrap').before(html);
}


function bw_generate_add_button(){
  var html = '<div id="bw-add-button-wrap"><input type="button" class="bw-admin-title-add button" value="Add title variation">' +
    '<div class="clear"></div></div>';
  jQuery('#titlewrap').append(html);
}

function get_random_tag(){
  return 'TitleVariation_'+'00000000000000000000'.split('').map(function(){return 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'.charAt(Math.floor(62*Math.random()));}).join('');
}