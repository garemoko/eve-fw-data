<?php
require_once(__DIR__ . "/cache.php");
require_once(__DIR__ . "/util.php");
require_once(__DIR__ . "/factions.php");
require_once(__DIR__ . "/universe.php");

class Systems {
  private $util;
  private $cache;

  public function __construct(){
    $this->util = new Util();
    $this->factions = new Factions();
    $this->cache = new FileCache('systems.json');
    $this->universe = new Universe(); 
  }

  public function updateCache(){

    date_default_timezone_set('UTC');
    $systems = $this->util->requestAndRetry('https://esi.tech.ccp.is/latest/fw/systems/?datasource=tranquility', []);

    $cache = (object)[
      'cachedUntil' => date('c', strtotime('+15 minutes', time())),
      'systems' => []
    ];

    foreach ($systems as $index => $system) {
      $systemData = $this->universe->getSystemById($system->solar_system_id);

      array_push($cache->systems, (object)[
        "solarSystemID" => $system->solar_system_id,
        "solarSystemName" => $systemData->name,
        "occupyingFactionID" => $system->occupier_faction_id,
        "owningFactionID" => $system->owner_faction_id,
        "occupyingFactionName" => $this->getFactionName($system->occupier_faction_id),
        "owningFactionName" => $this->getFactionName($system->owner_faction_id),
        "contested" => $system->contested,
        "victoryPoints" => $system->victory_points,
        "victoryPointThreshold" => $system->victory_points_threshold,
        "regionID" => $systemData->region_id,
        "region" => $this->universe->getRegionById($systemData->region_id)->name,
        "constellationID" => $systemData->constellation_id,
        "constellation" => $this->universe->getConstellationById($systemData->constellation_id)->name
      ]);
    }

    usort($cache->systems, function($a, $b){
      if ($a->victoryPoints == $b->victoryPoints) {
          return 0;
      }
      return ($a->victoryPoints < $b->victoryPoints) ? +1 : -1;
    });

    $this->cache->set($cache);
  }

  private function getFactionName ($factionId){
    return $this->factions->get((object)["id"=>$factionId])[0]->name;
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