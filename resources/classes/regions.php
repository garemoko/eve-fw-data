<?php

require_once("classes/cache.php");
require_once("classes/util.php");
require_once("classes/systems.php");
require_once("classes/factions.php");

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
  }

  public function updateCache(){

    $systems = $this->systems->get();
    $map = json_decode(file_get_contents('fw-systems.json'));

    $cache = (object)[
      'cachedUntil' => date('c', strtotime($systems->cachedUntil)),
      'regions' => (object)[]
    ];

    foreach ($systems->systems as $index => $system) {
      $regionName = $system->region;
      if (!isset($cache->regions->$regionName)){
        $cache->regions->$regionName = (object)[
          'name' => $regionName,
          'occupiedSystems' => (object)[
            'amarr' => 0,
            'minmatar' => 0,
            'gallente' => 0,
            'caldari' => 0
          ],
          'systems' => $map->regions->$regionName->systems,
          'victoryPointThreshold' => 0,
          'victoryPoints' => (object)[
            'amarr' => 0,
            'minmatar' => 0,
            'gallente' => 0,
            'caldari' => 0
          ],
          'volatility' => 0,
          'constellations' => $map->regions->$regionName->constellations
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