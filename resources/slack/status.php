<?php
require_once("../classes/systems.php");
require_once("../classes/factions.php");
require_once("../classes/orders.php");
require_once("classes/collection.php");
require_once("classes/station.php");
require_once("classes/dashboard.php");
require_once("classes/logistics.php");
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
  array_push($message, 'http://evewarfare.com/help.html');

  publicMessage (implode(PHP_EOL, $message));
  die();
}
else if (strtolower($arrText[0]) == 'courier') {
  $logistics = New Logistics ($_POST["token"], $_POST["channel_id"]);
  switch (strtolower($arrText[1])) {
    case 'add':
      if (count($arrText) == 3){
        publicMessage ($logistics->addOrder(array_pop($arrText), $_POST["user_name"]));
      }
      else {
        publicMessage ('Error: Please specify an order size in a valid format.');
      }
      die();
      break;
    case 'get':
      publicMessage ($logistics->getQueues());
      die();
      break;
    case 'remove':
      if (count($arrText) == 3){
        publicMessage ($logistics->removeOrder(array_pop($arrText)));
      }
      else {
        publicMessage ('Error: Please specify an order id.');
      }
      die();
      break;
    case 'accept':
      if (count($arrText) == 3){
        publicMessage ($logistics->acceptQueue(array_pop($arrText)));
      }
      else {
        publicMessage ('Error: Please specify a queue id.');
      }
      die();
      break;
    case 'help':
      $message = [
        'The process for creating courier contracts is as follows:',
        '1. Choose the right Slack channel for the required from/to locations.',
        '2. Begin to create a courier contract in game to get the m3 size of your items.',
        '3. In channel, use command `/wz courier [size of contract in m3]`. This will tell you the cost and order number.', 
        '4. If you accept the cost, immediately create the courier contract for that ISK value. (You can enter a higher ISK value if you want to tip.) The contract description should be your order number and nothing else. See Slack Pinned messages for who to assign the contract to. Set the maximum possible duration and DO NOT set excessive collateral.',
        '5. If you do not accept the cost, use command `/wz courier remove [order number]` to cancel.',
        'Please follow this process carefully! Repeat offenders will be banned from courier channels.',
        '',
        'The person assigned courier contracts should use the comamnds in the following process.',
        '1. `/wz courier get` to get a list of orders placed organized into queues by size.',
        '2. When a queue is of sufficient value, `/wz courier accept [queue id]` to accept the queue and remove the orders.',
        '3. In game accept all orders referenced by the queue and deliver them!',
        'DO NOT use the `/wz courier accept` command unless you are the person delivering the contracts!',
      ];
      publicMessage (implode(PHP_EOL, $message));
      die();
      break;
  }
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
    $expiry = array_slice($arrText, 2);
    if (count($expiry) > 0){
      publicMessage($dashboard->getURL(implode(' ', $expiry)));
    }
    else {
      publicMessage($dashboard->getURL());
    }
  }
  elseif (strtolower($arrText[1]) == 'expire') {
    $dashboard = new Dashboard($_POST["token"], $_POST["channel_id"]);
    $dashboard->expireAll();
    publicMessage ('All dashboard links deactivated.');
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
    $station->cacheMarket();
    $stationData = $station->get();
    $message = 'Collections: '.implode(', ', $stationData->orders) . PHP_EOL;
    $message .= 'Items: ' . PHP_EOL;
    foreach ($stationData->market as $index => $item) {
      $message .= $item->name . ' ' . $item->volume_remain . ' out of ' . $item->quantity . ' remaining.' . PHP_EOL;
    }
    publicMessage ($message);
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

publicMessage ('command or system '.$arrText[0]. ' not found.');

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