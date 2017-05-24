<?php
require_once("classes/cache.php");
require_once("classes/util.php");

class LPStore {
  private $util;
  private $cache;
  private $workingCache;
  private $corpId;

  public function __construct($corpId){
    $this->util = new Util();
    $this->cache = new FileCache('lpstore_'.$corpId.'.json');
    $this->corpId = $corpId;
  }

  public function updateCache(){
    ini_set('max_execution_time', 300);
    $this->workingCache = (object)[
      'cachedUntil' => date('c', strtotime('+1 day', time()))
    ];

    $lpStore = $this->util->requestAndRetry('https://esi.tech.ccp.is/v1/loyalty/stores/' . $this->corpId . '/offers/', []);
    $this->workingCache->lpStore = $lpStore;
    foreach ($lpStore as $index => $item) {
      $this->updateLPStoreItemNames($item);
      foreach ($item->required_items as $requiredIndex => $requiredItem) {
        $this->updateLPStoreItemNames($requiredItem);
      }
    }

    // Caching completed.
    $this->cache->set($this->workingCache);
  }

  public function get(){
    return $this->cache->get();
  }

  private function updateLPStoreItemNames($item){
    $itemInfo = $this->util->requestAndRetry(
      'https://esi.tech.ccp.is/v1/universe/types/' . $item->type_id . '/?datasource=tranquility&language=en-us',
      (object)[
        'type_name' => 'unknown item',
        'type_description' => 'unknown item'
      ]
    );
    $item->type_name = $itemInfo->type_name;
    $item->type_description = $itemInfo->type_description;
    $this->updateItem($item);
  }

  private function updateItem($item){
    foreach ($this->workingCache->lpStore as $storeIndex => $storeItem) {
      if($storeItem->type_id === $item->type_id && isset($item->lp_cost) && isset($item->isk_cost)) {
        $this->workingCache->lpStore[$storeIndex] = clone $item;
      }
      foreach ($storeItem->required_items as $requiredIndex => $requiredItem) {
        if($requiredItem->type_id === $item->type_id) {
          $this->workingCache->lpStore[$storeIndex]->required_items[$requiredIndex] = clone $item;
        }
      }
    }
  }
}