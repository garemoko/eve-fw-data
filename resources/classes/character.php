<?php

require_once( __DIR__ . "/cache.php");
require_once(__DIR__ . "/util.php");
date_default_timezone_set('UTC');

class Character {
  private $util;
  private $cache;
  private $characterId;

  public function __construct($characterId){
    $this->util = new Util();
    $this->cache = new FileCache('characters/'.$characterId.'.json');
    $this->characterId = $characterId;
  }

  public function updateCache(){
    $cache = (object)[
        'cachedUntil' => date('c', strtotime('+30 minutes', time())),
    ];

    $cache->character = $this->util->requestAndRetry('https://esi.tech.ccp.is/latest/characters/'.$this->characterId.'/', null);
    $cache->portrait = $this->util->requestAndRetry('https://esi.tech.ccp.is/latest/characters/'.$this->characterId.'/portrait/', null);
    $cache->corp = $this->util->requestAndRetry('https://esi.tech.ccp.is/latest/corporations/'.$cache->character->corporation_id.'/', null);
    $cache->corp->id = $cache->character->corporation_id;
    if (isset($cache->corp->alliance_id)){
      $cache->alliance = $this->util->requestAndRetry('https://esi.tech.ccp.is/latest/alliances/'.$cache->corp->alliance_id.'/', null);
      $cache->alliance->id = $cache->corp->alliance_id;
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