<?php
  /*
  Plugin Name: BayesianWitch
  Version: 0.1
  Description: -
  Author: Vladymir Goryachev
  */
  // TODO: exceptions, clean css, ability to remove a bandit
  // Client ID: bc232af6-8609-4e5f-a24a-7231ff7e14e5
  // Client Secret: 2d35ffd0-f072-4644-bcf9-d94f91e51734

class BayesianWitch{
  private $client;
  private $secret;
  private $domain;
  private $api_url;
  private $api_port;
  private $api_full_url;
  private $site_uuid;
  private $api_key_needed_message = "<p>Please enter domain, client ID and secret ID. These can be found by creating an account on the <a href=\"http://192.241.169.111?source=wordpress\">BayesianWitch.com</a> site. Once an account is created, you create a site, then click the \"Reset and view API keys\" button.</p>";

  public function __construct(){
    $this->js_widget_url = 'http://recommend.bayesianwitch.com';
    $this->api_url = 'http://api.bayesianwitch.com';
    $this->api_port = '8090';
    $this->api_full_url = $this->api_url.':'.$this->api_port;

    add_action('admin_menu', array($this, 'register_submenu'));
    add_filter('content_edit_pre', array($this, 'filter_bandit_shortcode'), 10, 2 );

    if($this->is_configured()){ //main functions don't work without credentials
      add_action('add_meta_boxes_post', array($this, 'add_bandit_meta_box'));
      add_action('wp_head', array($this, 'add_tracking_js'));
      add_action('save_post', array($this, 'save_metadata'));
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
        $json = json_decode(file_get_contents($url));
        $this->site_uuid = $json->uuid;
        update_option('bw_site_uuid', $this->site_uuid);
      }
    }
    return $this->site_uuid;
  }

  private function get_bandit($bandit_tag){
    $url = $this->api_full_url.'/bandits/'.$this->get_site_uuid().'/'.$bandit_tag.'?client='.$this->get_client().'&secret='.$this->get_secret();
    $result = json_decode(file_get_contents($url));
    return $result;
  }

  private function get_js_widget($bandit_uuid){
    $url = $this->js_widget_url.'/js_widget/'.$bandit_uuid;
    $result = file_get_contents($url);
    return $result;
  }

  private function send_bandit_update($data, $bandit_tag){
    $url = $this->api_url.'/bandits/'.$this->get_site_uuid().'/'.$bandit_tag.'?client='.$this->get_client().'&secret='.$this->get_secret();
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_PORT , $this->api_port);
    curl_setopt($curl, CURLOPT_VERBOSE, 0);
    curl_setopt($curl, CURLOPT_HEADER, 0);

    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json", "Content-length: ".strlen($data)));
    $result = json_decode(curl_exec($curl));
    return $result;
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

  public function render_menu(){
    if(isset($_POST['bw-domain'])){
      $this->set_domain($_POST['bw-domain']);
      $this->set_client($_POST['bw-client']);
      $this->set_secret($_POST['bw-secret']);
    }

    echo '<style type="text/css">
      #bayesianwitch.postbox{
        max-width: 700px;
      }
      #bayesianwitch h4{
        float: left;
        width: 10%;
        margin: 3px 0 0 0;
      }

      #bayesianwitch h4.bandit-body{
        margin-top: 30px;
      }

      #bayesianwitch .wp-editor-wrap{
        float: right;
        width: 89%;
        margin-bottom: 20px;
      }

      #bayesianwitch .input{
        float: right;
        width: 89%;
        margin-bottom: 20px;
      }

       #bayesianwitch .button{
        float: right;
        width: 70px;
      }

      #bayesianwitch span.input{
        margin:3px 0 20px 0;
      }

      .val-error{
        background-color: rgb(255, 235, 232);
        border-color: rgb(204, 0, 0);
        padding: 0px 0.6em;
        border-radius: 3px;
        border-width: 1px;
        border-style: solid;
        margin: 20px 0;
      }

    </style>';
    echo '<h2>BayesianWitch plugin settings</h2>';
    if(!$this->is_configured()){
      echo '<div class="val-error">'.$this->api_key_needed_message.'</div>';
    }
    echo '<form action="" method="post">';
    echo '<div id="bayesianwitch" class="postbox"><div class="inside">';
    echo '<h4>Domain:</h4>';
    echo '<input class="input" type="text" name="bw-domain" value="'.$this->get_domain().'">';
    echo '<div class="clear"></div>';
    echo '<h4>Client ID:</h4>';
    echo '<input class="input" type="text" name="bw-client" value="'.$this->get_client().'">';
    echo '<div class="clear"></div>';
    echo '<h4>Secret Key:</h4>';
    echo '<input class="input" type="text" name="bw-secret" value="'.$this->get_secret().'">';
    echo '<div class="clear"></div>';
    echo '<input type="submit" value="Save" class="button">';
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
      $js = file_get_contents($url);
      update_option('bw_tracking_js', $js);
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

    $bandit_tag = get_post_meta($post->ID, '_bandit_tag');
    if(empty($bandit_tag)){
      $bandit_tag = '';
    } else {
      $bandit_tag = $bandit_tag[0];
    }

    if($bandit_tag){
      $bandit = $this->get_bandit($bandit_tag);
    }

    echo '<style type="text/css">
      #bayesianwitch h4{
        float: left;
        width: 10%;
        margin: 3px 0 0 0;
      }

      #bayesianwitch h4.bandit-body{
        margin-top: 30px;
      }

      #bayesianwitch .wp-editor-wrap{
        float: right;
        width: 89%;
        margin-bottom: 20px;
      }

      #bayesianwitch .input{
        float: right;
        width: 89%;
        margin-bottom: 20px;
      }

      #bayesianwitch span.input{
        margin:3px 0 20px 0;
      }

      #bayesianwitch .val-error{
        background-color: rgb(255, 235, 232);
        border-color: rgb(204, 0, 0);
        padding: 0px 0.6em;
        border-radius: 3px;
        border-width: 1px;
        border-style: solid;
        margin: 20px 0;
      }

    </style>';
// var_dump($bandit); die;
    if(get_option('bw_validation_error') == '1'){
      echo '<div class="val-error"><p>Validation error. BANDIT_TAG, TAG1 and TAG2 must be alphanumeric strings with no spaces. Underscores are permitted.</p></div>';
      update_option('bw_validation_error', '0');
    }
    echo '<p>To embed a bandit put "[bandit]" somewhere in the post body.</p>';
    echo '<h4>BANDIT_TAG</h4>';
    if(!$bandit_tag){
      echo '<input class="input" type="text" name="bw-bandit-tag" value="">';
    } else {
      echo '<span class="input">'.$bandit_tag.'</span>';
    }
    echo '<div class="clear"></div>';

    echo '<h4>TAG1</h4>';
    if(!$bandit_tag){
      echo '<input class="input" type="text" name="bw-tag1" value="">';
    } else {
      echo '<span class="input">'.$bandit->variations[0]->tag.'</span>';
      echo '<input type="hidden" name="bw-tag1" value="'.$bandit->variations[0]->tag.'"></input>';
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
      echo '<input class="input" type="text" name="bw-tag2" value="">';
    } else {
      echo '<span class="input">'.$bandit->variations[1]->tag.'</span>';
      echo '<input type="hidden" name="bw-tag2" value="'.$bandit->variations[1]->tag.'"></input>';
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


  public function save_metadata($post_id){
    global $post;

    if(!$post) return; //return if it's an auto draft or revision

    $bandit_tag = get_post_meta($post->ID, '_bandit_tag');
    if($bandit_tag){ // if already exists
      $bandit_tag = $bandit_tag[0];
      $bandit_old = $this->get_bandit($bandit_tag);
      $bandit_tag1 = $_POST['bw-tag1'];
      $bandit_tag2 = $_POST['bw-tag2'];
    }else{
      $bandit_tag = $_POST['bw-bandit-tag'];
      $bandit_tag1 = $_POST['bw-tag1'];
      $bandit_tag2 = $_POST['bw-tag2'];
    }
    if(!$this->validate_tag($bandit_tag) || !$this->validate_tag($bandit_tag1) || !$this->validate_tag($bandit_tag2)){
      update_option('bw_validation_error', '1');
      return;
    }

    $bandit_body1 = stripslashes($_POST['bw-body1']);
    $bandit_body2 = stripslashes($_POST['bw-body2']);

    if($bandit_tag){
      $json = array();
      $json[] = array('tag' => $bandit_tag1, 'isActive' => true, 'contentAndType' => array('content_type' => 'text/html', 'content' => $bandit_body1));
      $json[] = array('tag' => $bandit_tag2, 'isActive' => true, 'contentAndType' => array('content_type' => 'text/html', 'content' => $bandit_body2));
      $json = json_encode($json, JSON_UNESCAPED_SLASHES);
      $bandit = $this->send_bandit_update($json, $bandit_tag);
      if($bandit){
        update_post_meta($post->ID, '_bandit_tag', $bandit_tag);

        $post_content = $_POST['post_content'];
        if(preg_match('/\[bandit\]/', $post_content)){
          $js_widget = $this->get_js_widget($bandit->bandit->uuid);
          $bandit_html = '<!--bandit-start--><div id="'.$bandit->bandit->uuid.'"></div>'.'<script type="application/javascript">'.wp_slash($js_widget).'</script><!--bandit-end-->';
          $post_content = str_replace('[bandit]', $bandit_html, $post_content);

          remove_action('save_post', array($this, 'save_metadata'));
          wp_update_post(array('ID' => $post->ID, 'post_content' => $post_content));
          add_action('save_post', array($this, 'save_metadata'));
        }
      }
    }
  }
}

$BayesianWitch = new BayesianWitch();
