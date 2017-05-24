<?php
require_once("classes/cache.php");
require_once("classes/util.php");
require_once("classes/lpstore.php");

class LPExchange {
  private $pricingRegionId = 10000002; // The Forge
  private $minRequiredOrderSize = 100;

  private $util;
  private $cache;
  private $fallbackCache;
  private $workingCache;
  private $LPStore;

  public function __construct($corpId){
    $this->util = new Util();
    $this->cache = new FileCache('lpexchange_'.$corpId.'.json');
    $this->workingCache = new FileCache('lpexchange_'.$corpId.'_temp.json');
    $this->fallbackCache = new FileCache('lpexchange_'.$corpId.'_fallback.json');
    $this->corpId = $corpId;
    $this->lpStore = new LPStore($corpId);
  }

  public function updateCache(){
    ini_set('max_execution_time', 300);

    // If there is a working cache, the previous process broke part way so restart.
    // If the working cache is corrupted/missing but a fallback cache exists, use that.
    $workingCache = $this->workingCache->get();
    $fallbackCache = $this->fallbackCache->get();
    if (is_null($workingCache)){
      if (is_null($fallbackCache)){
        $workingCache = (object)[
          'cachedUntil' => date('c', strtotime('+1 hour', time())),
          'completed' => false,
        ];
      } 
      else {
        $workingCache = $fallbackCache;
      }
    }
    $this->fallbackCache->set($workingCache);


    $requiredItemsChecked = [];
    if (!isset($workingCache->lpStore)){
      $workingCache->lpStore = $this->lpStore->get()->lpStore;
      $this->workingCache->set($workingCache);
    }
    foreach ($workingCache->lpStore as $index => $item) {
      if (!isset($item->highest_buy)){
        $this->updateLPStoreItemPrices($item);
        foreach ($item->required_items as $requiredIndex => $requiredItem) {
          if (!in_array($requiredItem->type_id, $requiredItemsChecked)) {
            array_push($requiredItemsChecked, $requiredItem->type_id);
            $this->updateLPStoreItemPrices($requiredItem);
          }
        }
      }
    }

    // Calculate exchange rates
    foreach ($workingCache->lpStore as $index => $item) {
      if (!isset($item->exchange)){
        $this->updateLPStoreItemExchange($item);
      }
    }

    $workingCache = $this->workingCache->get();
    // Sort by exchange
    usort($workingCache->lpStore, function($a, $b){
      if ($a->exchange->immediate == $b->exchange->immediate) {
          return 0;
      }
      return ($a->exchange->immediate < $b->exchange->immediate) ? +1 : -1;
    });

    // Caching completed.
    $workingCache->completed = true;
    $this->cache->set($workingCache);
    $this->workingCache->delete();
    $this->fallbackCache->delete();
  }

  public function get(){
    return $this->cache->get();
  }

  private function updateLPStoreItemPrices($item){
    $buyOrders = $this->util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/markets/' . $this->pricingRegionId
        . '/orders/?datasource=tranquility&order_type=buy&type_id=' . $item->type_id,
      (object)[]
    );

    usort($buyOrders, function($a, $b){
      if ($a->price == $b->price) {
          return 0;
      }
      return ($a->price < $b->price) ? +1 : -1;
    });

    $highestBuy = null;
    $volumeMatched = 0;
    foreach ($buyOrders as $index => $order) {
      if(($order->volume_remain + $volumeMatched) > $this->minRequiredOrderSize){
        $highestBuy = $order->price;
        break;
      }
      else {
        $volumeMatched += $order->volume_remain;
      }
    }
    if (is_null($highestBuy)) {
      $item->highest_buy = null;
      $item->highest_buy_delayed = null;
    }
    else {
      $item->highest_buy = $highestBuy;
      $item->highest_buy_delayed = $buyOrders[0]->price;
    }

    $sellOrders = $this->util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/markets/' . $this->pricingRegionId
        . '/orders/?datasource=tranquility&order_type=sell&type_id=' . $item->type_id,
      (object)[]
    );

    usort($sellOrders, function($a, $b){
      if ($a->price == $b->price) {
          return 0;
      }
      return ($a->price > $b->price) ? +1 : -1;
    });

    $lowestSell = null; // Default to null if not enough items on sale.
    $volumeMatched = 0;
    foreach ($sellOrders as $index => $order) {
      if(($order->volume_remain + $volumeMatched) > $this->minRequiredOrderSize){
        $lowestSell = $order->price;
        break;
      }
      else {
        $volumeMatched += $order->volume_remain;
      }
    }
    if (is_null($lowestSell)) {
      $item->lowest_sell = null;
      $item->lowest_sell_delayed = null;
    }
    else {
      $item->lowest_sell = $lowestSell;
      $item->lowest_sell_delayed = $sellOrders[0]->price;
    }

    $this->updateItemOrRequiredItem($item);
  }

  private function updateLPStoreItemExchange($item){
    $item->exchange = (object)[];

    $totalRequiredItemCost = 0;
    foreach ($item->required_items as $index => $required_item) {
      if (is_null($required_item->lowest_sell)){
        $item->exchange->immediate = 0;
        break;
      }
      $totalRequiredItemCost += $required_item->lowest_sell * $required_item->quantity;
    }
    if (!isset($item->exchange->immediate)){
      if (is_null($item->highest_buy)){
        $item->exchange->immediate = 0;
      }
      else {
        $item->exchange->immediate = (
          ($item->highest_buy * $item->quantity) - $totalRequiredItemCost - $item->isk_cost
        ) / $item->lp_cost;
      }
    }

    $totalRequiredItemCost = 0;
    foreach ($item->required_items as $index => $required_item) {
      if (is_null($required_item->highest_buy_delayed)){
        $item->exchange->delayed = 0;
        break;
      }
      $totalRequiredItemCost += $required_item->highest_buy_delayed * $required_item->quantity;
    }
    if (!isset($item->exchange->delayed)){
      if (is_null($item->lowest_sell_delayed)){
        $item->exchange->delayed = 0;
      }
      else {
        $item->exchange->delayed = (
          ($item->lowest_sell_delayed * $item->quantity) - $totalRequiredItemCost - $item->isk_cost
        ) / $item->lp_cost;
      }
    }
    $this->updateItem($item);
  }

  private function updateItem($item){
    $workingCache = $this->workingCache->get();
    foreach ($workingCache->lpStore as $storeIndex => $storeItem) {
      if($storeItem->type_id === $item->type_id && isset($item->lp_cost) && isset($item->isk_cost)) {
        $workingCache->lpStore[$storeIndex] = clone $item;
      }
    }
    $this->workingCache->set($workingCache);
  }

  private function updateItemOrRequiredItem($item){
    $workingCache = $this->workingCache->get();
    foreach ($workingCache->lpStore as $storeIndex => $storeItem) {
      if($storeItem->type_id === $item->type_id && isset($item->lp_cost) && isset($item->isk_cost)) {
        $workingCache->lpStore[$storeIndex] = clone $item;
      }
      foreach ($storeItem->required_items as $requiredIndex => $requiredItem) {
        if($requiredItem->type_id === $item->type_id) {
          $workingCache->lpStore[$storeIndex]->required_items[$requiredIndex] = clone $item;
        }
      }
    }
    $this->workingCache->set($workingCache);
  }
}