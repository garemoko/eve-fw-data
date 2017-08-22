<?php

require_once( __DIR__ . "/../../classes/cache.php");

class DashboardRegistry {
  private $cache;

  public function __construct(){
    $this->cache = new FileCache('market/dashboardregistry.json');
  }

  public function get(){
    return $this->cache->get();
  }

  public function add($slackToken, $slackChannelId){
    $cache = $this->get();
    if (is_null($cache)){
      $cache = (object)[
        'dashboards' => (object)[]
      ];
    }
    $id = uniqid('', true);

    $cache->dashboards->$id = (object)[
      'slackToken' => $slackToken,
      'slackChannelId' => $slackChannelId,
      'expires' => date('c', strtotime('+30 days', time()))
    ];
    $this->cache->set($cache);

    return 'http://evewarfare.com/market.php?id='.$id;
  }

  public function getById($id){
    $cache = $this->get();
    if (isset($cache->dashboards->$id)){
      return $cache->dashboards->$id;
    }
    return null;
  }

  public function removeExpired(){
    $cache = $this->get();
    if (is_null($cache)){
      return false;
    }
    foreach ($cache->dashboards as $id => $dashboard) {
      if ($dashboard->expires < new DateTime()){
        unset($cache->dashboards->$id);
      }
    }
    $this->cache->set($cache);
  }
}