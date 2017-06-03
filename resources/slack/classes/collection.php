<?php

require_once( __DIR__ . "/../../classes/cache.php");
require_once(__DIR__ . "/../../classes/util.php");

class Collection {
  private $util;
  private $cache;

  public function __construct($slackToken, $slackChannelId, $collection){
    $this->util = new Util();
    $this->cache = new FileCache('market/'.$slackToken.'/'.$slackChannelId.'/collections/'.$collection.'.json');
    $cache = $this->cache->get();
    if (is_null($cache)) {
      $cache = (object)[];
      $this->cache->set($cache);
    }
  }

  public function get(){
    return $this->cache->get();
  }

  public function add($search, $quantity, $price){

    $itemId = $this->search($search);
    $cache = $this->cache->get();

    if (isset($cache->items)) {
      foreach ($cache->items as $index => $currentItem) {
        if ($currentItem->type_id == $itemId) {
          $currentItem->quantity = $currentItem->quantity + $quantity;
          $currentItem->price = $price;
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
      'price' => $price,
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

  public function removeAll(){
    $cache = $this->cache->get();
    $cache->items = [];
    $this->cache->set($cache);
  }

  public function remove($search, $quantity){
    $itemId = $this->search($search);
    $cache = $this->cache->get();

    if (isset($cache->items)) {
      foreach ($cache->items as $index => $currentItem) {
        if ($currentItem->type_id == $itemId) {
          $currentItem->quantity = $currentItem->quantity - $quantity;
          if ($currentItem->quantity < 1) {
            unset($cache->items[$index]);
          }
          $this->cache->set($cache);
          return $currentItem->name;
        }
      }
    }
    return null;
  }

  public function delete(){
    $this->cache->delete();
  }

  private function search($search){
    $response = $this->util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/search/?categories=inventorytype&strict=true&search=' . urlencode($search),
      []
    );
    if (count($response->inventorytype) > 0){
      return array_pop($response->inventorytype);
    }
    else {
      return null;
    };
  }

}