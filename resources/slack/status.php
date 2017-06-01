<?
require_once("../classes/systems.php");
date_default_timezone_set('UTC');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die();
}

if (
  !isset($_POST["user_name"]) 
  || !isset($_POST["user_id"]) 
  || !isset($_POST["team_domain"]) 
  || !isset($_POST["channel_name"]) 
  || !isset($_POST["timestamp"])
  || !isset($_POST["text"])
) {
  http_response_code(400);
  die();
}

$systems = new Systems();

foreach ($systems->get()->systems as $index => $system) {
  if ($system->solarSystemName == trim($_POST["text"])){
    $percent = $system->victoryPoints / $system->victoryPointThreshold * 100;
    $percent = number_format($percent, 2, '.', '');
    echo ('System '.$system->solarSystemName. ' is held by '.$system->occupyingFactionName.' and is '.$percent.'% contested.');
    die();
  }
}

echo ($_POST["text"]. ' not found.');