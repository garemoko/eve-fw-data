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
        $station = $this->getStation($stationId);
        $cache->name = $station->name;
        $cache->station_id = $stationId;
        $cache->system_id = $station->system_id;
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

  private function getStation($stationId){
    $response = $this->util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/universe/stations/' . $stationId.'/',
      (object)[]
    );
    return $response;
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
      $this->buildStockList();
      return 'Order added at '.$cache->name.'.';
    }
    return 'Order already exists at '.$cache->name.'.';
  }

  public function cancelOrder($collectionName){
    $cache = $this->cache->get();
    if (!isset($cache->orders)){
      return 'Order not found at '.$cache->name.'.';
    }

    if (in_array($collectionName, $cache->orders)) {
      $cache->orders = array_diff($cache->orders, [$collectionName]);
      $this->cache->set($cache);
      if (count($cache->orders) == 0) {
        $this->delete();
      }
      $this->buildStockList();
      return 'Order cancelled at '.$cache->name.'.';
    }
    return 'Order not found at '.$cache->name.'.';
  }

  private function buildStockList(){
    $cache = $this->cache->get();
    $cache->stock = [];

    foreach ($cache->orders as $orderIndex => $collectionName) {
      $collection = new Collection($this->slackToken, $this->slackChannelId, $collectionName);
      foreach ($collection->get()->items as $collectionIndex => $item) {
        $itemInStock = false;
        foreach ($cache->stock as $stockIndex => $stockItem) {
          if ($stockItem->type_id == $item->type_id) {
            $itemInStock = true;
            $cache->stock[$stockIndex]->quantity += $item->quantity;
          }
        }
        if ($itemInStock == false){
          array_push($cache->stock, $item);
        }
      }
    }
    $this->cache->set($cache);
  }

  public function cacheMarket(){
    $this->buildStockList();
    $cache = $this->cache->get();

    if (!isset($cache->region_id)){
      $system = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/universe/systems/' . $cache->system_id . '/',
        []
      );
      $cache->constellation_id = $system->constellation_id;
      $constellation = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/universe/constellations/' . $system->constellation_id . '/',
        []
      );
      $cache->region_id = $constellation->region_id;
      $this->cache->set($cache);
    }

    $message = [];
    if (!isset($cache->cachedUntil) || $cache->cachedUntil < new DateTime()){
      $cache->cachedUntil = date('c', strtotime('+1 hour', time()));
      $cache->market = [];
      foreach ($cache->stock as $index => $stockItem) {
        $item = unserialize(serialize($stockItem));
        $orders = $this->util->requestAndRetry(
          'https://esi.tech.ccp.is/latest/markets/' . $cache->region_id . '/orders/?order_type=sell&type_id=' . $stockItem->type_id,
          []
        );
        $item->volume_remain = 0;
        foreach ($orders as $index => $order) {
          if ($order->location_id == $cache->station_id && $order->price <= $stockItem->price){
            $item->volume_remain += $order->volume_remain;
          }
        }
        $item->percent_remain = ($item->volume_remain / $item->quantity) * 100;
        array_push($cache->market, $item);
        array_push($message, $item->name . ' ' . $item->volume_remain . ' out of ' . $item->quantity . ' remaining.');
      }
      $this->cache->set($cache);
    }
    else {
      foreach ($cache->market as $index => $item) {
        array_push($message, $item->name . ' ' . $item->volume_remain . ' out of ' . $item->quantity . ' remaining.');
      }
    }
    return implode(PHP_EOL, $message);
  }

  public function delete(){
    $this->cache->delete();
  }
}