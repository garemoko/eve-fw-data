<?php

require_once("classes/cache.php");
require_once("classes/util.php");
require_once("classes/systems.php");
require_once("classes/factions.php");

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
  }

  public function updateCache(){

    $systems = $this->systems->get();
    $map = json_decode(file_get_contents('fw-systems.json'));

    $cache = (object)[
      'cachedUntil' => date('c', strtotime($systems->cachedUntil)),
      'constellations' => (object)[]
    ];

    foreach ($systems->systems as $index => $system) {
      $constellationName = $system->constellation;
      if (!isset($cache->constellations->$constellationName)){
        $cache->constellations->$constellationName = (object)[
          'name' => $constellationName,
          'occupiedSystems' => (object)[
            'amarr' => 0,
            'minmatar' => 0,
            'gallente' => 0,
            'caldari' => 0
          ],
          'systems' => $map->constellations->$constellationName->systems,
          'victoryPointThreshold' => 0,
          'victoryPoints' => (object)[
            'amarr' => 0,
            'minmatar' => 0,
            'gallente' => 0,
            'caldari' => 0
          ],
          'volatility' => 0,
          'region' => $map->constellations->$constellationName->region
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