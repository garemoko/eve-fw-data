<?php
require_once("../classes/systems.php");
require_once("../classes/factions.php");
require_once("classes/collection.php");
require_once("classes/station.php");
require_once("classes/dashboard.php");
date_default_timezone_set('UTC');
header("Content-type:application/json");

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
if (strtolower($arrText[0]) == 'help') {
  $message = [];
  array_push($message, 'Allowed commands:');
  array_push($message, '<name of system>');
  array_push($message, 'orders <shortname of faction>');
  array_push($message, 'market collection <nameOfCollection> add <quantityToAdd> <maxPrice>');
  array_push($message, 'market collection <nameOfCollection> remove <quantityToRemove>');
  array_push($message, 'market collection <nameOfCollection> list');
  array_push($message, 'market collection <nameOfCollection> empty');
  array_push($message, 'market collection <nameOfCollection> delete');
  array_push($message, 'market require <nameOfCollection> <name of station>');
  array_push($message, 'market cancel <nameOfCollection> <name of station>');
  array_push($message, 'market get <nameOfCollection> <name of station>');

  publicMessage (implode(PHP_EOL, $message));
  die();
}
else if (strtolower($arrText[0]) == 'market') {
  if (strtolower($arrText[1]) == 'collection') {
    $collection = new Collection($_POST["token"], $_POST["channel_id"], $arrText[2]);
    switch ($arrText[3]) {
      case 'add':
        $price = array_pop($arrText);
        $quantity = array_pop($arrText);
        $itemName = $collection->add(implode(' ', array_slice($arrText, 4, count($arrText) - 4)), $quantity, $price);
        if (is_null($itemName)) {
          publicMessage ('No matching item found. Nothing added to collection.');
          die();
        }
        else {
          publicMessage ($quantity. ' ' . $itemName . ' added.');
          die();
        }
        break;
      case 'remove':
        $quantity = array_pop($arrText);
        $itemName = $collection->remove(implode(' ', array_slice($arrText, 4, count($arrText) - 4)), $quantity);
        if (is_null($itemName)) {
          publicMessage ('No matching item found in collection. Nothing removed.');
          die();
        }
        else {
          publicMessage ($quantity. ' ' . $itemName . ' removed.');
          die();
        }
        break;
      case 'empty':
        $collection->removeAll();
        publicMessage ('All items removed.');
        die();
      case 'delete':
        $collection->delete();
        publicMessage ('Collection deleted.');
        die();
      case 'list':
        publicMessage ($collection->getList());
        die();
      default:
        echo ('command '.$arrText[3]. ' not found.');
        die();
        break;
    }
  } 
  elseif (strtolower($arrText[1]) == 'dashboard') {
    $dashboard = new Dashboard($_POST["token"], $_POST["channel_id"]);
    publicMessage($dashboard->getURL());
    $dashboard->get();
  }
  elseif (strtolower($arrText[1]) == 'require') {
    $collectionName = $arrText[2];
    $station = implode(' ', array_slice($arrText, 3, count($arrText) - 3));
    $station = new Station($_POST["token"], $_POST["channel_id"], $station);
    if (is_null($station->get())){
      publicMessage ('Station not found.');
      die();
    }

    publicMessage ($station->addOrder($collectionName));
    die();
  }
  elseif (strtolower($arrText[1]) == 'cancel') {
    $collectionName = $arrText[2];
    $station = implode(' ', array_slice($arrText, 3, count($arrText) - 3));
    $station = new Station($_POST["token"], $_POST["channel_id"], $station);
    if (is_null($station->get())){
      publicMessage ('Station not found.');
      die();
    }

    publicMessage ($station->cancelOrder($collectionName));
    die();
  }
  elseif (strtolower($arrText[1]) == 'get') {
    $station = implode(' ', array_slice($arrText, 2, count($arrText) - 2));
    $station = new Station($_POST["token"], $_POST["channel_id"], $station);
    if (is_null($station->get())){
      publicMessage ('Station not found.');
      die();
    }
    publicMessage ($station->cacheMarket());
    die();
  }
  else {
    echo ('command '.$arrText[1]. ' not found.');
    die();
  }
}
  elseif (strtolower($arrText[0]) == 'orders') {
    $defenceThreshold = 0.3;

    if (!isset($arrText[1])){
      $arrText[1] = 'minmatar';
    }

    $factions = new Factions();
    $factionResponse = $factions->get((object)['shortname' => $arrText[1]]);
    if (count($factionResponse) !== 1){
      echo ('faction '.$arrText[1]. ' not found.');
      die();
    }
    $faction = $factionResponse[0];
    $enemy = $factions->get((object)['shortname' => $faction->enemy])[0];

    $defendSystems = [];
    $defendSystems[$faction->shortname] = (object)[
      "fields" => []
    ];
    $attackSystems = [];
    $attackSystems[$faction->shortname] = (object)[
      "fields" => []
    ];

    $systems = new Systems();
    foreach ($systems->get()->systems as $index => $system) {
      if ($system->solarSystemName == 'Arzad'){
        $system->solarSystemName = 'Starkman';
      }
      $contestedPercent = round(($system->victoryPoints / $system->victoryPointThreshold * 100), 2) . '%';
      if ($system->occupyingFactionName == $faction->name && (($system->victoryPoints / $system->victoryPointThreshold) > $defenceThreshold)){
        if (isset($defendSystems[$faction->shortname]->fallback)){
          $defendSystems[$faction->shortname]->fallback .= ', ' . $system->solarSystemName . ': ' . $contestedPercent;
        }
        else {
          $defendSystems[$faction->shortname]->fallback = $system->solarSystemName.  ': ' . $contestedPercent;
        }

        array_push($defendSystems[$faction->shortname]->fields, (object)[
          "title" => $system->solarSystemName,
          "value" => $contestedPercent . " contested.",
          "short" => true
        ]);
      }
      else if ($system->occupyingFactionName == $enemy->name){
        if (isset($defendSystems[$faction->shortname]->fallback)){
          $attackSystems[$faction->shortname]->fallback .= ', ' . $system->solarSystemName.  ': ' . $contestedPercent;
        }
        else {
          $attackSystems[$faction->shortname]->fallback = $system->solarSystemName.  ': ' . $contestedPercent;
        }

        array_push($attackSystems[$faction->shortname]->fields, (object)[
          "title" => $system->solarSystemName,
          "value" => $contestedPercent . " contested.",
          "short" => true
        ]);
      }
    }

    $attachments = [];
    if (count($defendSystems[$faction->shortname]->fields) > 0) {
      array_push($attachments, (object)[
        "fallback" => $defendSystems[$faction->shortname]->fallback,
        "color" => $faction->color,
        "title" => ucwords($faction->name)." Homeland",
        "text" => "YOU SHALL NOT PASS!\n Defend these systems at all costs.",
        "fields" => $defendSystems[$faction->shortname]->fields
      ]);
    }
    if (count($attackSystems[$faction->shortname]->fields) > 0) {
      array_push($attachments, (object)[
        "fallback" => $attackSystems[$faction->shortname]->fallback,
        "color" => $enemy->color,
        "title" => ucwords($faction->corp->name)." Victory",
        "text" => "VICTORY OR DEATH!\n Claim these systems from our enemies.",
        "fields" => $attackSystems[$faction->shortname]->fields
      ]);
    }
    publicMessage('Your orders, solider:', $attachments);
  }
else {
  $systems = new Systems();
  foreach ($systems->get()->systems as $index => $system) {
    $systemName = strtolower(trim($_POST["text"]));
    $systemNameToCompare = $systemName;
    if ($systemName == 'starkman'){
      $systemNameToCompare = 'arzad';
    }
    if (strtolower($system->solarSystemName) == $systemNameToCompare){
      $percent = $system->victoryPoints / $system->victoryPointThreshold * 100;
      $percent = number_format($percent, 2, '.', '');
      publicMessage (
        ucwords($systemName). ' is held by the '.$system->occupyingFactionName.' and is '.$percent.'% contested.'
      );
      die();
    }
  }
}

echo ('command or system '.$arrText[0]. ' not found.');

function publicMessage($message, $attachments = []){
  $response = (object)[
    "response_type" => "in_channel",
    "text" => $message
  ];
  if (count($attachments) > 0){
    $response->attachments = $attachments;
  }
  echo json_encode($response, JSON_PRETTY_PRINT);
}