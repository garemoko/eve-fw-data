<?php
header("Content-type:text/html");
require_once( __DIR__ . "/resources/slack/classes/dashboardregistry.php");
require_once( __DIR__ . "/resources/slack/classes/dashboard.php");

if (
  !isset($_GET["id"])
) {
  http_response_code(400);
  echo('No dashboard id provided');
  die();
}

$dashboardregistry = new DashboardRegistry();
$dashboardMetaData = $dashboardregistry->getById($_GET["id"]);

if (is_null($dashboardMetaData) || new DateTime($dashboardMetaData->expires) < new DateTime()) {
  http_response_code(404);
  echo('Dashboard not found or expired.');
  die();
}

$dashboard = new Dashboard($dashboardMetaData->slackToken, $dashboardMetaData->slackChannelId);
$data = $dashboard->get();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Market Dashboard</title>
    <link href="main.css" rel="stylesheet" />
</head>
<body>
  <div class="title">
    <h1>Market Dashboard</h1>
    <p>By <a href="https://gate.eveonline.com/Profile/DrButterfly%20PHD" target="_blank">DrButterfly PHD</a></p>
  </div>
  <div class="links">
    <b>Other Useful sites: </b>
    <a href="https://evewarfare.com/" target="_blank">EVE Warfare</a>
    | <a href="https://forums.eveonline.com/default.aspx?g=posts&t=496584" target="_blank">Ushra'Khan Recruitment</a> 
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