(function() {
    var loadingMoreIndicatorId = "bayesian_witch_loading_more";
    var displayedAllTitles = false;
    setTimeout(function() {
        if (!displayedAllTitles) {
            try {
                var loadingMoreIndicator = document.createElement("p");
                loadingMoreIndicator.innerHTML = "Please wait - checking for alternative titles..."
                loadingMoreIndicator.id = loadingMoreIndicatorId;
                document.getElementById("titlediv").appendChild(loadingMoreIndicator);
            } catch (err) {
            }
        }
    }, 10);

    bw_add_box_counter = 0;

    var hideLoadingIndicator = function() {
        displayedAllTitles = true;
        jQuery("#" + loadingMoreIndicatorId).remove(); //remove loading more indicator
    };

    window.bw_generate_title_box = function(title, tag){
        hideLoadingIndicator();

        title = title || '';
        tag = tag || get_random_tag();
        var html =
            '<div>' +
            '<input class="bw-admin-title" type="text" name="bw-titles['+tag+']" size="30" value="'+title+'" autocomplete="off">' +
            '<input type="button" class="bw-admin-title-delete button" value="Delete">' +
            '</div>';
        jQuery('#bw-add-button-wrap').before(html);
    };


    var bw_generate_add_button = function(){
        var html = '<div id="bw-add-button-wrap"><input type="button" class="bw-admin-title-add button" value="Add title variation">' +
            '<div class="clear"></div></div>';
        jQuery('#titlewrap').append(html);
        hideLoadingIndicator();
    };

    var get_random_tag = function(){
        return 'TitleVariation_'+'0000000000'.split('').map(function(){return 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'.charAt(Math.floor(62*Math.random()));}).join('');
    };

    jQuery(window).ready(function(){
        //bandit title
        jQuery(document).on('click', '.bw-admin-title-delete', function(){
            jQuery(this).parent().remove();
        });
        jQuery(document).on('click', '.bw-admin-title-add', function(){
            window.bw_generate_title_box();
        });

        bw_generate_add_button();
    });
})()
