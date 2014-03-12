<?php
  /*
  Plugin Name: BayesianWitch
  Version: 0.3
  Description: -
  Author: Vladymir Goryachev
  */

  //todo: remove it
  // Client ID: bc232af6-8609-4e5f-a24a-7231ff7e14e5
  // Client Secret: 2d35ffd0-f072-4644-bcf9-d94f91e51734
//ini_set('display_errors',1);
//ini_set('display_startup_errors',1);
//error_reporting(-1);

require_once 'remote.php';

class BayesianWitch{
  private $client;
  private $secret;
  private $domain;
  private $api_url;
  private $api_port;
  private $api_full_url;
  private $site_uuid;
  private $api_key_needed_message;
  private $configuration_needed_message;
  private $shortcode;
  private $rss_cache = array();

  public function __construct(){
    $this->shortcode = '{bandit}';
    $this->api_key_needed_message = "Please enter domain, client ID and secret ID. These can be found by creating an account on the <a href=\"http://www.bayesianwitch.com?source=wordpress\">BayesianWitch.com</a> site. Once an account is created, you create a site, then click the \"Reset and view API keys\" button.";
    $this->configuration_needed_message = '<a href="'.site_url().'/wp-admin/plugins.php?page=BayesianWitch">Click here</a> to configure BayesianWitch plugin';

    $this->api_recommend_url = 'http://recommend.bayesianwitch.com';
    $this->api_url = 'http://api.bayesianwitch.com';
    $this->api_port = '8090';
    $this->api_full_url = $this->api_url.':'.$this->api_port;

    add_action('admin_menu', array($this, 'register_submenu'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_css'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_post_edit_js'));
    add_action('wp_enqueue_scripts', array($this, 'enqueue_all_pages_css'));
    add_filter('content_edit_pre', array($this, 'filter_bandit_shortcode'), 10, 2);

    if($this->is_configured()){ //main functions don't work without credentials
      add_action('parse_query', array($this, 'init_rss_hooks'));
      add_action('add_meta_boxes_post', array($this, 'add_bandit_meta_box'));
      add_action('wp_head', array($this, 'add_tracking_js'));
      add_action('wp_head', array($this, 'add_incoming_nodisplay_css'));
      add_action('wp_footer', array($this, 'add_title_bandit_incoming_js'));
      add_filter('wp_insert_post_data', array($this, 'save_metadata'), '99', 2);
      add_action('wp_insert_post_data', array($this, 'save_bandit_title'));
      add_filter('the_title', array($this, 'filter_title_bandit'), 99, 2);
      add_action('edit_form_after_title', array($this, 'add_title_box_js'));
      add_action('the_content', array($this, 'add_title_bandit_tracking_js'));
    } else {
      add_action('admin_notices', array($this, 'render_admin_error'));
    }
  }

  public function init_rss_hooks(){
    if(is_feed()){
      add_filter('the_content_feed', array($this, 'filter_bandit_shortcode_rss'));
      remove_filter('get_the_excerpt', 'wp_trim_excerpt');
      add_filter('get_the_excerpt', array($this, 'filter_bandit_shortcode_rss'));
      add_filter('the_title_rss', array($this, 'filter_title_bandit_rss'));
    }
  }

  public function get_domain(){
    if (!isset($this->domain)) {
      $this->domain = get_option('bw_domain');
    }
    return $this->domain;
  }

  public function get_client(){
    if (!isset($this->client)) {
      $this->client = get_option('bw_client');
    }
    return $this->client;
  }

  public function get_secret(){
    if (!isset($this->secret)) {
      $this->secret = get_option('bw_secret');
    }
    return $this->secret;
  }

  private function set_domain($x){
    $cleaned_domain = trim($x);
    $this->domain = $cleaned_domain;
    update_option('bw_domain', $cleaned_domain);
    $this->flush_site_uuid();
  }

  private function set_client($x){
    $cleaned_client = trim($x);
    $this->client = $cleaned_client;
    update_option('bw_client', $cleaned_client);
    $this->flush_site_uuid();
  }

  private function set_secret($x){
    $cleaned_secret = trim($x);
    $this->secret = $cleaned_secret;
    update_option('bw_secret', $cleaned_secret);
    $this->flush_site_uuid();
  }


  private function get_auth_url_string(){
    return http_build_query(array('client' => $this->get_client(), 'secret' => $this->get_secret()));
  }

  private function make_api_url($path){
    $str = $this->api_full_url.$path;
    if(preg_match('/\?/', $str)){
      $del = '&';
    } else {
      $del = '?';
    }
    return $str.$del.$this->get_auth_url_string();
  }

  //returns an object in case of error or a string with uuid on success
  private function get_site_uuid(){
    if(!isset($this->site_uuid)){
      if(!$this->site_uuid = get_option('bw_site_uuid')){
        $url = $this->make_api_url('/sites/'.$this->get_domain().'/');
        $response = Remote::get($url);
        if($response->get_error()){
          return $response;
        }
        $json = json_decode($response->body);
        $this->site_uuid = $json->uuid;
        update_option('bw_site_uuid', $this->site_uuid);
      }
    }
    return $this->site_uuid;
  }

  private function flush_site_uuid(){
    $this->site_uuid = '';
    update_option('bw_site_uuid', '');
  }

  private function get_bandit($bandit_tag){
    $uuid_response = $this->get_site_uuid();
    if(is_object($uuid_response)){
      return $uuid_response;
    }
    $url = $this->make_api_url('/bandits/'.$uuid_response.'/'.$bandit_tag);
    $response = Remote::get($url);
    return $response;
  }

  private function get_bandit_rss($bandit_uuid){
    $url = $this->api_recommend_url.'/bandit/'.$bandit_uuid.'?usage=rss&fingerprint=false';
    $response = Remote::get($url);
    return $response;
  }

  private function get_js_widget($bandit_uuid){
    $url = $this->api_recommend_url.'/js_widget/'.$bandit_uuid;
    $response = Remote::get($url);
    return $response;
  }

  private function get_api_error($response){
    if(!is_object($response)) return false;
    if($response->curl_error) return 'CURL Error: '.$response->curl_error;
    if($response->http_code >= 400){
      $json = json_decode($response->body);
      if(isset($json->humanReadable)){
        return 'BayesianWitch Error: '.$json->humanReadable;
      }
      if(isset($json->message)){
        return 'BayesianWitch Error: '.$json->message;
      }
      return 'BayesianWitch Error: HTTP '.$response->http_code;
    }
    return false;
  }

  private function get_credentials_and_domain_errors(){
    $result = array();
    $url_credentials = $this->make_api_url('/client/check_credentials');
    $response = Remote::get($url_credentials);
    if($error = $response->get_error()){
      $result['credentials'] = $error;
    }
    $url_domain = $this->make_api_url('/sites/'.$this->get_domain());
    $response = Remote::get($url_domain);
    if($error = $response->get_error()){
      $result['domain'] = $error;
    }
    return $result;
  }

  private function send_bandit_update($data, $bandit_tag, $additional_params = ''){
    $uuid_response = $this->get_site_uuid();
    if(is_object($uuid_response)){
      return $uuid_response;
    }
    $url = $this->make_api_url('/bandits/'.$uuid_response.'/'.$bandit_tag.$additional_params);
    $response = Remote::put_json($url, $data);
    return $response;
  }

  private function validate_tag($tag){
    return preg_match('/^[a-zA-Z0-9_]+$/', $tag);
  }

  private function is_configured(){
    return ($this->get_domain() && $this->get_client() && $this->get_secret());
  }

  public function register_submenu(){
    add_submenu_page('plugins.php', 'BayesianWitch plugin settings', 'BayesianWitch', 'manage_options', 'BayesianWitch', array($this, 'render_menu'));
  }

  public function enqueue_post_edit_js($hook){
    global $post;

    if($hook == 'post-new.php' || $hook == 'post.php'){
      $bandit_tag = get_post_meta($post->ID, '_bandit_tag');
      if(empty($bandit_tag)){
        wp_register_script('bw_bandit_tag_js', plugins_url('js/bandit_tag.js', __FILE__) );
        wp_enqueue_script('bw_bandit_tag_js');
      }
      wp_register_script('bw_post_edit_main_js', plugins_url('js/post_edit_main.js', __FILE__) );
      wp_enqueue_script('bw_post_edit_main_js');
    }
  }

  public function enqueue_all_pages_css(){
    wp_register_style('bw_wpautop_fix', plugins_url('css/all_pages.css', __FILE__) );
    wp_enqueue_style('bw_wpautop_fix');
  }

  public function enqueue_admin_css($hook){
    wp_register_style('bw_stylesheet', plugins_url('css/admin.css', __FILE__) );
    wp_enqueue_style('bw_stylesheet');
  }

  public function render_admin_error($message){
    echo '<div class="error"><p>'.$this->configuration_needed_message.'</p></div>';
  }

  public function render_menu(){

    if(isset($_POST['bw-domain'])){
      $this->set_domain($_POST['bw-domain']);
    }
    if(isset($_POST['bw-client'])){
      $this->set_client($_POST['bw-client']);
    }
    if(isset($_POST['bw-secret'])){
      $this->set_secret($_POST['bw-secret']);
    }

    echo '<h2>BayesianWitch plugin settings</h2>';
    $credentials_classes = 'input';
    $domain_classes = 'input';
    if($this->is_configured()){
      $errors = $this->get_credentials_and_domain_errors();
      if(!empty($errors)){
        if(isset($errors['credentials'])){
          $credentials_classes .= ' error';
        }
        if(isset($errors['domain'])){
          $domain_classes .= ' error';
        }
        if(isset($errors['credentials'])){
          echo '<div class="bw-error-box">'.$errors['credentials'].'</div>';
        }else{
          echo '<div class="bw-error-box">'.$errors['domain'].'</div>';
        }
      }
    }else{
      echo '<div class="bw-error-box">'.$this->api_key_needed_message.'</div>';
    }
    echo '<form action="" method="post">';
    echo '<div id="bayesianwitch" class="postbox bw-admin-config"><div class="inside">';
    echo '<h4>Domain:</h4>';
    echo '<input class="'.$domain_classes.'" type="text" name="bw-domain" value="'.$this->get_domain().'">';
    echo '<div class="clear"></div>';
    echo '<h4>Client ID:</h4>';
    echo '<input class="'.$credentials_classes.'" type="text" name="bw-client" value="'.$this->get_client().'">';
    echo '<div class="clear"></div>';
    echo '<h4>Secret Key:</h4>';
    echo '<input class="'.$credentials_classes.'" type="text" name="bw-secret" value="'.$this->get_secret().'">';
    echo '<div class="clear"></div>';
    echo '<input type="submit" value="Save" class="bw-button">';
    echo '<div class="clear"></div>';
    echo '</div></div>';
    echo '<p>The client ID and secret ID can be found on <a href=\"http://www.bayesianwitch.com?source=wordpress_plugin\" target=\"_blank\">BayesianWitch.com</a>. You need to create an account and then create a site, where the domain of the site is the domain of your blog (e.g. www.myblog.com). The enter "www.myblog.com" (or whatever your real domain is) for the Domain field.</p>';
    echo '<p>To find the Client ID and Secret Key, click the "Reset and view API keys" button on the BayesianWitch <a href=\"http://www.bayesianwitch.com/accounts/profile?source=wordpress_plugin\" target=\"_blank\">profile page</a>.</p>';
    echo '</form>';
  }

  public function filter_bandit_shortcode($content, $post_id){
    $result = preg_replace('/<!--bandit-start-->.*?<!--bandit-end-->/is', $this->shortcode, $content);
    return $result;
  }

  public function filter_bandit_shortcode_rss($content){
    global $post;
    $is_rss = false;
    $bandit_retrieved = false;
    if(!$content){
      $is_rss = true;
      $content = $post->post_content;
    }
    if(isset($this->rss_cache[$post->ID])){ //without it we'd have to make every API call for both the excerpt and the full content
      $result = $this->rss_cache[$post->ID];
      $bandit_retrieved = true;
    } else {
      $search = '/<!--bandit-start-->.*?<!--bandit-end-->/is';
      if(preg_match($search, $content)){
        $bandit_tag = get_post_meta($post->ID, '_bandit_tag');
        $response = $this->get_bandit($bandit_tag[0]);
        if(!$response->get_error()){
          $bandit = json_decode($response->body);
          $rss_response = $this->get_bandit_rss($bandit->bandit->uuid);
          if(!$rss_response->get_error()){
            $result = preg_replace($search, $rss_response->body, $content);
            $bandit_retrieved = true;
            $this->rss_cache[$post->ID] = $result;
          }
        }
      }
    }
    if(!$bandit_retrieved){
      $result = preg_replace($search, '', $content);
    }
    if($is_rss){
      $result = wp_trim_words($result);
    }
    return $result;
  }

  public function filter_title_bandit_rss($name){
    global $post;
    $bandit_title_uuid = get_post_meta($post->ID, '_bandit_title_uuid');
    if(!empty($bandit_title_uuid)){
      $rss_response = $this->get_bandit_rss($bandit_title_uuid[0]);
      if(!$rss_response->get_error()){
        return $rss_response->body;
      }
    }
    return $name;
  }

  public function filter_title_bandit($name, $id){
    $bandit_title_uuid = get_post_meta($id, '_bandit_title_uuid');
    if(!empty($bandit_title_uuid)){
      $bandit_title_uuid = $bandit_title_uuid[0];
      return '<span class="bw-title-nodisplay" bw_title_bandit="'.$bandit_title_uuid.'">'.$name.'</span>';
    }
    return $name;
  }

  public function add_title_bandit_tracking_js($text){
    global $post;
    if(!is_single()) return $text;
    $data = get_transient('bw_title_bandit_tracking_js_'.$post->ID);
    if($data === false){
      $bandit_title_uuid = get_post_meta($post->ID, '_bandit_title_uuid');
      if(!empty($bandit_title_uuid)){
        $response = Remote::get($this->api_recommend_url.'/title/'.$bandit_title_uuid[0].'/javascript');
        if(!$response->get_error()){
          $data = $response->body;
          set_transient('bw_title_bandit_tracking_js_'.$post->ID, $data);
        }
      }
    }
    if($data){
      echo '<script type="application/javascript">'.$data.'</script>';
    }
    return $text;
  }

  public function add_title_bandit_incoming_js(){
    $data = get_transient('bw_title_bandit_incoming_js');
    if($data === false){
      $response = Remote::get($this->api_recommend_url.'/title/incoming/javascript');
      if(!$response->get_error()){
        $data = $response->body;
        set_transient('bw_title_bandit_incoming_js', $data, 12 * HOUR_IN_SECONDS);
      }
    }
    if($data){
      echo '<script type="application/javascript">'.$data.'</script>';
    }
  }

  public function add_incoming_nodisplay_css(){
    echo '<style type="text/css">
            .bw-title-nodisplay {
              visibility: hidden;
            }
          </style>';
  }

  public function add_tracking_js(){
    $js = get_option('bw_tracking_js');
    if(!$js){
      $url = $this->api_full_url.'/sites/'.$this->get_domain().'/javascript?'.$this->get_auth_url_string();
      $response = Remote::get($url);
      if(!$response->get_error()){
        $js = $response->body;
        update_option('bw_tracking_js', $js);
      }
    }
    echo '<script type="application/javascript">'.$js.'</script>';
  }

  public function add_bandit_meta_box(){
    add_meta_box(
      'bayesianwitch',
      'BayesianWitch',
      array($this, 'render_bandit_meta_box'),
      'post');
  }

  public function render_bandit_meta_box($bandit_tag){
    global $post;

    echo '<input type="hidden" name="bw-ready" id="bw-ready" value="0">';
    if($post_error_message = get_option('bw_post_error')){
      echo '<div class="bw-error-box">Bandit is not saved. '.$post_error_message.'</div>';
      update_option('bw_post_error', '');
    }

    $bandit_tag = get_post_meta($post->ID, '_bandit_tag');
    if(empty($bandit_tag)){
      $bandit_tag = '';
    } else {
      $bandit_tag = $bandit_tag[0];
    }

    if($bandit_tag){
      $response = $this->get_bandit($bandit_tag);
      if($error = $response->get_error()){
        if(isset($post_error_message) && $post_error_message) return; // previous error message was displayed, we don't need 2 similar errors to be shown at the same time
        echo '<div class="bw-error-box">'.$error.'</div>';
        return;
      }
      $bandit = json_decode($response->body);
    }

    if(get_option('bw_validation_error')){
      echo '<div class="bw-error-box">Validation error. BANDIT_TAG, TAG1 and TAG2 must be alphanumeric strings with no spaces. Underscores are permitted.</div>';
      update_option('bw_validation_error', '');
    }

    echo '<p>To embed a bandit put "'.$this->shortcode.'" somewhere in the post body.</p>';
    echo '<h4>BANDIT_TAG</h4>';
    if(!$bandit_tag){
      if($post->post_title){
        $title = str_replace(' ', '_', $post->post_title);
        $title = preg_replace('/[^a-zA-Z0-9_]/', '', $title);
        $bandit_tag_default = 'Bandit_'.$title.'_'.date('d_F_Y').'_p'.$post->ID;
      }else{
        $bandit_tag_default = 'Bandit_'.date('d_F_Y').'_p'.$post->ID;
      }
      echo '<input type="hidden" name="bw-bandit-tag" id="bw-bandit-tag-hidden" value="'.$bandit_tag_default.'">';
      echo '<span class="input" id="bw-bandit-tag">'.$bandit_tag_default.'</span>';
    } else {
      echo '<span class="input" id="bw-bandit-tag">'.$bandit_tag.'</span>';
    }
    echo '<div class="clear"></div>';

    echo '<h4>TAG1</h4>';
    if(!$bandit_tag){
      echo '<input class="input" type="text" name="bw-tag1" value="Version1">';
    } else {
      echo '<span class="input">'.$bandit->variations[0]->tag.'</span>';
      echo '<input type="hidden" name="bw-tag1" value="'.$bandit->variations[0]->tag.'">';
    }
    echo '<div class="clear"></div>';

    echo '<h4 class="bandit-body">BODY1</h4>';
    if(!$bandit_tag){
      wp_editor('', 'bw-body1', array('textarea_rows' => 4));
    } else {
      wp_editor(stripslashes($bandit->variations[0]->contentAndType->content), 'bw-body1', array('textarea_rows' => 4));
    }
    echo '<div class="clear"></div>';

    echo '<h4>TAG2</h4>';
    if(!$bandit_tag){
      echo '<input class="input" type="text" name="bw-tag2" value="Version2">';
    } else {
      echo '<span class="input">'.$bandit->variations[1]->tag.'</span>';
      echo '<input type="hidden" name="bw-tag2" value="'.$bandit->variations[1]->tag.'">';
    }

    echo '<div class="clear"></div>';

    echo '<h4 class="bandit-body">BODY2</h4>';
    if(!$bandit_tag){
      wp_editor('', 'bw-body2', array('textarea_rows' => 4));
    } else {
      wp_editor(stripslashes($bandit->variations[1]->contentAndType->content), 'bw-body2', array('textarea_rows' => 4));
    }
    echo '<div class="clear"></div>';

  }

  public function add_title_box_js(){
    global $post;
    $bandit_title_tag = get_post_meta($post->ID, '_bandit_title_tag');
    if(!empty($bandit_title_tag)){
      $response = $this->get_bandit($bandit_title_tag[0]);
      if(!$response->get_error()){
        $bandit = json_decode($response->body);
        echo '<script type="application/javascript">';
        echo 'jQuery(document).ready(function(){';
        foreach($bandit->variations as $variation){
          if($variation->isActive == true && $variation->tag != 'MainTitle'){
            echo 'bw_generate_title_box("'.wp_slash(htmlspecialchars($variation->contentAndType->content)).'", "'.wp_slash(htmlspecialchars($variation->tag)).'");';
          }
        }
        echo '});';
        echo '</script>';
      }
    }
  }

  public function save_bandit_title($post_san){
    global $post;
    if(!$post) return $post_san;
    $titles = array();
    if(isset($_POST['bw-titles'])){
      foreach($_POST['bw-titles'] as $tag=>$title){
        //stripslashes is used here to remove escaping added by WP's magic quotes
        $titles[stripslashes($tag)] = stripslashes($title);
      }
    }
    if(!empty($titles)){
      $bandit_title_tag = get_post_meta($post->ID, '_bandit_title_tag');
      if(!$bandit_title_tag || empty($bandit_title_tag)){
        $bandit_title_tag = 'BanditTitle_'.date('d_F_Y').'_p'.$post->ID;
        update_post_meta($post->ID, '_bandit_title_tag', $bandit_title_tag);
      } else {
        $bandit_title_tag = $bandit_title_tag[0];
      }
      $json = array();
      foreach($titles as $tag=>$title){
        $json[] = array(
          'tag' => $tag,
          'isActive' => true,
          'contentAndType' => array(
            'content_type' => 'text/html',
            'content' => $title
          )
        );
      }
      $json[] = array(
        'tag' => 'MainTitle',
        'isActive' => true,
        'contentAndType' => array(
          'content_type' => 'text/html',
          'content' => stripslashes($_POST['post_title'])
        )
      );
      $json = json_encode($json, JSON_UNESCAPED_SLASHES);

      $response = $this->send_bandit_update($json, $bandit_title_tag, '?kind=title&url='.urlencode(get_permalink($post->ID)));
      if(!$response->get_error()){
        $bandit = json_decode($response->body);
        update_post_meta($post->ID, '_bandit_title_uuid', $bandit->bandit->uuid);
        delete_transient('bw_title_bandit_tracking_js_'.$post->ID);
      }
      return $post_san;
      #todo: display error
//      if(!$this->get_api_error($response)){
//
//      }
    }
  }

  public function save_metadata($post_san, $post_raw){
    global $post;
    $post_id =  $post_raw['ID'];
    if(!$post) return $post_san; //return if it's an auto draft or revision
    if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return $post_san;
    if(defined('DOING_AJAX') && DOING_AJAX) return $post_san;
    if(!current_user_can('edit_post', $post_id)) return $post_san;
    if(false !== wp_is_post_revision($post_id)) return $post_san;

    $bandit_tag_meta = get_post_meta($post->ID, '_bandit_tag');
    if($bandit_tag_meta){ // if already exists
      $bandit_tag = $bandit_tag_meta[0];
    }else{
      $bandit_tag = $_POST['bw-bandit-tag'];
    }
    $bandit_tag1 = $_POST['bw-tag1'];
    $bandit_tag2 = $_POST['bw-tag2'];
    $bandit_body1 = stripslashes($_POST['bw-body1']);
    $bandit_body2 = stripslashes($_POST['bw-body2']);

    $update = true;
    if(!$bandit_body1 || !$bandit_body2){
      $update = false; //don't update bandit if one of bandit bodies is empty
    }
    if($update && (!$this->validate_tag($bandit_tag) || !$this->validate_tag($bandit_tag1) || !$this->validate_tag($bandit_tag2))){
      update_option('bw_validation_error', '1');
      return $post_san;
    }

    if($bandit_tag){
      $json = array();
      $json[] = array('tag' => $bandit_tag1, 'isActive' => true, 'contentAndType' => array('content_type' => 'text/html', 'content' => $bandit_body1));
      $json[] = array('tag' => $bandit_tag2, 'isActive' => true, 'contentAndType' => array('content_type' => 'text/html', 'content' => $bandit_body2));
      $json = json_encode($json, JSON_UNESCAPED_SLASHES);
      if($update){
        $response = $this->send_bandit_update($json, $bandit_tag);
        if($error = $response->get_error()){
          update_option('bw_post_error', $error);
          return $post_san;
        }
        $bandit = json_decode($response->body);
        if(!$bandit_tag_meta){
          update_post_meta($post->ID, '_bandit_tag', $bandit_tag);
        }
      }else{
        $response = $this->get_bandit($bandit_tag);
        $bandit = json_decode($response->body);
      }

      if($bandit){
        $post_content = $_POST['post_content'];
        if($update && strpos($post_content, $this->shortcode)){
          $response = $this->get_js_widget($bandit->bandit->uuid);
          if($error = $response->get_error()){
            update_option('bw_post_error', $error);
            return $post_san;
          }
          $js_widget = $response->body;
          $bandit_html = '<!--bandit-start--><div id="bw-container"><div id="'.$bandit->bandit->uuid.'"></div>'.'<script type="application/javascript">'.wp_slash($js_widget).'</script></div><!--bandit-end-->';
          $post_content = str_replace($this->shortcode, $bandit_html, $post_content);
          $post_san['post_content'] = $post_content;
        }
      }
    }
    return $post_san;
  }
}

$BayesianWitch = new BayesianWitch();
