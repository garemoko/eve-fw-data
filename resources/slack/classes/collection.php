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

  public function addZKill($zKillBoardUrl, $quantity){
    //https://zkillboard.com/api/killID/64283338/
    $killURL = str_replace('/kill/','/api/killID/',$zKillBoardUrl);
    $kill = $this->util->requestAndRetry(
      $killURL,
      (object)[]
    )[0];
    if (is_null($kill)){
      return null;
    }

    $cfg = (object)[
      'itemId' => $kill->victim->shipTypeID,
      'quantity' => $quantity,
      'useJitaPrice' => true
    ];
    $this->addToCollection($cfg);

    foreach ($kill->items as $index => $item) {
      $cfg = (object)[
        'itemId' => $item->typeID,
        'quantity' => ($item->qtyDropped + $item->qtyDestroyed) * $quantity,
        'useJitaPrice' => true
      ];
      $this->addToCollection($cfg);
    }
    return true;
  }

   public function add($search, $quantity, $price){
    $cfg = (object)[
    ];
    
    if ($price === 'jita'){
      $cfg->useJitaPrice = true;
    } else {
      $cfg->useJitaPrice = false;
      $cfg->price = $price;
    }

    $cfg->search = $search;
    $cfg->quantity = $quantity;

    return $this->addToCollection($cfg);
   }

  /*
    $cfg
      $search - term to search for if itemId is unknown
      $itemId - required if search is null
      $quantity
      $useJitaPrice = false
      $price - required if $useJitaPrice is false
  */
  private function addToCollection($cfg){

    // Determine the item id
    if (isset($cfg->itemId) && !is_null($cfg->itemId)){
      $itemId = $cfg->itemId;
    }
    elseif (isset($cfg->search) && !is_null($cfg->search)){
      $itemId = $this->search($cfg->search);
      if (is_null($itemId)){
        return null;
      }
    } 
    else {
      // Cannot determine itemId;
      return null;
    }

    // Determine the max price
    $price = 0;
    if ($cfg->useJitaPrice){
      $sellOrders = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/markets/' . $this->pricingRegionId
          . '/orders/?datasource=tranquility&order_type=sell&type_id=' . $itemId,
        (object)[]
      );
      foreach ($sellOrders as $index => $order) {
        $price = $order->price > $price ? $order->price : $price;
      }
    } 
    elseif (isset($cfg->price) && !is_null($cfg->price)){
      $price = $cfg->price;
    }
    if (is_string($price)){
      $price = floatval($price);
    }
    if ($price == 0) {
      // Invalid or undetermined price.
      return null;
    }

    // Nothing special here. 
    $quantity = intval($cfg->quantity);

    $cache = $this->cache->get();

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

  public function updateAll(){
    $cache = $this->cache->get();
    if (isset($cache->items)) {
      foreach ($cache->items as $index => $currentItem) {
        $cfg = (object)[
          'itemId' => $currentItem->type_id,
          'quantity' => 0,
          'useJitaPrice' => true
        ];
        $this->addToCollection($cfg);
      }
    }
    else {
      return null;
    }
    return true;
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