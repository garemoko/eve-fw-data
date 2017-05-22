<?php
require_once("classes/cache.php");
require_once("classes/util.php");
require_once("classes/lpstore.php");

class LPExchange {
  private $pricingRegionId = 10000002; // The Forge
  private $minRequiredOrderSize = 100;

  private $util;
  private $cache;
  private $workingCache;
  private $LPStore;

  public function __construct($corpId){
    $this->util = new Util();
    $this->cache = new FileCache('lpexchange_'.$corpId.'.json');
    $this->workingCache = new FileCache('lpexchange_'.$corpId.'_temp.json');
    $this->corpId = $corpId;
    $this->lpStore = new LPStore($corpId);
  }

  public function updateCache(){
    ini_set('max_execution_time', 300);
    $workingCache = (object)[
      'cachedUntil' => date('c', strtotime('+1 hour', time())),
      'completed' => false,
    ];

    $lpStore = $this->lpStore->get()->lpStore;
    $workingCache->lpStore = $lpStore;
    $this->workingCache->set($workingCache);
    foreach ($lpStore as $index => $item) {
      $this->updateLPStoreItemPrices($item);
      foreach ($item->required_items as $requiredIndex => $requiredItem) {
        $this->updateLPStoreItemPrices($requiredItem);
      }
    }

    // Calculate exchange rates
    foreach ($lpStore as $index => $item) {
      $this->updateLPStoreItemExchange($item);
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
    $this->workingCache->delete();
    $this->cache->set($workingCache);
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

    $highestBuy = 0.01;
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
    $item->highest_buy = $highestBuy;

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

    $lowestSell = 1000000000000000; // Default to 1000 trillion ISK if not enough items on sale.
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
    $item->lowest_sell = $lowestSell;

    $this->updateItemOrRequiredItem($item);
  }

  private function updateLPStoreItemExchange($item){
    $item->exchange = (object)[];

    $totalRequiredItemCost = 0;
    foreach ($item->required_items as $index => $required_item) {
      $totalRequiredItemCost += $required_item->lowest_sell * $required_item->quantity;
    }
    $item->exchange->immediate = (
      ($item->highest_buy * $item->quantity) - $totalRequiredItemCost - $item->isk_cost
    ) / $item->lp_cost;

    $totalRequiredItemCost = 0;
    foreach ($item->required_items as $index => $required_item) {
      $totalRequiredItemCost += $required_item->highest_buy * $required_item->quantity;
    }
    $item->exchange->delayed = (
      ($item->lowest_sell * $item->quantity) - $totalRequiredItemCost - $item->isk_cost
    ) / $item->lp_cost;

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