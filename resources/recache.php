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
    if (
      !is_null($lpStore->getWorkingCache()) // There is a working cache
      && (
        new DateTime($lpStore->getWorkingCache()->cachedStarted) 
        > date('c', strtotime('-15 minutes', time())) // It started in the last 15 minutes
      )
    ) {
        // Recache in process, exit. 
        echo ('{"done": false, "faction": "'.$faction->corp->id).'", "stage": "store"}');
        die();
      }
    else {
      $lpStore->updateCache();
    }
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
    if (
      !is_null($lpExchange->getWorkingCache()) // There is a working cache
      && (
        new DateTime($lpExchange->getWorkingCache()->cachedStarted) 
        > date('c', strtotime('-15 minutes', time())) // It started in the last 15 minutes
      )
    ) {
        // Recache in process, exit.
        echo ('{"done": false, "faction": "'.$faction->corp->id).'", "stage": "store"}');
        die();
      }
    else {
      $lpExchange->updateCache();
    }
  }
}

echo ('{"done": true}');

