<?php

require_once( __DIR__ . "/../../classes/cache.php");
require_once(__DIR__ . "/../../classes/util.php");

class Collection {
  private $util;
  private $cache;

  public function __construct($slackToken, $collection){
    $this->util = new Util();
    $this->cache = new FileCache('market/'.$slackToken.'/collections/'.$collection.'.json');
    $cache = $this->cache->get();
    if (is_null($cache)) {
      $cache = (object)[
        'cachedUntil' => date('c', strtotime('+1 hour', time()))
      ];
      $this->cache->set($cache);
    }
  }

  public function updateCache(){
    $cache = (object)[
      'cachedUntil' => date('c', strtotime('+1 hour', time()))
    ];

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

  public function add($search, $quantity){
    $response = $this->util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/search/?categories=inventorytype&strict=true&search=' . urlencode($search),
      []
    );
    if (count($response->inventorytype) > 0){
       $itemId = array_pop($response->inventorytype);
    }
    else {
      return null;
    };

    $cache = $this->cache->get();

    if (isset($cache->items)) {
      foreach ($cache->items as $index => $currentItem) {
        if ($currentItem->type_id == $itemId) {
          $currentItem->quantity = $currentItem->quantity + $quantity;
          $this->cache->set($cache);
          return $currentItem->name;
        }
      }
    } else {
      $cache->items = [];
    }

    $result = $this->util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/universe/types/' . $itemId.'/',
      (object)[]
    );
    $item = (object)[
      'quantity' => intval($quantity),
      'type_id' => $result->type_id,
      'name' => $result->name
    ];

    array_push($cache->items, $item);
    $this->cache->set($cache);
    return $item->name;
  }

  public function getList(){
    $cache = $this->cache->get();
    $list = [];
    if (isset($cache->items)) {
      foreach ($cache->items as $index => $currentItem) {
        array_push($list, $currentItem->name . ' x' . $currentItem->quantity);
      }
    }
    return implode(', ', $list);
  }

}

/*
get orders by region then for each order grab the station via the location id. See if the station's system id matches the configured station. 
*/