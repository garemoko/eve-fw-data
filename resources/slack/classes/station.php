<?php

require_once( __DIR__ . "/../../classes/cache.php");
require_once(__DIR__ . "/../../classes/util.php");
require_once(__DIR__ . "/collection.php");

class Station {
  private $util;
  private $cache;
  private $slackToken;
  private $slackChannelId;

  public function __construct($slackToken, $slackChannelId, $station){
    $this->slackToken = $slackToken;
    $this->slackChannelId = $slackChannelId;

    $this->util = new Util();
    $stationId = $this->search($station);
    if (!is_null($stationId)){
      $this->cache = new FileCache('market/'.$slackToken.'/'.$slackChannelId.'/station/'.$stationId.'.json');
      $cache = $this->cache->get();
      if (is_null($cache)) {
        $cache = (object)[];
        $this->cache->set($cache);
      }
    }
  }

  public function get(){
    return $this->cache->get();
  }

  private function search($search){
    $response = $this->util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/search/?categories=station&strict=true&search=' . urlencode($search),
      []
    );
    if (count($response->station) > 0){
      return array_pop($response->station);
    }
    else {
      return null;
    };
  }

  public function addOrder($collectionName){
    $collection = new Collection($this->slackToken, $this->slackChannelId, $collectionName);
    $cache = $this->cache->get();

    if (!isset($collection->get()->items)){
      $collection->delete();
      return 'Collection not found.';
    }

    if (!isset($cache->orders)){
      $cache->orders = [];
    }

    if (!in_array($collectionName, $cache->orders)) {
      array_push($cache->orders, $collectionName);
      $this->cache->set($cache);
      return 'Order added.';
    }
    return 'Order already exists.';
  }

}