<?php

require_once( __DIR__ . "/cache.php");
require_once(__DIR__ . "/util.php");
date_default_timezone_set('UTC');

class MineralPrices {
  private $util;
  private $cache;
  private $mineralsData;

  public function __construct(){
    $this->util = new Util();
    $this->cache = new FileCache('mineralprices.json');
    $this->mineralsData = (object)[
      '34' => (object)[
        'name' => 'Tritanium',
        'size' => 0.01
      ],
      '35' => (object)[
        'name' => 'Pyerite',
        'size' => 0.01
      ], 
      '36' => (object)[
        'name' => 'Mexallon',
        'size' => 0.01
      ], 
      '37' => (object)[
        'name' => 'Isogen',
        'size' => 0.01
      ], 
      '38' => (object)[
        'name' => 'Nocxium',
        'size' => 0.01
      ], 
      '40' => (object)[
        'name' => 'Megacyte',
        'size' => 0.01
      ], 
      '39' => (object)[
        'name' => 'Zydrine',
        'size' => 0.01
      ], 
      '11399' => (object)[
        'name' => 'Morphite',
        'size' => 0.01
      ], 
      '16634' => (object)[
        'name' => 'Atmospheric Gases',
        'size' => 0.05
      ], 
      '16643' => (object)[
        'name' => 'Cadmium',
        'size' => 0.05
      ], 
      '16647' => (object)[
        'name' => 'Caesium',
        'size' => 0.05
      ], 
      '16641' => (object)[
        'name' => 'Chromium',
        'size' => 0.05
      ], 
      '16640' => (object)[
        'name' => 'Cobalt',
        'size' => 0.05
      ], 
      '16650' => (object)[
        'name' => 'Dysprosium',
        'size' => 0.05
      ], 
      '16635' => (object)[
        'name' => 'Evaporite Deposits',
        'size' => 0.05
      ], 
      '16648' => (object)[
        'name' => 'Hafnium',
        'size' => 0.05
      ], 
      '16633' => (object)[
        'name' => 'Hydrocarbons',
        'size' => 0.05
      ], 
      '16646' => (object)[
        'name' => 'Mercury',
        'size' => 0.05
      ], 
      '16651' => (object)[
        'name' => 'Neodymium',
        'size' => 0.05
      ], 
      '16644' => (object)[
        'name' => 'Platinum',
        'size' => 0.05
      ], 
      '16652' => (object)[
        'name' => 'Promethium',
        'size' => 0.05
      ], 
      '16639' => (object)[
        'name' => 'Scandium',
        'size' => 0.05
      ], 
      '16636' => (object)[
        'name' => 'Silicates',
        'size' => 0.05
      ], 
      '16649' => (object)[
        'name' => 'Technetium',
        'size' => 0.05
      ], 
      '16653' => (object)[
        'name' => 'Thulium',
        'size' => 0.05
      ], 
      '16638' => (object)[
        'name' => 'Titanium',
        'size' => 0.05
      ], 
      '16637' => (object)[
        'name' => 'Tungsten',
        'size' => 0.05
      ], 
      '16642' => (object)[
        'name' => 'Vanadium',
        'size' => 0.05
      ]
    ];
  }

  public function getJitaMinPriceById($id){
    $mineral = $this->get()->$id;
    return min([
      $mineral->buyPrice,
      $mineral->historicalPrices->{5},
      $mineral->historicalPrices->{15},
      $mineral->historicalPrices->{30}
    ]);
  }

  public function getExportPriceById($id, $costPerVolume){
    $mineral = $this->get()->$id;
    return $this->getJitaMinPriceById($id) - ($mineral->size * $costPerVolume);
  }

  public function getJitaMinPriceByName($name){
    return $this->getJitaMinPriceById($this->getIdByName($name));
  }

  public function getExportPriceByName($name, $costPerVolume){
    return $this->getExportPriceById($this->getIdByName($name), $costPerVolume);
  }

  private function getIdByName($name){
    $minerals = $this->get();
    foreach ($minerals as $id => $mineral) {
      if (strtolower($mineral->name) == strtolower($name)){
        return $id;
      }
    }
    return null;
  }

  public function get(){
    $cache = $this->cache->get();
    if (is_null($cache) || new DateTime($cache->cachedUntil) < new DateTime()){
      $this->updateCache();
      $cache = $this->cache->get();
    }
    return $cache->list;
  }

  function updateCache(){
    $cachedMineralsList = $this->cache->get();

    $mineralsList = (object)[
      'cachedUntil' => date('c', strtotime('+1 day', time()))
    ];

    $mineralsList->list = $this->mineralsData;

    // Get buy prices
    foreach ($mineralsList->list as $id => $mineral) {
      $price = null;

      // fetch buy orders ion The Forge
      $buyOrders = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/markets/10000002/orders/?datasource=tranquility&order_type=buy&type_id=' . $id,
        null
      );

      // If no buy orders, use the previously cached price. 
      if (is_null($buyOrders)){
        if (!is_null($cachedMineralsList)){
          $mineral->buyPrice = $cachedMineralsList->list->$id->buyPrice;
        }
        else {
          $mineral->buyPrice = 0;
        }
        continue;
      }

      // Sort buy orders buy highest price first.
      usort($buyOrders, function ($a, $b){
        if ($a->price == $b->price) {
          return 0;
        }
        return ($a->price > $b->price) ? -1 : 1;
      });

      // Filter 
      $JitaBuyOrders = array_filter($buyOrders, function($buyOrder){
        if ($buyOrder->location_id == 60003760){
          return true;
        }
        return false;
      });

      // Take the price of the 100 thousandth item (to avoid high outliers skewing the price)
      $quantityToFind = 100000;
      $mineral->buyPrice = null;
      foreach ($JitaBuyOrders as $buyIndex => $buyOrder) {
        $quantityToFind -= $buyOrder->volume_remain;
        if ($quantityToFind < 1){
          $mineral->buyPrice = $buyOrder->price;
          break;
        }
      }

      // If not 10000000 items on buy order in Jita, use the previously saved price. 
      if (is_null($mineral->buyPrice)){
        var_dump($quantityToFind);die();
        if (!is_null($cachedMineralsList)){
          $mineral->buyPrice = $cachedMineralsList->list->$id->buyPrice;
        }
        else {
          $mineral->buyPrice = 0;
        }
        continue;
      }
    }

    // Get historical prices
    foreach ($mineralsList->list as $id => $mineral) {
      $history = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/markets/10000002/history/?datasource=tranquility&type_id=' . $id,
        null
      );
      $mineral->historicalPrices = (object)[
          '5' => $this->getHistoricAverage($history, 5),
          '15' => $this->getHistoricAverage($history, 15),
          '30' => $this->getHistoricAverage($history, 30),
      ];

    }

    // Save the data to the cache. 
    $this->cache->set($mineralsList);

    // Cache updated. Return true. 
    return true;
  }

  private function getHistoricAverage($history, $days){
    $price = (object)[
      'totalVolume' => 0,
      'totalISK' => 0,
    ];

    foreach ($history as $index => $record) {
      if(strtotime($record->date) > strtotime('-'.$days.' days')) {
         $price->totalVolume += $record->volume;
         $price->totalISK += ($record->average * $record->volume);
      }
    }

    return floor(($price->totalISK / $price->totalVolume) * 100) / 100;
  }
}