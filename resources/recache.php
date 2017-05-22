<?php
header("Content-type:application/json");
date_default_timezone_set('UTC');

require_once("classes/factions.php");
require_once("classes/lpstore.php");
require_once("classes/lpexchange.php");

$alwaysRecache = false; // Set true for testing only.

$factions = new Factions();

// Recache LP Stores
foreach ($factions->get() as $index => $faction) {
  $lpStore = new LPStore($faction->corp->id);
  if (
    is_null($lpStore->get()) // No cache exists.
    || new DateTime($lpStore->get()->cachedUntil) < new DateTime() // Cache is old.
    || $alwaysRecache === true // We just don't care. 
  ) {
    $lpStore->updateCache();
  }
}


// Recache LP Store Exchange Prices
foreach ($factions->get() as $index => $faction) {
  $lpExchange = new LPExchange($faction->corp->id);
  if (
    is_null($lpExchange->get()) // No cache exists.
    || new DateTime($lpExchange->get()->cachedUntil) < new DateTime() // Cache is old.
    || $alwaysRecache === true // We just don't care. 
  ) {
    $lpExchange->updateCache();
  }
}

echo ('{"done": true}');

