jQuery(window).ready(function(){
  jQuery('input#title').change(function(){
    var now = new Date();
    var day = now.getDate();
    if(day < 10){
      day = '0'+day;
    }
    var months = new Array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
    var month = months[now.getMonth()];
    var year = now.getFullYear();
    var date = day+'_'+month+'_'+year;
    var title = jQuery(this).val().replace(/ /g, '_').replace(/[^a-zA-Z0-9_]/g,'');
    var post_id = jQuery('#post_ID').val();
    if(title.length > 0){
      var tag = 'Bandit_'+title+'_'+date+'_p'+post_id;
    } else {
      var tag = 'Bandit_'+date+'_p'+post_id;
    }
    jQuery('#bw-bandit-tag').text(tag);
    jQuery('#bw-bandit-tag-hidden').val(tag);
  });
});