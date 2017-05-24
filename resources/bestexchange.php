<?php
header("Content-type:application/json");
error_reporting(0);
date_default_timezone_set('UTC');
require_once("classes/factions.php");
require_once("classes/lpexchange.php");
$factions = new Factions();

$exchanges = [];

foreach ($factions->get() as $index => $faction) {
  $lpExchange = new LPExchange($faction->corp->id);
  $item = $lpExchange->get()->lpStore[0];
  array_push($exchanges, (object)[
    'shortname' => $faction->shortname,
    'name' => $faction->name,
    'type_name' => $item->type_name,
    'type_description' => $item->type_description,
    'highest_buy' => $item->highest_buy,
    'lowest_sell' => $item->lowest_sell,
    'exchange' => $item->exchange,
  ]);
}

usort($exchanges, function($a, $b){
  if ($a->exchange->immediate == $b->exchange->immediate) {
      return 0;
  }
  return ($a->exchange < $b->exchange) ? +1 : -1;
});

echo json_encode($exchanges, JSON_PRETTY_PRINT);