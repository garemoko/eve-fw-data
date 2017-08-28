<?php
require_once("../classes/systems.php");
require_once("../classes/factions.php");
require_once("../classes/orders.php");
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
  array_push($message, 'collection <nameOfCollection> add <name of item> <quantityToAdd> <maxPrice|"Jita">');
  array_push($message, 'collection <nameOfCollection> addZKill <zKillboardKillURL> <quantityToAdd>');
  array_push($message, 'collection <nameOfCollection> update <name of item> <maxPrice|"Jita">');
  array_push($message, 'collection <nameOfCollection> updateAll');
  array_push($message, 'collection <nameOfCollection> remove <name of item> <quantityToRemove>');
  array_push($message, 'collection <nameOfCollection> list');
  array_push($message, 'collection <nameOfCollection> empty');
  array_push($message, 'collection <nameOfCollection> delete');
  array_push($message, 'market require <nameOfCollection> <name of station>');
  array_push($message, 'market cancel <nameOfCollection> <name of station>');
  array_push($message, 'market get <nameOfCollection> <name of station>');
  array_push($message, 'market dashboard');

  publicMessage (implode(PHP_EOL, $message));
  die();
}
else if (strtolower($arrText[0]) == 'collection') {
  $collection = new Collection($_POST["token"], $_POST["channel_id"], $arrText[1]);
  switch (strtolower($arrText[2])) {
    case 'add':
      $price = array_pop($arrText);
      $quantity = array_pop($arrText);
      $itemName = $collection->add(implode(' ', array_slice($arrText, 3, count($arrText) - 3)), $quantity, $price);
      if (is_null($itemName)) {
        publicMessage ('No matching item found. Nothing added to collection.');
        die();
      }
      else {
        publicMessage ($quantity. ' ' . $itemName . ' added.');
        die();
      }
      break;
    case 'addzkill':
      $quantity = array_pop($arrText);
      $url = array_pop($arrText);
      $response = $collection->addZKill($url, $quantity);
      if (is_null($response)) {
        publicMessage ('Failed. Nothing added to collection.');
        die();
      }
      else {
        publicMessage ($quantity. ' of kill added.');
        die();
      }
      break;
    case 'update':
      $price = array_pop($arrText);
      $itemName = $collection->updatePrice(implode(' ', array_slice($arrText, 3, count($arrText) - 3)), $price);
      if (is_null($itemName)) {
        publicMessage ('No matching item found. Nothing updated.');
        die();
      }
      else {
        publicMessage ($itemName . ' updated.');
        die();
      }
      break;
    case 'updateall':
      $collection->updateAll();
      publicMessage ('All items updated using Jita prices.');
      die();
    case 'remove':
      $quantity = array_pop($arrText);
      $itemName = $collection->remove(implode(' ', array_slice($arrText, 3, count($arrText) - 3)), $quantity);
      if (is_null($itemName)) {
        publicMessage ('No matching item found in collection. Nothing removed.');
        die();
      }
      else {
        $quantity = $quantity > 0 ? $quantity : 'All';
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
      echo ('command '.$arrText[2]. ' not found.');
      die();
      break;
  }
} 
else if (strtolower($arrText[0]) == 'market') {
  if (strtolower($arrText[1]) == 'dashboard') {
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
    if (!isset($arrText[1])){
      $arrText[1] = 'minmatar';
    }

    $orders = new Orders();
    $factionName = $arrText[1];
    if (!isset($orders->get()->$factionName)){
      echo ('faction '.$factionName. ' not found.');
      die();
    }
    //echo json_encode($orders->get()->$factionName->defend);die();
    $defendSystems = (object)[
      "fields" => []
    ];
    foreach ($orders->get()->$factionName->defend as $index => $order) {
      if (isset($defendSystems->fallback)){
        $defendSystems->fallback .= ', ' . $order->solarSystemName . ': ' . $order->contestedPercent . '%';
      }
      else {
        $defendSystems->fallback = $order->solarSystemName.  ': ' . $order->contestedPercent . '%';
      }

      array_push($defendSystems->fields, (object)[
        "title" => $order->solarSystemName,
        "value" => $order->contestedPercent . "% contested.",
        "short" => true
      ]);
    }

    $attackSystems = (object)[
      "fields" => []
    ];
    foreach ($orders->get()->$factionName->attack as $index => $order) {
      if (isset($attackSystems->fallback)){
        $attackSystems->fallback .= ', ' . $order->solarSystemName.  ': ' . $order->contestedPercent . '%';
      }
      else {
        $attackSystems->fallback = $order->solarSystemName.  ': ' . $order->contestedPercent . '%';
      }

      array_push($attackSystems->fields, (object)[
        "title" => $order->solarSystemName,
        "value" => $order->contestedPercent . "% contested.",
        "short" => true
      ]);
    }

    $factions = new Factions();
    $faction = $factions->get((object)['shortname' => $factionName])[0];
    $enemy = $factions->get((object)['enemy' => $factionName])[0];

    $attachments = [];
    if (count($defendSystems->fields) > 0) {
      array_push($attachments, (object)[
        "fallback" => $defendSystems->fallback,
        "color" => $faction->color,
        "title" => ucwords($faction->name)." Homeland",
        "text" => "YOU SHALL NOT PASS!\n Defend these systems at all costs.",
        "fields" => $defendSystems->fields
      ]);
    }
    if (count($attackSystems->fields) > 0) {
      array_push($attachments, (object)[
        "fallback" => $attackSystems->fallback,
        "color" => $enemy->color,
        "title" => ucwords($faction->corp->name)." Victory",
        "text" => "VICTORY OR DEATH!\n Claim these systems from our enemies.",
        "fields" => $attackSystems->fields
      ]);
    }
    publicMessage('Your orders, soldier:', $attachments);
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