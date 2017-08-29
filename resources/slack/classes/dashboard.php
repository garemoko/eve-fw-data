<?php

require_once( __DIR__ . "/../../classes/cache.php");
require_once(__DIR__ . "/station.php");
require_once(__DIR__ . "/dashboardregistry.php");

class Dashboard {
  private $util;
  private $cache;
  private $slackToken;
  private $slackChannelId;

  public function __construct($slackToken, $slackChannelId){
    $this->slackToken = $slackToken;
    $this->slackChannelId = $slackChannelId;
    $this->cache = new FileCache('market/'.$slackToken.'/'.$slackChannelId.'/dashboard.json');
  }

  public function getURL($expiry = "+30 days"){
    if (trim($expiry) === ""){
      $expiry = "+30 days";
    }
    $dashboardRegistry = new DashboardRegistry();
    // Pre-cache the dashboard
    $this->get();
    return $dashboardRegistry->add($this->slackToken, $this->slackChannelId, $expiry);
  }

  public function expireAll(){
    $dashboardRegistry = new DashboardRegistry();
    return $dashboardRegistry->expireAll($this->slackToken, $this->slackChannelId);
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

  private function jsonDirectoryToArray($path){
    $array = [];
    if (file_exists($path)){
      $files = array_diff(scandir($path), ['.', '..']);
      foreach ($files as $index => $file) {
        $fileArr = explode('.', $file);
        if (end($fileArr) === 'json') {
          $data = json_decode(file_get_contents($path . $file));
          array_push($array, $data);
        }
      }
    }
    return $array;
  }

}