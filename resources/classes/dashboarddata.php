<?php

require_once("classes/cache.php");
require_once("classes/systems.php");
require_once("classes/constellations.php");
require_once("classes/regions.php");
require_once("classes/factions.php");
require_once("classes/factionstats.php");
require_once("classes/lpexchange.php");

class DashboardData {
  private $util;
  private $cache;
  private $systems;
  private $constellations;
  private $regions;
  private $factions;
  private $factionstats;

  public function __construct(){
    $this->cache = new FileCache('dashboarddata.json');
    $this->systems = new Systems();
    $this->constellations = new Constellations();
    $this->regions = new Regions();
    $this->factions = new Factions();
    $this->factionstats = new FactionStats();
  }

  public function updateCache(){

    $cache = (object)[];

    $cache->systems = $this->systems->get()->systems;
    $cache->cachedUntil = $this->systems->get()->cachedUntil;
    $cache->constellations = $this->constellations->get()->constellations;
    $cache->regions = $this->regions->get()->regions;
    $cache->factions = $this->factionstats->get()->factions;
    $cache->totals = $this->factionstats->get()->totals;

    $cache->totals->systemsControlled = 0;
    foreach ($cache->factions as $index => $faction) {
      $cache->totals->systemsControlled += $faction->systemsControlled;
    }


    $exchanges = (object)[
      'immediate' => [],
      'delayed' => [],
    ];
    foreach ($this->factions->get() as $index => $faction) {
      $lpExchange = new LPExchange($faction->corp->id);
      $lpStore = $lpExchange->get()->lpStore;
      $item = $lpStore[0];
      array_push($exchanges->immediate, (object)[
        'shortname' => $faction->shortname,
        'name' => $faction->name,
        'type_name' => $item->type_name,
        'type_description' => $item->type_description,
        'highest_buy' => $item->highest_buy,
        'lowest_sell' => $item->lowest_sell,
        'exchange' => $item->exchange,
      ]);

      usort($lpStore, function($a, $b){
        if ($a->exchange->delayed == $b->exchange->delayed) {
            return 0;
        }
        return ($a->exchange->delayed < $b->exchange->delayed) ? +1 : -1;
      });
      $item = $lpStore[0];
      array_push($exchanges->delayed, (object)[
        'shortname' => $faction->shortname,
        'name' => $faction->name,
        'type_name' => $item->type_name,
        'type_description' => $item->type_description,
        'highest_buy' => $item->highest_buy,
        'lowest_sell' => $item->lowest_sell,
        'exchange' => $item->exchange,
      ]);
    }

    usort($exchanges->immediate, function($a, $b){
      if ($a->exchange->immediate == $b->exchange->immediate) {
          return 0;
      }
      return ($a->exchange->immediate < $b->exchange->immediate) ? +1 : -1;
    });

    usort($exchanges->delayed, function($a, $b){
      if ($a->exchange->delayed == $b->exchange->delayed) {
          return 0;
      }
      return ($a->exchange->delayed < $b->exchange->delayed) ? +1 : -1;
    });

    $cache->exchange_rates = $exchanges;

    $this->cache->set($cache);
  }

  public function get(){
    $cache = $this->cache->get();
    if (is_null($cache) || new DateTime($cache->cachedUntil) < new DateTime()){
      $this->updateCache();
      $cache = $this->cache->get();
    }
    return $cache;
  }
}