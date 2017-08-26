<?php

require_once( __DIR__ . "/../../classes/cache.php");
require_once(__DIR__ . "/../../classes/util.php");

class Collection {
  private $util;
  private $cache;
  private $pricingRegionId = 10000002; // The Forge

  public function __construct($slackToken, $slackChannelId, $collection){
    $this->util = new Util();
    $this->cache = new FileCache('market/'.$slackToken.'/'.$slackChannelId.'/collections/'.strtolower($collection).'.json');
    $cache = $this->cache->get();
    if (is_null($cache)) {
      $cache = (object)[
        "name" => $collection
      ];
      $this->cache->set($cache);
    }
  }

  public function get(){
    return $this->cache->get();
  }

  public function add($search, $quantity, $price){

    $itemId = $this->search($search);
    $cache = $this->cache->get();

    if (strtolower($price) == 'jita'){
      $sellOrders = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/markets/' . $this->pricingRegionId
          . '/orders/?datasource=tranquility&order_type=sell&type_id=' . $itemId,
        (object)[]
      );
      $price = 0;
      foreach ($sellOrders as $index => $order) {
        $price = $order->price > $price ? $order->price : $price;
      }
    }

    if (is_string($price)){
      $price = floatval($price);
    }

    if (isset($cache->items)) {
      foreach ($cache->items as $index => $currentItem) {
        if ($currentItem->type_id == $itemId) {
          $currentItem->quantity = $currentItem->quantity + $quantity;
          $currentItem->price = floatval($price);
          $this->cache->set($cache);
          return $currentItem->name;
        }
      }
    } else {
      $cache->items = [];
    }

    // Tried to update price of existing item in collection but it's not in collection.
    if ($quantity == 0){
      return null;
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

  public function updatePrice($search, $price){
    return $this->add($search, 0, $price);
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

  public function remove($search, $quantity = 0){
    $itemId = $this->search($search);
    $cache = $this->cache->get();

    if (isset($cache->items)) {
      foreach ($cache->items as $index => $currentItem) {
        if ($currentItem->type_id == $itemId) {
          if ($quantity == 0){
            unset($cache->items[$index]);
          }
          else {
            $currentItem->quantity = $currentItem->quantity - $quantity;
            if ($currentItem->quantity < 1) {
              unset($cache->items[$index]);
            }
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