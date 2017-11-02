<?php

require_once( __DIR__ . "/../config.php");
global $CFG;


// Check if user has active session
$sessionFound = false;
if (isset($_GET["code"]) && isset($_GET["state"])){
  //TODO: check state is found in database
  $sessionFound = true;
}

// If not, redirect to CCP to login
if (!$sessionFound){

  $sessionId = uniqid();
  // TODO: store this in the database with an expiry and appropriate metadata
  // TODO: cron task to tidy up old sessions.  

  $params = [
    'response_type' => 'code',
    'redirect_uri' => $CFG->sso->callback,
    'client_id' => $CFG->sso->clientID,
    'scope' => $CFG->sso->scope,
    'state' => $sessionId
  ];

  header('Location: https://login.eveonline.com/oauth/authorize/?' . http_build_query($params));
  die();
}

$response = (object)[
  'token' => null,
  'character' => null
];

$ch = curl_init(); 
$header = array();
$header[] = 'Content-Type: application/x-www-form-urlencoded';
$header[] = 'Host: login.eveonline.com';
$header[] = 'User-Agent: evewarfare.com';
$header[] = 'Authorization: Basic '.base64_encode($CFG->sso->clientID.':'.$CFG->sso->secretKey);

curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
curl_setopt($ch, CURLOPT_URL, 'https://login.eveonline.com/oauth/token');
curl_setopt($ch,CURLOPT_POST, 1);
curl_setopt($ch,CURLOPT_POSTFIELDS, 'grant_type=authorization_code&code='.$_GET["code"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
$output = curl_exec($ch); 
$responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
unset($ch);

if ($responseCode == 200){
  $response->token = json_decode($output);

  $ch = curl_init(); 
  $header = array();
  $header[] = 'Host: login.eveonline.com';
  $header[] = 'User-Agent: evewarfare.com';
  $header[] = 'Authorization: Bearer '.$response->token->access_token;

  curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
  curl_setopt($ch, CURLOPT_URL, 'https://login.eveonline.com/oauth/verify');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
  $output = curl_exec($ch); 
  $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  unset($ch);

  if ($responseCode == 200){
    $response->character = json_decode($output);
  }
}


/*if ($responseCode != 200){
  var_dump($output);
  var_dump($responseCode);
  echo ('Login failed.');
  die();
}*/

echo('<pre>');
echo(json_encode($response, JSON_PRETTY_PRINT));



