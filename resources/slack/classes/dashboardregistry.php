<?php

require_once( __DIR__ . "/../../classes/cache.php");

class DashboardRegistry {
  private $cache;
  private $type;

  public function __construct($type){
    $this->type = $type;
    $this->cache = new FileCache($type.'/dashboardregistry.json');
  }

  public function get(){
    return $this->cache->get();
  }

  public function add($slackToken, $slackChannelId, $expiry){
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
      'expires' => date('c', strtotime($expiry, time()))
    ];
    $this->cache->set($cache);

    return 'http://evewarfare.com/'.$this->type.'.php?id='.$id;
  }

  public function expireAll($slackToken, $slackChannelId){
    $cache = $this->get();
    if (is_null($cache)){
      $cache = (object)[
        'dashboards' => (object)[]
      ];
    }
    $return = false;
    foreach ($cache->dashboards as $id => $dashboard) {
      if ($dashboard->slackToken ==  $slackToken && $dashboard->slackChannelId ==  $slackChannelId){
        unset($cache->dashboards->$id);
        $return = true;
      }
    }
    $this->cache->set($cache);
    return $return;
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