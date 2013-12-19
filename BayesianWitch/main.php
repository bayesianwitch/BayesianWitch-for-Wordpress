<?php
  /*
  Plugin Name: BayesianWitch
  Version: 0.2
  Description: -
  Author: Vladymir Goryachev
  */

  // Client ID: bc232af6-8609-4e5f-a24a-7231ff7e14e5
  // Client Secret: 2d35ffd0-f072-4644-bcf9-d94f91e51734

require_once 'curl.php';

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


  public function __construct(){
    $this->api_key_needed_message = "Please enter domain, client ID and secret ID. These can be found by creating an account on the <a href=\"http://192.241.169.111?source=wordpress\">BayesianWitch.com</a> site. Once an account is created, you create a site, then click the \"Reset and view API keys\" button.";
    $this->configuration_needed_message = '<a href="'.site_url().'/wp-admin/plugins.php?page=BayesianWitch">Click here</a> to configure BayesianWitch plugin';

    $this->js_widget_url = 'http://recommend.bayesianwitch.com';
    $this->api_url = 'http://api.bayesianwitch.com';
    $this->api_port = '8090';
    $this->api_full_url = $this->api_url.':'.$this->api_port;

    add_action('admin_menu', array($this, 'register_submenu'));
    add_action('admin_enqueue_scripts', array($this, 'load_admin_stylesheet'));
    add_filter('content_edit_pre', array($this, 'filter_bandit_shortcode'), 10, 2);
    wp_register_style('bw_wpautop_fix', plugins_url('wpautop_fix.css', __FILE__) );
    wp_enqueue_style('bw_wpautop_fix');
    
    if($this->is_configured()){ //main functions don't work without credentials
      add_action('add_meta_boxes_post', array($this, 'add_bandit_meta_box'));
      add_action('wp_head', array($this, 'add_tracking_js'));
      add_filter('wp_insert_post_data', array($this, 'save_metadata'), '99', 2);
    } else {
      add_action('admin_notices', array($this, 'render_admin_error'));
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
    $this->domain = $x;
    update_option('bw_domain', $x);
  }

  private function set_client($x){
    $this->client = $x;
    update_option('bw_client', $x);
  }

  private function set_secret($x){
    $this->secret = $x;
    update_option('bw_secret', $x);
  }


  private function get_site_uuid(){
    if(!isset($this->site_uuid)){
      if(!$this->site_uuid = get_option('bw_site_uuid')){
        $url = $this->api_full_url.'/sites/'.$this->get_domain().'/?client='.$this->get_client().'&secret='.$this->get_secret();
        $response = Curl::get($url);
        if($this->get_api_error($response)) return;
        $json = $response->body;
        $this->site_uuid = $json->uuid;
        update_option('bw_site_uuid', $this->site_uuid);
      }
    }
    return $this->site_uuid;
  }

  private function get_bandit($bandit_tag){
    $url = $this->api_full_url.'/bandits/'.$this->get_site_uuid().'/'.$bandit_tag.'?client='.$this->get_client().'&secret='.$this->get_secret();
    $response = Curl::get($url);
    return $response;
  }

  private function get_js_widget($bandit_uuid){
    $url = $this->js_widget_url.'/js_widget/'.$bandit_uuid;
    $response = Curl::get($url);
    return $response;
  }

  private function get_api_error($response){
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

  private function get_credentials_and_domain_error(){
    $url_credentials = $this->api_full_url.'/client/check_credentials?client='.$this->get_client().'&secret='.$this->get_secret();
    $response = Curl::get($url_credentials);
    if($error = $this->get_api_error($response)){
      return $error;
    }
    $url_domain = $this->api_full_url.'/sites/'.$this->get_domain().'?client='.$this->get_client().'&secret='.$this->get_secret();
    $response = Curl::get($url_domain);
    if($error = $this->get_api_error($response)){
      return $error;
    }
    return false;
  }

  private function send_bandit_update($data, $bandit_tag){
    $url = $this->api_full_url.'/bandits/'.$this->get_site_uuid().'/'.$bandit_tag.'?client='.$this->get_client().'&secret='.$this->get_secret();
    $response = Curl::put_json($url, $data);
    return $response;
  }

  private function validate_tag($tag){
    return preg_match('/^[a-zA-Z0-9_]+$/', $tag);
  }

  private function is_configured(){
    return ($this->get_domain() && $this->get_client() && $this->get_secret());
  }

  public function register_submenu(){
    add_submenu_page('plugins.php', 'BayesianWitch plugin settings', 'BayesianWitch', 10, 'BayesianWitch', array($this, 'render_menu'));
  }

  public function load_admin_stylesheet($hook){
    wp_register_style('bw_stylesheet', plugins_url('stylesheet.css', __FILE__) );
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
    if($this->is_configured()){
      if($error = $this->get_credentials_and_domain_error()){
        echo '<div class="bw-error-box">'.$error.'</div>';
      }
    }else{
      echo '<div class="bw-error-box">'.$this->api_key_needed_message.'</div>';
    }
    echo '<form action="" method="post">';
    echo '<div id="bayesianwitch" class="postbox bw-admin-config"><div class="inside">';
    echo '<h4>Domain:</h4>';
    echo '<input class="input" type="text" name="bw-domain" value="'.$this->get_domain().'">';
    echo '<div class="clear"></div>';
    echo '<h4>Client ID:</h4>';
    echo '<input class="input" type="text" name="bw-client" value="'.$this->get_client().'">';
    echo '<div class="clear"></div>';
    echo '<h4>Secret Key:</h4>';
    echo '<input class="input" type="text" name="bw-secret" value="'.$this->get_secret().'">';
    echo '<div class="clear"></div>';
    echo '<input type="submit" value="Save" class="bw-button">';
    echo '<div class="clear"></div>';
    echo '</div></div>';
    echo '<p>The client ID and secret ID can be found on <a href=\"http://192.241.169.111?source=wordpress_plugin\" target=\"_blank\">BayesianWitch.com</a>. You need to create an account and then create a site, where the domain of the site is the domain of your blog (e.g. www.myblog.com). The enter "www.myblog.com" (or whatever your real domain is) for the Domain field.</p>';
    echo '<p>To find the Client ID and Secret Key, click the "Reset and view API keys" button on the BayesianWitch <a href=\"http://192.241.169.111/accounts/profile?source=wordpress_plugin\" target=\"_blank\">profile page</a>.</p>';
    echo '</form>';
  }

  public function filter_bandit_shortcode($content, $post_id){
    $result = preg_replace('/<!--bandit-start-->.*?<!--bandit-end-->/is', '[bandit]', $content);
    return $result;
  }

  public function add_tracking_js(){
    $js = get_option('bw_tracking_js');
    if(!$js){
      $url = $this->api_full_url.'/sites/'.$this->get_domain().'/javascript?client='.$this->get_client().'&secret='.$this->get_secret();
      $response = Curl::get($url);
      if(!$this->get_api_error($response)){
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
      if($error = $this->get_api_error($response)){
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

    echo '<p>To embed a bandit put "[bandit]" somewhere in the post body.</p>';
    echo '<h4>BANDIT_TAG</h4>';
    if(!$bandit_tag){
      if($post->post_title){
        $bt = 'Bandit_'.$post->post_title.'_'.date('d_F_Y');
      }else{
        $bt = 'Bandit_'.date('d_F_Y');
      }
      echo '<input class="input" type="text" name="bw-bandit-tag" value="'.$bt.'">';
    } else {
      echo '<span class="input">'.$bandit_tag.'</span>';
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
    if(!$this->validate_tag($bandit_tag) || !$this->validate_tag($bandit_tag1) || !$this->validate_tag($bandit_tag2)){
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
        if($error = $this->get_api_error($response)){
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
        if(preg_match('/\[bandit\]/', $post_content)){
          $response = $this->get_js_widget($bandit->bandit->uuid);
          if($error = $this->get_api_error($response)){
            update_option('bw_post_error', $error);
            return $post_san;
          }
          $js_widget = $response->body;
          $bandit_html = '<!--bandit-start--><div id="bw-container"><div id="'.$bandit->bandit->uuid.'"></div>'.'<script type="application/javascript">'.wp_slash($js_widget).'</script></div><!--bandit-end-->';
          $post_content = str_replace('[bandit]', $bandit_html, $post_content);
          $post_san['post_content'] = $post_content;
        }
      }
    }
    return $post_san;
  }
}

$BayesianWitch = new BayesianWitch();

