<?php
require_once("../classes/systems.php");
require_once("classes/collection.php");
date_default_timezone_set('UTC');
//header("Content-type:application/json");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die();
}

if (
  !isset($_POST["text"])
) {
  http_response_code(400);
  die();
}

$arrText = explode(' ', trim($_POST["text"]));

if (strtolower($arrText[0]) == 'market') {
  if (strtolower($arrText[1]) == 'collection') {
    $collection = new Collection($_POST["token"], $arrText[2]);
    switch ($arrText[3]) {
      case 'add':
        $itemName = $collection->add(implode(' ', array_slice($arrText, 4, count($arrText) - 5)), array_pop($arrText));
        echo ($itemName . ' added.');
        die();
        break;
      case 'list':
        echo $collection->getList();
        die();
      default:
        echo ('command '.$arrText[3]. ' not found.');
        die();
        break;
    }
  } 
  else {
    echo ('command '.$arrText[1]. ' not found.');
    die();
  }
}
else {
  $systems = new Systems();
  foreach ($systems->get()->systems as $index => $system) {
    if (strtolower($system->solarSystemName) == strtolower(trim($_POST["text"]))){
      $percent = $system->victoryPoints / $system->victoryPointThreshold * 100;
      $percent = number_format($percent, 2, '.', '');
      $message = json_encode((object)[
        "response_type" => "in_channel",
        "text" => $system->solarSystemName. ' is held by the '.$system->occupyingFactionName.' and is '.$percent.'% contested.'
      ], JSON_PRETTY_PRINT);
      echo ($message);
      die();
    }
  }
}

echo ('command or system '.$arrText[0]. ' not found.');