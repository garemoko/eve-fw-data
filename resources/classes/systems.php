<?php
require_once(__DIR__ . "/cache.php");
require_once(__DIR__ . "/util.php");

class Systems {
  private $util;
  private $cache;

  public function __construct(){
    $this->util = new Util();
    $this->cache = new FileCache('systems.json');
  }

  public function updateCache(){

    date_default_timezone_set('UTC');
    $systems = $this->util->requestAndRetry('https://api.eveonline.com/map/FacWarSystems.xml.aspx', null, 'xml');
    $map = json_decode(file_get_contents(dirname(__DIR__) . '/fw-systems.json'));

    $cache = (object)[
      'cachedUntil' => date('c', strtotime($systems->cachedUntil)),
      'systems' => []
    ];

    foreach ($systems->result->rowset[0]->row as $index => $systemRaw) {
      $system = (object)[];
      foreach ($systemRaw->attributes() as $key => $value) {
        $system->$key = (String)$value;
      }
      $systemName = $system->solarSystemName;
      $system->region = $map->systems->$systemName->region;
      $system->constellation = $map->systems->$systemName->constellation;
      if ($system->occupyingFactionName == ''){
        $system->occupyingFactionName = $system->owningFactionName;
      }
      array_push($cache->systems, $system);
    }

    usort($cache->systems, function($a, $b){
      if ($a->victoryPoints == $b->victoryPoints) {
          return 0;
      }
      return ($a->victoryPoints < $b->victoryPoints) ? +1 : -1;
    });

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