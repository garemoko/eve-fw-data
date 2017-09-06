<?php
require_once(__DIR__ . "/cache.php");
require_once(__DIR__ . "/util.php");
require_once(__DIR__ . "/factions.php");

class Systems {
  private $util;
  private $cache;

  public function __construct(){
    $this->util = new Util();
    $this->factions = new Factions();
    $this->cache = new FileCache('systems.json');
    $this->solarSystemsCache = new FileCache('systems-data.json'); 
  }

  public function updateCache(){

    date_default_timezone_set('UTC');
    $systems = $this->util->requestAndRetry('https://esi.tech.ccp.is/latest/fw/systems/?datasource=tranquility', []);
    $map = json_decode(file_get_contents(dirname(__DIR__) . '/fw-systems.json'));

    $cache = (object)[
      'cachedUntil' => date('c', strtotime('+15 minutes', time())),
      'systems' => []
    ];

    foreach ($systems as $index => $system) {
      $systemName = $this->getSystemName($system->solar_system_id);
      array_push($cache->systems, (object)[
        "solarSystemID" => $system->solar_system_id,
        "solarSystemName" => $systemName,
        "occupyingFactionID" => $system->occupier_faction_id,
        "owningFactionID" => $system->owner_faction_id,
        "occupyingFactionName" => $this->getFactionName($system->occupier_faction_id),
        "owningFactionName" => $this->getFactionName($system->owner_faction_id),
        "contested" => $system->contested,
        "victoryPoints" => $system->victory_points,
        "victoryPointThreshold" => $system->victory_points_threshold,
        "region" => $map->systems->$systemName->region,
        "constellation" => $map->systems->$systemName->constellation
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

  private function getSystemName ($systemId){
    $solarSystemsCache = $this->solarSystemsCache->get();
    if (is_null($solarSystemsCache)){
      $solarSystemsCache = (object)[];
    }
    if (!isset($solarSystemsCache->$systemId)){
      $solarSystemsCache->$systemId = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/universe/systems/'. $systemId 
          .'/?datasource=tranquility&language=en-us', 
        (object)[]
      );
      $this->solarSystemsCache->set($solarSystemsCache);
    }
    return $solarSystemsCache->$systemId->name;
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