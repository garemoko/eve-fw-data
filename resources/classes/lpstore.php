<?php
require_once(__DIR__ . "/cache.php");
require_once(__DIR__ . "/util.php");

class LPStore {
  private $util;
  private $cache;
  private $workingCache;
  private $corpId;

  public function __construct($corpId){
    $this->util = new Util();
    $this->cache = new FileCache('lpstore_'.$corpId.'.json');
    $this->workingCache = new FileCache('lpstore_'.$corpId.'_temp.json');
    $this->corpId = $corpId;
  }

  public function updateCache(){
    ini_set('max_execution_time', 300);
    $workingCache = $this->workingCache->get();
    if (!isset($workingCache->completed) || $workingCache->completed == true){
      $workingCache = (object)[
        'cachedUntil' => date('c', strtotime('+1 day', time())),
        'completed' => false,
      ];
    }

    if (!isset($workingCache->lpStore)){
      $workingCache->lpStore = $this->util->requestAndRetry('https://esi.tech.ccp.is/v1/loyalty/stores/' . $this->corpId . '/offers/', []);
      $this->workingCache->set($workingCache);
    }
    foreach ($workingCache->lpStore as $index => $item) {
      if (!isset($item->type_name)){
        $item = $this->updateLPStoreItemNames($item);
        foreach ($item->required_items as $requiredIndex => $requiredItem) {
          $requiredItem = $this->updateLPStoreItemNames($requiredItem);
        }
      }
    }

    // Caching completed.
    $workingCache->completed = true;
    $this->cache->set($workingCache);
    $this->workingCache->delete();
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
    return $item;
  }
}