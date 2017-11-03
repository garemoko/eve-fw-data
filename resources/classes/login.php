<?php

require_once( __DIR__ . "/../../config.php");

class Login {

  public function __construct(){
  }

  public function getAuthToken($code){
    global $CFG;
    $ch = curl_init(); 
    $header = array();
    $header[] = 'Content-Type: application/x-www-form-urlencoded';
    $header[] = 'Host: login.eveonline.com';
    $header[] = 'User-Agent: evewarfare.com';
    $header[] = 'Authorization: Basic '.base64_encode($CFG->sso->clientID.':'.$CFG->sso->secretKey);

    curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
    curl_setopt($ch, CURLOPT_URL, 'https://login.eveonline.com/oauth/token');
    curl_setopt($ch,CURLOPT_POST, 1);
    curl_setopt($ch,CURLOPT_POSTFIELDS, 'grant_type=authorization_code&code='.$code);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    $output = curl_exec($ch); 
    $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return (object)[
      'status' => $responseCode,
      'content' => $output
    ];
  }

  public function getCharacter($token){
    global $CFG;
    $ch = curl_init(); 
    $header = array();
    $header[] = 'Host: login.eveonline.com';
    $header[] = 'User-Agent: evewarfare.com';
    $header[] = 'Authorization: Bearer '.$token;

    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_URL, 'https://login.eveonline.com/oauth/verify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    $output = curl_exec($ch); 
    $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return (object)[
      'status' => $responseCode,
      'content' => $output
    ];
  }

  public function refreshToken($token){
    global $CFG;
    $ch = curl_init(); 
    $header = array();
    $header[] = 'Content-Type: application/x-www-form-urlencoded';
    $header[] = 'Host: login.eveonline.com';
    $header[] = 'User-Agent: evewarfare.com';
    $header[] = 'Authorization: Basic '.base64_encode($CFG->sso->clientID.':'.$CFG->sso->secretKey);

    curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
    curl_setopt($ch, CURLOPT_URL, 'https://login.eveonline.com/oauth/token');
    curl_setopt($ch,CURLOPT_POST, 1);
    curl_setopt($ch,CURLOPT_POSTFIELDS, 'grant_type=refresh_token&refresh_token='.$token);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    $output = curl_exec($ch); 
    $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return (object)[
      'status' => $responseCode,
      'content' => $output
    ];
  }
}