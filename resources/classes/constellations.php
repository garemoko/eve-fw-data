<?php

require_once( __DIR__ . "/cache.php");
require_once(__DIR__ . "/util.php");
require_once(__DIR__ . "/systems.php");
require_once(__DIR__ . "/factions.php");
require_once(__DIR__ . "/universe.php");

class Constellations {
  private $util;
  private $cache;
  private $systems;
  private $factions;

  public function __construct(){
    $this->util = new Util();
    $this->cache = new FileCache('constellations.json');
    $this->systems = new Systems();
    $this->factions = new Factions();
    $this->universe = new Universe(); 
  }

  public function updateCache(){

    $systems = $this->systems->get();

    $cache = (object)[
      'cachedUntil' => date('c', strtotime($systems->cachedUntil)),
      'constellations' => (object)[]
    ];

    $fwSystemIds = [];
    foreach ($systems->systems as $index => $system) {
      array_push($fwSystemIds, $system->solarSystemID);
    }

    foreach ($systems->systems as $index => $system) {
      $constellationName = $system->constellation;

      if (!isset($cache->constellations->$constellationName)){
        $constellationData = $this->universe->getConstellationById($system->constellationID);
        $regionData = $this->universe->getRegionById($system->regionID);

        $systemNames = [];
        foreach ($constellationData->systems as $index => $systemId) {
          if (in_array($systemId, $fwSystemIds)) {
            $systemData = $this->universe->getSystemById($systemId);
            array_push($systemNames, $systemData->name);
          }
        }

        $cache->constellations->$constellationName = (object)[
          'name' => $constellationName,
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
          'region' => $regionData->name
        ];
      }

      $occupyingFaction = $this->factions->get((object)['name' => $system->occupyingFactionName])[0];
      $attacker = $occupyingFaction->enemy;
      $defender = $occupyingFaction->shortname;

      $cache->constellations->$constellationName->victoryPointThreshold += $system->victoryPointThreshold;
      $cache->constellations->$constellationName->victoryPoints->$attacker += $system->victoryPoints;
      $cache->constellations->$constellationName->victoryPoints->$defender += $system->victoryPointThreshold - $system->victoryPoints;
      $cache->constellations->$constellationName->volatility += $system->victoryPoints;
      $cache->constellations->$constellationName->occupiedSystems->$defender++;
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