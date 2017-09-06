<?php
require_once(__DIR__ . "/cache.php");
require_once(__DIR__ . "/util.php");
require_once(__DIR__ . "/systems.php");
require_once(__DIR__ . "/factions.php");


Class FactionStats {
  private $util;
  private $cache;
  private $workingCache;

  public function __construct(){
    $this->util = new Util();
    $this->cache = new FileCache('factionstats.json');
    $this->systems = new Systems();
    $this->factions = new Factions();
  }

  public function updateCache(){
    date_default_timezone_set('UTC');
    $factionStats = $this->util->requestAndRetry('https://esi.tech.ccp.is/latest/fw/stats/?datasource=tranquility', null);

    $cache = (object)[
      'cachedUntil' => date('c', strtotime('+15 minutes', time())),
      'totals' => (object)[
        "killsYesterday" => 0 ,
        "killsLastWeek" => 0,
        "killsTotal" => 0,
        "victoryPointsYesterday" => 0,
        "victoryPointsLastWeek" => 0,
        "victoryPointsTotal" => 0
      ],
      'factions' => []
    ];

    foreach ($factionStats as $index => $factionStat) {
      $faction = (object)[
        "factionID" => $factionStat->faction_id,
        "factionName" => $this->getFactionName($factionStat->faction_id),
        "pilots" => $factionStat->pilots,
        "systemsControlled" => $factionStat->systems_controlled,
        "killsYesterday" => $factionStat->kills->yesterday,
        "killsLastWeek" => $factionStat->kills->last_week,
        "killsTotal" => $factionStat->kills->total,
        "victoryPointsYesterday" => $factionStat->victory_points->yesterday,
        "victoryPointsLastWeek" => $factionStat->victory_points->last_week,
        "victoryPointsTotal" => $factionStat->victory_points->total
      ];
      array_push($cache->factions, $faction);
    }

    $systems = $this->systems->get();
    $map = json_decode(file_get_contents('fw-systems.json'));

    foreach ($cache->factions as $index => $faction) {
      $faction->victoryPoints = 0;
      $faction->victoryPointThreshold = 0;
      $faction->occupiedSystems = 0;
      $faction->occupiedSystemsThreshold = 0;
      $faction->shortName = $this->factions->get((object)['name' => $faction->factionName])[0]->shortname;
      $faction->enemy = $this->factions->get((object)['name' => $faction->factionName])[0]->enemy;
    }

    foreach ($systems->systems as $index => $system) {
      $occupyingFaction = $this->factions->get((object)['name' => $system->occupyingFactionName])[0];
      $attackingFaction = $this->factions->get((object)['shortname' => $occupyingFaction->enemy])[0];

      foreach ($cache->factions as $index => $faction) {
        if ($attackingFaction->name == $faction->factionName){
          $faction->victoryPoints += $system->victoryPoints;
          $faction->victoryPointThreshold += $system->victoryPointThreshold;
          $faction->occupiedSystemsThreshold++;
        }
        else if ($occupyingFaction->name == $faction->factionName){
          $faction->victoryPointThreshold += $system->victoryPointThreshold;
          $faction->victoryPoints += $system->victoryPointThreshold - $system->victoryPoints;
          $faction->occupiedSystems++;
          $faction->occupiedSystemsThreshold++;
        }
      }
    }

    foreach ($cache->factions as $index => $faction) {
      $faction->victoryPointsPercent = 100 * $faction->victoryPoints / $faction->victoryPointThreshold;
      $faction->occupiedSystemsPercent = 100 * $faction->occupiedSystems / $faction->occupiedSystemsThreshold;
      $faction->victoryIndex = $faction->victoryPointsPercent - $faction->occupiedSystemsPercent;
    }

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