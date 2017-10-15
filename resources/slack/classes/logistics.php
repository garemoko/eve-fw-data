<?php

require_once( __DIR__ . "/../../classes/cache.php");
require_once(__DIR__ . "/../../classes/util.php");

class Logistics {
  private $util;
  private $cache;
  private $minimumOrderSize = 5000;
  private $cargoSpace = 360000;
  private $costPerM3 = 500;

  public function __construct($slackToken, $slackChannelId){
    $this->util = new Util();
    $this->cache = new FileCache('logistics/'.$slackToken.'/'.$slackChannelId.'/logistics-orders.json');
    $cache = $this->cache->get();
    if (is_null($cache)) {
      $cache = (object)[
        'lastOrderId' => 0,
        'orders' => (object)[]
      ];
      $this->cache->set($cache);
    }
  }

  public function get(){
    return $this->cache->get();
  }

  public function addOrder($size, $owner){

    $size = ceil($size);
    if ($size < $this->minimumOrderSize){
      $size = $this->minimumOrderSize;
    }

    if ($size > $this->cargoSpace){
      return 'Attempt to add order failed. Too big!';
    }

    $cache = $this->cache->get();
    $id = $cache->lastOrderId++;
    $order = (object)[
      'owner' => $owner,
      'size' => $size,
      'cost' => $size * $this->costPerM3
    ];

    $cache->orders->$id = $order;

    $this->cache->set($cache);
    if ($size == $this->minimumOrderSize){
      return 'Minimum sized order added. Please create a courier contract for '. number_format($order->cost) .'ISK, using order number ' . $id .' in the description.';
    }
    return 'Order added. Please create a courier contract for '. number_format($order->cost) .'ISK, using order number ' . $id .' in the description.';
  }

  public function getOrders(){
    $cache = $this->cache->get();
    $list = [];
    foreach ($cache->orders as $id => $order) {
      array_push($list, $id . ': ' . $order->size. 'm3 for ' .$order->owner) 
        . ' ('.number_format($order->cost).'ISK)';
    }
    if (count($list > 0)) {
      return implode(PHP_EOL, $list);
    }
    return 'No orders found';
  }

  public function removeAllOrders(){
    $cache = $this->cache->get();
    $cache->orders = (object)[];
    $this->cache->set($cache);
    return true;
  }

  public function removeOrder($id){
    $cache = $this->cache->get();

    if ($id == -1){
      return $this->removeAllOrders();
    }

    if (isset($cache->orders->$id)){
      $rtnStr = 'Removed '. $id . ': ' . number_format($cache->orders->$id->size). 'm3 for ' 
        .$cache->orders->$id->owner;
      unset($cache->orders->$id);
      $this->cache->set($cache);
      return $rtnStr;
    }
    return false;
  }

  public function getQueues(){
    $cache = $this->cache->get();
    $list = [];
    $this->cacheQueues();

    foreach ($cache->queues as $index => $queue) {
      $queueStr = 'Queue '.$index . ' is worth ' . number_format(ceil($queue->cost)) . 'ISK total. It has '
        . number_format(strval($this->cargoSpace - $queue->size)) .'m3 space remaining. Orders:';
      $queueList = [];
      foreach ($queue->orders as $index => $order) {
        array_push(
          $queueList, $order->id . ': ' . number_format($order->size). 'm3 for ' .$order->owner 
          . ' ('.number_format($order->cost).'ISK)'
        );
      }
      array_push($list, $queueStr . PHP_EOL . implode(PHP_EOL, $queueList));
    }
    return implode(PHP_EOL, $list);
  }

  public function acceptQueue($index){
    $cache = $this->cache->get();
    $queue = $cache->queues[$index];
    foreach ($queue->orders as $index => $order) {
      $this->removeOrder($order->id);
    }
    return 'Queue ' . $index . ' accepted. Your stuff will be delivered soon(TM).'
      .' Here\'s the new queue list (queue numbers have changed):'
      .PHP_EOL.$this->getQueues();
  }

  public function cacheQueues(){
    $cache = $this->cache->get();
    $queues = [];
    foreach ($cache->orders as $id => $order) {
      $order->id = $id;
      $addedToQueue = false;
      foreach ($queues as $index => $queue) {
        if ($this->cargoSpace - $queue->size > $order->size) {
          array_push($queue->orders, $order);
          $queue->size += $order->size;
          $addedToQueue = true;
          break;
        }
      }
      if (!$addedToQueue) {
        array_push($queues, (object)[
          'size' => $order->size,
          'orders' => [$order]
        ]);
      }
    }

    // calculate cost
    foreach ($queues as $queueIndex => $queue) {
      $queue->cost = 0;
      foreach ($queue->orders as $orderIndex => $order) {
        $queue->cost += $order->cost;
      }
    }

    $cache->queues = $queues;
    $this->cache->set($cache);
    return $queues;
  }

  public function delete(){
    $this->cache->delete();
  }

}