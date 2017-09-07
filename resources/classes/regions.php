<?php

require_once(__DIR__ . "/cache.php");
require_once(__DIR__ . "/util.php");
require_once(__DIR__ . "/systems.php");
require_once(__DIR__ . "/factions.php");
require_once(__DIR__ . "/universe.php");

class Regions {
  private $util;
  private $cache;
  private $systems;
  private $factions;

  public function __construct(){
    $this->util = new Util();
    $this->cache = new FileCache('regions.json');
    $this->systems = new Systems();
    $this->factions = new Factions();
    $this->universe = new Universe(); 
  }

  public function updateCache(){

    $systems = $this->systems->get();

    $cache = (object)[
      'cachedUntil' => date('c', strtotime($systems->cachedUntil)),
      'regions' => (object)[]
    ];

    $fwSystemIds = [];
    $fwConstellationIds = [];
    foreach ($systems->systems as $index => $system) {
      array_push($fwSystemIds, $system->solarSystemID);
      array_push($fwConstellationIds, $system->constellationID);
    }

    foreach ($systems->systems as $index => $system) {
      $regionName = $system->region;

      if (!isset($cache->regions->$regionName)){
        $regionData = $this->universe->getRegionById($system->regionID);

        $constellationNames = [];
        foreach ($regionData->constellations as $index => $constellationId) {
          $constellationData = $this->universe->getConstellationById($constellationId);
          array_push($constellationNames, $constellationData->name);
        }

        $systemNames = [];
        foreach ($regionData->systems as $index => $systemId) {
          if (in_array($systemId, $fwSystemIds)) {
            $systemData = $this->universe->getSystemById($systemId);
            array_push($systemNames, $systemData->name);
          }
        }

        $cache->regions->$regionName = (object)[
          'name' => $regionName,
          'occupiedSystems' => (object)[
            'amarr' => 0,
            'minmatar' => 0,
            'gallente' => 0,
            'caldari' => 0
          ],
          'systems' => $systemNames,
          'victoryPointThreshold' => 0,
          'victoryPoints' => (object)[
            'amarr' => 0,
            'minmatar' => 0,
            'gallente' => 0,
            'caldari' => 0
          ],
          'volatility' => 0,
          'constellations' => $constellationNames
        ];
      }

      $occupyingFaction = $this->factions->get((object)['name' => $system->occupyingFactionName])[0];
      $attacker = $occupyingFaction->enemy;
      $defender = $occupyingFaction->shortname;

      $cache->regions->$regionName->victoryPointThreshold += $system->victoryPointThreshold;
      $cache->regions->$regionName->victoryPoints->$attacker += $system->victoryPoints;
      $cache->regions->$regionName->victoryPoints->$defender += $system->victoryPointThreshold - $system->victoryPoints;
      $cache->regions->$regionName->volatility += $system->victoryPoints;
      $cache->regions->$regionName->occupiedSystems->$defender++;
    }

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