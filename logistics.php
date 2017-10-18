<?php
header("Content-type:text/html");
//error_reporting(0);
date_default_timezone_set('UTC');
require_once( __DIR__ . "/resources/slack/classes/dashboardregistry.php");
require_once( __DIR__ . "/resources/slack/classes/logisticsdashboard.php");

if (
  !isset($_GET["id"])
) {
  http_response_code(400);
  echo('No dashboard id provided');
  die();
}

$dashboardRegistry = new DashboardRegistry('logistics');
$dashboardMetaData = $dashboardRegistry->getById($_GET["id"]);

var_dump($dashboardRegistry->get());

if (is_null($dashboardMetaData) || new DateTime($dashboardMetaData->expires) < new DateTime()) {
  // Tidy up expired links.
  $dashboardRegistry->removeExpired();

  http_response_code(404);
  echo('Dashboard not found or expired.');
  die();
}

$dashboard = new LogisticsDashboard($dashboardMetaData->slackToken, $dashboardMetaData->slackChannelId);
$data = $dashboard->get();

$colors = (object)[
  'red' => (object)[
    'red' => 230,
    'green' => 0,
    'blue' => 0
  ],
  'yellow' => (object)[
    'red' => 204,
    'green' => 153,
    'blue' => 0
  ],
  'green' => (object)[
    'red' => 36,
    'green' => 143,
    'blue' => 36
  ]
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Move My Stuff</title>
    <link href="main.css" rel="stylesheet" />
</head>
<body>
  <div class="title">
    <h1>Move My Stuff</h1>
    <p>By <a href="https://gate.eveonline.com/Profile/DrButterfly%20PHD" target="_blank">DrButterfly PHD</a></p>
  </div>
  <div class="links">
    <b>Other Useful sites: </b>
    <a href="http://evewarfare.com/" target="_blank">EVE Warfare</a>
    | <a href="c/" target="_blank">Ushra'Khan Recruitment</a> 
    | <a href="http://evemaps.dotlan.net/" target="_blank">dotlan</a> 
    | <a href="https://eve-central.com/" target="_blank">EVE Central</a>
  </div><p class="times">&nbsp;</p>
  <div class="content">
  
  </div>
</body>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script type="text/javascript">
var data = <?php
  echo json_encode($data, JSON_PRETTY_PRINT);
?>;
console.log(data);
</script>
</html>