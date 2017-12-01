<?php

require_once(__DIR__ . "/cache.php");
require_once(__DIR__ . "/util.php");
date_default_timezone_set('UTC');

class TribalStore {
  private $util;
  private $db;
  private $order;
  private $checkoutOrder;
  private $characterId;
  private $adminIds;
  private $paymentCharacter;

  public function __construct($characterId, $adminIds, $paymentCharacter){
    $this->util = new Util();
    $this->db = new Database();
    $this->characterId = $characterId;
    $this->adminIds = $adminIds;
    $this->paymentCharacter = $paymentCharacter;
    $this->createTables();
    $this->order = [];
    $checkoutOrder = null;
  }

  // Functions relating to fetching orders

  public function setOrder($order){
    $this->order = $order;
  }

  public function getOrder(){
    return $this->order;
  }

  public function consolidateOrders(){
    $index = 0;
    while (count($this->order) > $index) {
      $item = $this->order[$index];
      foreach ($this->order as $secondIndex => $secondItem) {
        if ($index !== $secondIndex && $item->id == $secondItem->id){
          $item->quantity += $secondItem->quantity;
          unset($this->order[$secondIndex]);
          $this->order = array_values($order);
        }
      }
      $index++;
    }
    return true;
  }

  public function getCheckoutOrder(){
    if (!is_null($this->checkoutOrder)){
      return $this->checkoutOrder;
    }
    $rows = $this->db->getRow('uk_tribalstore_orders', [
      'ownerId' => $this->characterId,
      'status' => 'checkout'
    ]);

    if (count($rows) == 0){
      $this->checkoutOrder = null;
      return null;
    }
    $this->checkoutOrder = (object)[
      'orderId' => $rows[0]->orderId,
      'createdDate' => $rows[0]->createdDate
    ];

    // get items in basket
    $rows = $this->db->getRow('uk_tribalstore_orders_items', [
      'orderId' => $rows[0]->orderId
    ]);

    $this->checkoutOrder->items = [];
    if (count($rows > 0)){
      foreach ($rows as $index => $item) {
        array_push($this->checkoutOrder->items, (object)[
          'id' => $item->typeId,
          'quantity' => $item->quantity
        ]);
      }
    }

    return $this->checkoutOrder;
  }

   // Order details

  public function getOrderDetailsByStatus ($status){
    $rows = [];
    // Admin sees all
    if (in_array($this->characterId, $this->adminIds)) {
      $rows = $this->db->getRow('uk_tribalstore_orders', [
        'status' => $status
      ]);
    }
    else {
      $rows = $this->db->getRow('uk_tribalstore_orders', [
        'ownerId' => $this->characterId,
        'status' => $status
      ]);
    }

    $orders = [];
    foreach ($rows as $index => $row) {
      $orderDBData = (object)[
        'order' => $row,
        'items' => $this->db->getRow('uk_tribalstore_orders_items', [
          'orderId' => $row->orderId
        ])
      ];
      $order = [];
      if (count($orderDBData->items > 0)){
        foreach ($orderDBData->items as $index => $item) {
          array_push($order, (object)[
            'id' => $item->typeId,
            'quantity' => $item->quantity
          ]);
        }
      }
      $details = $this->getOrderDetails($order);
      $details->total->paid = $orderDBData->order->paid;

      $characterRows = $this->db->getRow('uk_characters', [
        'id' => $row->ownerId
      ]);
      $details->owner = $characterRows[0]->name;
      $details->submitted = $row->submittedDate;
      $details->orderId = $row->orderId;
      array_push(
        $orders,
        $details
      );
    }

    return $orders;
  }

  public function getOrderDetails($order){
    $orderDetails = [];
    $errors = [];
    $total = (object)[
      'jitaPrice' => 0,
      'volume' => 0,
      'courierCost' => 0,
      'adminFee' => 0,
      'total' => 0,
      'multibuy' => ''
    ];

    foreach ($order as $orderIndex => $item) {
      // Get item name and volume
      $itemdata = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/universe/types/' . $item->id.'/?datasource=tranquility&language=en-us',
        null
      );
      if (is_null($itemdata)){
        array_push($errors, (object)[
          'quantity' => $item->quantity,
          'name' => $item->id,
          'id' => $item->id,
          'errorcode' => 'itemIdNotFound',
          'message' => 'Details for item not found. Maybe EVE is down?'
        ]);
        unset($order[$orderIndex]);
        continue;
      }

      // Calculate best Jita buy price
      $price = null;
      $sellOrders = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/markets/10000002/orders/?datasource=tranquility&order_type=sell&type_id=' . $item->id,
        null
      );
      if (is_null($sellOrders)){
        array_push($errors, (object)[
          'quantity' => $item->quantity,
          'name' => $itemdata->name,
          'id' => $item->id,
          'errorcode' => 'noSellOrders',
          'message' => 'No Jita sell orders for found. Buy the item yourself and use the courier service.'
        ]);
        unset($order[$orderIndex]);
        continue;
      }

      usort($sellOrders, function ($a, $b){
        if ($a->price == $b->price) {
          return 0;
        }
        return ($a->price < $b->price) ? -1 : 1;
      });
      $JitaSellOrders = array_filter($sellOrders, function($sellOrder){
        if ($sellOrder->location_id == 60003760){
          return true;
        }
        return false;
      });

      $quantityToFind = ($item->quantity * 5);
      $price = null;
      foreach ($JitaSellOrders as $sellIndex => $sellOrder) {
        $quantityToFind -= $sellOrder->volume_remain;
        if ($quantityToFind < 1){
          $price = $sellOrder->price;
          break;
        }
      }
      if (is_null($price)){
        array_push($errors, (object)[
          'quantity' => $item->quantity,
          'name' => $itemdata->name,
          'id' => $item->id,
          'errorcode' => 'notEnoughSellOrders',
          'message' => 'Not enough Jita sell orders found. Try a smaller quantity or buy the item yourself and use the courier service.'
        ]);
        unset($order[$orderIndex]);
        continue;
      }

      // Push order to $orderDetails array.
      $courierCost = $itemdata->packaged_volume * 500;
      $adminFee = ceil(($price + $courierCost) * 0.1);
      $itemTotal = $price + $courierCost + $adminFee;
      $totalCost = $itemTotal * $item->quantity;

      array_push($orderDetails, (object)[
        'name' => $itemdata->name,
        'jitaPrice' => $price,
        'volume' => $itemdata->packaged_volume,
        'courierCost' => $courierCost,
        'adminFee' => $adminFee,
        'totalitem' => $itemTotal,
        'quantity' => $item->quantity,
        'total' => $totalCost
      ]);

      $total->jitaPrice += ($price * $item->quantity);
      $total->volume += ($itemdata->packaged_volume * $item->quantity);
      $total->courierCost += ($courierCost * $item->quantity);
      $total->adminFee += ($adminFee * $item->quantity);
      $total->total += $totalCost;
      $total->multibuy .= $itemdata->name . ' x' . $item->quantity . PHP_EOL;
    }
    return (object)[
      'errors' => $errors,
      'orderDetails' => $orderDetails,
      'total' => $total
    ];
  }

  public function getPayment(){
    $amountPaid = 0;

    if (!is_null($this->checkoutOrder)){
      $response = $this->util->requestAndRetry('https://esi.tech.ccp.is/latest/characters/'.$this->paymentCharacter->id.'/wallet/journal/?token='.$this->paymentCharacter->accessToken, null);
      // Filter Player Donations from Current Player since order start
      foreach ($response as $index => $payment) {
        if (
          ($payment->ref_type == 'player_donation')
          && ($payment->first_party_id == $this->characterId)
          && (strtotime($payment->date) > strtotime($this->checkoutOrder->createdDate))
        ){
          $amountPaid += $payment->amount;
        }
      }
    }
    return $amountPaid;
  }

  // progress order functions
  public function progressAll($oldStatus, $newStatus){
    $this->db->updateRow('uk_tribalstore_orders', [
      'status' => $oldStatus,
    ],[
      'status' =>  $newStatus
    ]);
  }

  public function progressById($orderId, $newStatus){
    $this->db->updateRow('uk_tribalstore_orders', [
      'orderId' => $orderId,
    ],[
      'status' =>  $newStatus
    ]);
  }

  // Action handlers
  public function handleAddAction($itemList){
    $errors = [];
    $lines = explode(PHP_EOL, $itemList);
    foreach ($lines as $index => $line) {
      $line = trim($line);
      if ($line == '') continue;
      $split = preg_split('/(x|X)(?= *\d+$)/', $line);

      $searchName = trim($split[0]);
      $quantity = isset($split[1]) ? $split[1] : 1;

      $search = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/search/?categories=inventorytype&strict=true&search=' . urlencode($searchName),
        null
      );

      if (!isset($search->inventorytype)){
        array_push($errors, (object)[
          'quantity' => $quantity,
          'name' => $searchName,
          'id' => null,
          'errorcode' => 'searchFailed',
          'message' => 'Error searching for item in EVE\'s item database.'
        ]);
        continue;
      }

      $item = (object)[
        'id' => $search->inventorytype[0],
        'quantity' => $quantity
      ];
      array_push($this->order, $item);
    }
    return $errors;
  }

  public function handleEmptyBasketAction(){
    // empty the basket
    $this->db->deleteRow('uk_tribalstore_orders_items', [
      'orderId' => $this->checkoutOrder->orderId
    ]);
    $this->checkoutOrder = null;
  }

  public function handleCheckoutAction(){
    $checkoutOrder = $this->getCheckoutOrder();
    // If there is nothing in the checkout, add it. 
    if (is_null($checkoutOrder)){
      $this->db->addRow('uk_tribalstore_orders', [
        'ownerId' => $this->characterId,
        'status' => 'checkout',
        'paid' => 0,
        'createdDate' => date('c')
      ]);
      $checkoutOrder = $this->getCheckoutOrder();
    }

    // Loop through all items in the order
    foreach ($this->order as $orderIndex => $orderItem) {
      // See if the item is already in checkout list
      $itemInCheckout = false;
      foreach ($checkoutOrder->items as $checkoutIndex => $checkoutItem) {
        // if it is, update the checkout quantity (and db record)
        if ($orderItem->id == $checkoutItem->id){
          $itemInCheckout = true;
          $checkoutItem->quantity += $orderItem->quantity;
          $this->db->updateRow('uk_tribalstore_orders_items', [
            'typeId' => $checkoutItem->id,
            'orderId' => $checkoutDBData->order->orderId
          ] , [
            'quantity' => $checkoutItem->quantity
          ]);
          break;
        }
      }
      // if not, add the item to the checkout as a new line item. 
      if ($itemInCheckout === false){
        $this->db->addRow('uk_tribalstore_orders_items', [
          'typeId' => $orderItem->id,
          'orderId' => $checkoutOrder->orderId,
          'quantity' => $orderItem->quantity
        ]);
        array_push($checkoutOrder->items, $orderItem);
      }
    }

    // Update the checkout in memory
    $this->checkoutOrder = $checkoutOrder;
    // Clear the pre-checkout order (because its now in checkout)
    $this->order = [];
  }

  public function handleCompleteAction($payment){

    // Update the order with the amount paid and new status
    $this->db->updateRow('uk_tribalstore_orders', [
      'orderId' => $this->checkoutOrder->orderId,
    ],[
      'status' => 'pending',
      'paid' => $payment,
      'submittedDate' => date('c')
    ]);
  }


  // Setup functions

  private function createTables(){
    // Ensure shop DB tables exist.
    // Orders table stores orders in progress (shopping cart onwards). 
    if (!$this->db->tableExists('uk_tribalstore_orders')){
      $this->db->createTable('uk_tribalstore_orders', (object)[
        'orderId' => (object) [
          'type' => 'INT',
          'size' => 20,
          'attributes' => ['NOT NULL','PRIMARY KEY', 'AUTO_INCREMENT']
        ],
        'ownerId' => (object) [
          'type' => 'INT',
          'size' => 20,
          'attributes' => ['NOT NULL']
        ],
        'paid' => (object) [
          'type' => 'INT',
          'size' => 50,
          'attributes' => ['NOT NULL']
        ],
        'status' => (object) [
          'type' => 'VARCHAR',
          'size' => 20,
          'attributes' => ['NOT NULL']
        ],
        'createdDate' => (object) [
          'type' => 'VARCHAR',
          'size' => 50,
          'attributes' => ['NOT NULL']
        ],
        'submittedDate' => (object) [
          'type' => 'VARCHAR',
          'size' => 50,
          'attributes' => []
        ]
      ]);
    }

    // Orderitems table stores items related to orders
    if (!$this->db->tableExists('uk_tribalstore_orders_items')){
      $this->db->createTable('uk_tribalstore_orders_items', (object)[
        'id' => (object) [
          'type' => 'INT',
          'size' => 20,
          'attributes' => ['NOT NULL','PRIMARY KEY', 'AUTO_INCREMENT']
        ],
        'orderId' => (object) [
          'type' => 'INT',
          'size' => 20,
          'attributes' => ['NOT NULL']
        ],
        'typeId' => (object) [
          'type' => 'INT',
          'size' => 20,
          'attributes' => ['NOT NULL']
        ],
        'quantity' => (object) [
          'type' => 'INT',
          'size' => 40,
          'attributes' => ['NOT NULL']
        ]
      ]);
    }
  }
}