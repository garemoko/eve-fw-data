<?php
header("Content-type:text/html");
//error_reporting(0);
date_default_timezone_set('UTC');
session_start();

require_once( __DIR__ . "/../config.php");
require_once( __DIR__ . "/../resources/classes/database.php");
require_once( __DIR__ . "/../resources/classes/login.php");
global $CFG;

// Set up database
$db = new Database();
if (!$db->tableExists('uk_sessions')){
  var_dump($db->createTable('uk_sessions', (object)[
    'sessionId' => (object) [
      'type' => 'VARCHAR',
      'size' => 30,
      'attributes' => ['NOT NULL','PRIMARY KEY']
    ],
    'FK_characters_id' => (object) [
      'type' => 'INT',
      'size' => 20,
      'attributes' => []
    ],
    'lastPage' => (object) [
      'type' => 'VARCHAR',
      'size' => 20,
      'attributes' => []
    ]
  ]));
}

if (!$db->tableExists('uk_characters')){
  $db->createTable('uk_characters', (object)[
    'id' => (object) [
      'type' => 'INT',
      'size' => 20,
      'attributes' => ['NOT NULL','PRIMARY KEY']
    ],
    'name' => (object) [
      'type' => 'VARCHAR',
      'size' => 30,
      'attributes' => ['NOT NULL']
    ],
    'accessToken' => (object) [
      'type' => 'VARCHAR',
      'size' => 255,
      'attributes' => ['NOT NULL']
    ],
    'refreshToken' => (object) [
      'type' => 'VARCHAR',
      'size' => 255,
      'attributes' => ['NOT NULL']
    ]
  ]);
}

$login = new Login();

$page = 'home';
if (isset($_GET["p"])){
  $page = $_GET["p"];
}

if ($page == 'logout'){
  // Remove session from database
  if (isset($_SESSION['id'])){
    $db->deleteRow('uk_sessions', [
      'sessionId' => $_SESSION['id']
    ]);
  }

  // Clear session from the browser. 
  session_unset();
  // Don't destory the session, because we need to start a new login. 
}


$character = null;

// If CCP login querystring is provided, login and refresh page. 
if (isset($_GET["code"]) && isset($_GET["state"])) {
  $logindata = (object)[
    'token' => null,
    'character' => null
  ];

  $tokenresponse = $login->getAuthToken($_GET["code"]);
  if ($tokenresponse->status == 200){
    $logindata->token = json_decode($tokenresponse->content);
    $characterresponse = $login->getCharacter($logindata->token->access_token);
    if ($characterresponse->status == 200){
      $logindata->character = json_decode($characterresponse->content);
    }
    else {
      var_dump($characterresponse->content);
      var_dump($characterresponse->status);
      echo ('Fetching Character Failed.');
      die();
    }
  }
  else {
    var_dump($tokenresponse->content);
    var_dump($tokenresponse->status);
    echo ('Login failed.');
    die();
  }

  $character = (object)[
    'id' => $logindata->character->CharacterID,
    'name' => $logindata->character->CharacterName,
    'accessToken' => $logindata->token->access_token,
    'refreshToken' => $logindata->token->refresh_token 
  ];

  $rows = $db->getRow('uk_characters', [
    'id' => $character->id
  ]);
  if (count($rows) == 0){
    $db->addRow('uk_characters', [
      'id' => $character->id,
      'name' => $character->name,
      'accessToken' => $character->accessToken,
      'refreshToken' => $character->refreshToken 
    ]);
  }
  else {
    $db->updateRow('uk_characters', [
      'id' => $character->id
    ] , [
      'accessToken' => $character->accessToken,
      'refreshToken' => $character->refreshToken 
    ]);
  }

  $_SESSION['id'] = $_GET["state"];
  $rows = $db->getRow('uk_sessions', [
    'sessionId' => $_SESSION['id']
  ]);
  if (count($rows) == 0){
    $db->addRow('uk_sessions', [
      'sessionId' => $_SESSION['id'],
      'FK_characters_id' => $character->id
    ]);
    header(
      'Location: '. $_SERVER['PHP_SELF'] . '?' . http_build_query(['p' => 'home'])
    );
  }
  else {
    $db->updateRow('uk_sessions', [
    'sessionId' => $_SESSION['id']
  ], [
      'FK_characters_id' => $character->id
    ]);
    header(
      'Location: '. $_SERVER['PHP_SELF'] . '?' . http_build_query(['p' => $rows[0]->lastPage])
    );
  }

  // Redirect to last location before login.
  
  die();
}

// SSO params not provided.
// Check if user has active session
$sessionFound = false;
$session = null;

// Check for session cookies
if (isset($_SESSION['id'])){
  $rows = $db->getRow('uk_sessions', [
    'sessionId' => $_SESSION['id']
  ]);

  if (count($rows) > 0){
    $session = $rows[0];
    $rows = $db->getRow('uk_characters', [
      'id' => $session->FK_characters_id
    ]);
    if (count($rows) > 0){
      $character = $rows[0];
    }
  }
}

if (!is_null($character)){
  $refreshresponse = $login->refreshToken($character->refreshToken);
  if ($refreshresponse->status == 200){
    $token = json_decode($refreshresponse->content);
    $character->accessToken = $token->access_token;
    $character->refreshToken = $token->refresh_token;

    $db->updateRow('uk_characters', [
      'id' => $character->id
    ] , [
      'accessToken' => $character->accessToken,
      'refreshToken' => $character->refreshToken 
    ]);
    $sessionFound = true;
  }
}

// No SSO params provided and no active session. 
// Redirect to CCP to login
if (!($sessionFound)){
  $page = $page == 'logout' ? 'home' : $page;
  $sessionId = uniqid();
  $db->addRow('uk_sessions', [
    'sessionId' => $sessionId,
    'lastPage' => $page
  ]);

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

// If the page gets this far, there is an active logged in session. 
?>

<!DOCTYPE html>
<html>
<head>
    <title>Uashra'Khan Alliance Services</title>
    <link href="../main.css" rel="stylesheet" />
</head>
<body>
  <div class="title">
    <h1>Ushra'Khan Alliance Services</h1>
    <img src="uk_header.jpg" class="ushrakhan" alt="Ushra'Khan" />
  </div>
  <div class="login">
    Logged in as: <b><?=$character->name?></b>. 
    <a href="<?=$_SERVER['PHP_SELF']?>?p=logout">Switch</a>
  </div>
  <div class="content">
  
  </div>
</body>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script type="text/javascript">
var data = <?php
  echo json_encode($character, JSON_PRETTY_PRINT);
?>;
console.log(data);
</script>
</html>



