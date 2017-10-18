<?php

require_once(__DIR__ . "/dashboard.php");
require_once(__DIR__ . "/station.php");

class MarketDashboard extends Dashboard {

  public function __construct($slackToken, $slackChannelId){
    $this->type = 'market';
    $this->slackToken = $slackToken;
    $this->slackChannelId = $slackChannelId;
    $this->cache = new FileCache('market/'.$slackToken.'/'.$slackChannelId.'/dashboard.json');
  }

  public function get(){
    $cache = $this->cache->get();
    if (!is_null($cache) && isset($cache->cachedUntil) && $cache->cachedUntil > new DateTime()){
      return $cache;
    }
    if (is_null($cache)){
      $cache = (object)[];
    }
    $cache->cachedUntil = date('c', strtotime('+5 minutes', time()));
    $path = __DIR__ . '/../../files/market/'.$this->slackToken.'/'.$this->slackChannelId.'/';
    $cache->collections = $this->jsonDirectoryToArray($path . 'collections/');
    $cache->stations = $this->jsonDirectoryToArray($path . 'station/');

    // Loop through stations to update their market cache
    foreach ($cache->stations as $index => $currentStation) {
      $station = new Station($this->slackToken, $this->slackChannelId, (object)[
        'station_id' => $currentStation->station_id
      ]);
      $station->cacheMarket();
      $cache->stations[$index] = $station->get();
    }

    $this->cache->set($cache);
    return $cache;
  }

}