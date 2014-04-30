<?php

class BayesianWitchRemote{
  private static $default_args = array('user-agent' => 'BayesianWitch Wordpress Call-to-Action tester');

  public static function get($url){
    $response = new BayesianWitchResponse(wp_remote_get($url, BayesianWitchRemote::$default_args));
    return $response;
  }

  public static function put_json($url, $data){
    $args = BayesianWitchRemote::$default_args;
    $args['headers'] = array('Content-Type' => 'application/json', 'Content-Length' => strlen($data));
    $args['method'] = 'PUT';
    $args['body'] = $data;
    $response = new BayesianWitchResponse(wp_remote_request($url, $args));
    return $response;
  }
}

class BayesianWitchResponse{
  public $body;
  public $headers;
  public $response;
  public $cookies;
  public $filename;
  public $response_raw;

  public function __construct($response){
    $this->response_raw = $response;
    if(is_wp_error($this->response_raw)){
      $this->error_message = $this->response_raw->get_error_message();
      return;
    }
    $this->body = $response['body'];
    $this->headers = $response['headers'];
    $this->response = $response['response'];
    $this->cookies = $response['cookies'];
    $this->filename = $response['filename'];
  }

  public function get_error(){
    if(isset($this->error_message)) return $this->error_message;
    if($this->response['code'] >= 400){
      $json = json_decode($this->body);
      if(isset($json->humanReadable)){
        return 'BayesianWitch Error: '.$json->humanReadable;
      }
      if(isset($json->message)){
        return 'BayesianWitch Error: '.$json->message;
      }
      return 'BayesianWitch Error: HTTP code '.$this->response['code'];
    }
    return false;
  }
}