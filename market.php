<?php
header("Content-type:application/json");
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

echo json_encode($dashboard->get(), JSON_PRETTY_PRINT);