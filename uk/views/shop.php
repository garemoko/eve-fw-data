<?php
  // Ensure shop DB tables exist.
  // Orders table stores orders in progress (shopping cart onwards). 
  if (!$db->tableExists('uk_tribalstore_orders')){
    $db->createTable('uk_tribalstore_orders', (object)[
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
      ]
    ]);
  }

  // Orderitems table stores items related to orders
  if (!$db->tableExists('uk_tribalstore_orders_items')){
    $db->createTable('uk_tribalstore_orders_items', (object)[
      'orderId' => (object) [
        'type' => 'INT',
        'size' => 20,
        'attributes' => ['NOT NULL','PRIMARY KEY', 'AUTO_INCREMENT']
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

  // Get existing order from querystring. 
  $order = null;
  if (isset($_GET['order'])){
    $order = json_decode(urldecode($_GET['order']));
  }
  else {
    $order = [];
  }

  // Handle add action
  $addErrors = [];
  if (isset($_GET['action']) && $_GET['action'] == 'add' && isset($_POST['itemlist'])){
    $lines = explode(PHP_EOL, $_POST['itemlist']);
    foreach ($lines as $index => $line) {
      $line = trim($line);
      if ($line == '') continue;
      $split = preg_split('/(x|X)(?= *\d+$)/', $line);

      $searchName = trim($split[0]);
      $quantity = isset($split[1]) ? $split[1] : 1;

      $search = $util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/search/?categories=inventorytype&strict=true&search=' . urlencode($searchName),
        null
      );

      if (!isset($search->inventorytype)){
        array_push($addErrors, (object)[
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
      array_push($order, $item);
    }
  }

  // Consoldiate orders
  $index = 0;
  while ( count($order) > $index) {
    $item = $order[$index];
    foreach ($order as $secondIndex => $secondItem) {
      if ($index !== $secondIndex && $item->id == $secondItem->id){
        $item->quantity += $secondItem->quantity;
        unset($order[$secondIndex]);
        $order = array_values($order);
      }
    }
    $index++;
  }



  // Get current shopping basket (check out)
  $checkoutDBData = null;
  $rows = $db->getRow('uk_tribalstore_orders', [
    'ownerId' => $character->id,
    'status' => 'checkout'
  ]);

  // Handle Clear checkout action
  if (isset($_GET['action']) && $_GET['action'] == 'emptybasket'){
    // empty the basket
    $db->deleteRow('uk_tribalstore_orders_items', [
      'orderId' => $rows[0]->orderId
    ]);
  }
  else {
    // get items in basket
    if (count($rows) > 0){
      $checkoutDBData = (object)[
        'order' => $rows[0],
        'items' => $db->getRow('uk_tribalstore_orders_items', [
          'orderId' => $rows[0]->orderId
        ])
      ];
    }
  }
  

  $checkoutOrder = [];
  if (!is_null($checkoutDBData) && count($checkoutDBData->items > 0)){
    foreach ($checkoutDBData->items as $index => $item) {
      array_push($checkoutOrder, (object)[
        'id' => $item->typeId,
        'quantity' => $item->quantity
      ]);
    }
  }

  // Handle checkout action
  if (isset($_GET['action']) && $_GET['action'] == 'checkout'){
    // If there is nothing in the checkout, add it. 
    if (is_null($checkoutDBData)){
      $db->addRow('uk_tribalstore_orders', [
        'ownerId' => $character->id,
        'status' => 'checkout',
        'paid' => 0,
        'createdDate' => date('c')
      ]);
      $rows = $db->getRow('uk_tribalstore_orders', [
        'ownerId' => $character->id,
        'status' => 'checkout'
      ]);
      $checkoutDBData = (object)[
        'order' => $rows[0],
        'items' => []
      ];
    }

    // Loop through all items in the order
    foreach ($order as $orderIndex => $orderItem) {
      // See if the item is already in checkout list
      $itemInCheckout = false;
      foreach ($checkoutOrder as $checkoutIndex => $checkoutItem) {
        // if it is, update the checkout quantity (and db record)
        if ($orderItem->id == $checkoutItem->id){
          $itemInCheckout = true;
          $checkoutItem->quantity += $orderItem->quantity;
          $db->updateRow('uk_tribalstore_orders_items', [
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
        $db->addRow('uk_tribalstore_orders_items', [
          'typeId' => $orderItem->id,
          'orderId' => $checkoutDBData->order->orderId,
          'quantity' => $orderItem->quantity
        ]);
        array_push($checkoutOrder, $orderItem);
      }
    }
    // Clear the order (because its now in checkout)
    $order = [];
  }

  // Get order details
  $orderDetails = getOrderDetails($order, $util);
  $checkoutOrderDetails = getOrderDetails($checkoutOrder, $util);

  foreach ($checkoutOrderDetails->errors as $index => $error) {
    // For each error row, remove the item from checkout.
    $rows = $db->deleteRow('uk_tribalstore_orders_items', [
      'typeId' => $error->id,
      'orderId' => $checkoutDBData->order->orderId,
    ]);
  }

  // Get jita alt's refresh token from db
  $pilot = (object)[
    "name" => "Professor Pirate"
  ];
  $rows = $db->getRow('uk_characters', [
    'name' => $pilot->name
  ]);

  if (count($rows) == 0){
    echo('<h2>No API Token for Courier Pilot '.$pilot->name.' found in database. Courier Pilot must log in.</h2>');
    die();
  }

  $pilot->id = $rows[0]->id;
  $pilot->refreshToken = $rows[0]->refreshToken;

  // Resfresh and store access token
  $refreshresponse = $login->refreshToken($pilot->refreshToken);
  if ($refreshresponse->status == 200){
    $token = json_decode($refreshresponse->content);
    $pilot->accessToken = $token->access_token;
    $pilot->refreshToken = $token->refresh_token;
    $db->updateRow('uk_characters', [
      'id' => $pilot->id
    ] , [
      'accessToken' => $pilot->accessToken,
      'refreshToken' => $pilot->refreshToken 
    ]);
  }

  // Get any payments for checked out orders
  $checkoutOrderDetails->payment = 0;
  if (!is_null($checkoutDBData) && $refreshresponse->status == 200){
    $response = $util->requestAndRetry('https://esi.tech.ccp.is/latest/characters/'.$pilot->id.'/wallet/journal/?token='.$pilot->accessToken, null);

    // Filter Player Donations from Current Player since order start
    $filteredPayments = [];
    foreach ($response as $index => $payment) {
      if (
        ($payment->ref_type == 'player_donation')
        && ($payment->first_party_id == $character->id)
        //&& (strtotime($contract->date) > strtotime($checkoutDBData->createdDated))
      ){
        $checkoutOrderDetails->payment += $payment->amount;
      }
    }
  }


  function getOrderDetails($order, $util){
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
      $itemdata = $util->requestAndRetry(
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
      $sellOrders = $util->requestAndRetry(
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
        return ($a->price > $b->price) ? -1 : 1;
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
          'message' => 'Not enough Jita sell orders found. Try a smaller quanitity or buy the item yourself and use the courier service.'
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

  function orderDetailsTable($orderDetails){
    foreach ($orderDetails->errors as $index => $error) {
      ?>
        <div class="error-message">
          <p>There was an error with your order: <code class="clicktohighlight"><?=$error->name?> x <?=$error->quantity?></code></p>
          <p><?=$error->message?></p>
          <p>The item has been removed and is no longer in your order.</p>
        </div>
      <?php
    }
  ?>
  <table>
    <tr>
      <th>Item</th>
      <th>Jita Sell Price</th>
      <th>Volume</th>
      <th>Courier Cost (per item)</th>
      <th>Admin Fee (per item)</th>
      <th>Total Per Item</th>
      <th>Quantity</th>
      <th>Total Cost</th>
    </tr>
    <?php 
      foreach ($orderDetails->orderDetails as $index => $item) {
        ?>
        <tr>
          <td><?=$item->name?></td>
          <td><?=number_format($item->jitaPrice, 2)?> ISK</td>
          <td><?=number_format($item->volume, 2)?> m3</td>
          <td><?=number_format($item->courierCost, 2)?> ISK</td>
          <td><?=number_format($item->adminFee, 2)?> ISK</td>
          <td><?=number_format($item->totalitem, 2)?> ISK</td>
          <td><?=number_format($item->quantity, 0)?></td>
          <td><?=number_format($item->total, 2)?> ISK</td>
        </tr>
        <?php 
      }
    ?>
    <tr>
      <th>Total (all items)</th>
      <th><?=number_format($orderDetails->total->jitaPrice, 2)?> ISK</th>
      <th><?=number_format($orderDetails->total->volume, 2)?> m3</th>
      <th><?=number_format($orderDetails->total->courierCost, 2)?> ISK</th>
      <th><?=number_format($orderDetails->total->adminFee, 2)?> ISK</th>
      <th>-</th>
      <th>-</th>
      <th><?=number_format($orderDetails->total->total, 2)?> ISK</th>
    </tr>
  </table>
  <div class="multibuy">
    <h4>Multibuy (Click text to copy)</h4>
    <pre class="clicktohighlight"><?=$orderDetails->total->multibuy?></pre>
  </div>
  <?php
  }
?>
<h2>Tribal Store</h2>

<div class="help-section">
  <p>Welcome to Tribal Store! Here you can order items to be delivered direct to UAV-1E - The Butterfly Net. Please note that we charge a 10% admin fee. You can avoid this by buying the items yourself in Jita and using the <a href="<?php 
    echo htmlspecialchars($_SERVER["PHP_SELF"]);
  ?>?p=courier">courier service</a>.</p>
  <h3>1. Search for Items</h3>
  <form method="post" action="<?php 
    echo htmlspecialchars($_SERVER["PHP_SELF"]);
  ?>?p=shop&action=add&order=<?php
    echo(urlencode(json_encode($order)));
  ?>">
    <p>Enter multibuy style text below (replace 30 Firetails example). You can copy this from fittings in game.</p>
    <textarea rows="20" cols="200" id="itemlist" name="itemlist" style="width:100%">Republic Fleet Firetail x30</textarea>
    <input type="submit" name="submit" value="Add to Order">  
  </form>
  <?php 
    foreach ($addErrors as $index => $error) {
      ?>
        <div class="error-message">
          <p>There was an error with your order: <code class="clicktohighlight"><?=$error->name?> x <?=$error->quantity?></code></p>
          <p><?=$error->message?></p>
          <p>The item has been removed and is no longer in your order.</p>
        </div>
      <?php
    }
  ?>
</div>
<div class="help-section">
  <h3>2. Confirm Order</h3>
  <p>Check the order below and price, then Check Out. Your order is not saved until you check out!</p>
  <div class="station-div">
    <?php
      orderDetailsTable($orderDetails);
    ?>
    <form method="post" action="<?php 
      echo htmlspecialchars($_SERVER["PHP_SELF"]);
    ?>?p=shop&action=checkout&order=<?php
      echo(urlencode(json_encode($order)));
    ?>">
      <p>
        <input type="submit" name="checkout" value="Add To Basket"> to save your order and proceed to payment.
      </p>
    </form>
    <form method="post" action="<?php 
      echo htmlspecialchars($_SERVER["PHP_SELF"]);
    ?>?p=shop">
      <p>
        <input type="submit" name="clear-order" value="Clear Order"> to start again.
      </p>
    </form>
  </div>
</div>

<h2>TO DO NEXT: payment</h2>

<h2>TO DO NEXT: post payment order processing</h2>

<div class="help-section">
  <h3>3. Check Out and Pay</h3>
  <p>Details of your saved in-progress order are listed below.</p>
  <div class="station-div">
    <?php
      orderDetailsTable($checkoutOrderDetails);
    ?>
    <form method="post" action="<?php 
      echo htmlspecialchars($_SERVER["PHP_SELF"]);
    ?>?p=shop&action=emptybasket">
      <p>
        <input type="submit" name="emptybasket" value="Empty Basket"> to clear your saved order.
      </p>
    </form>
  </div>
</div>
<div class="help-section">
  <h3>4. Track your order</h3>
  <p>Details of in-progress orders are listed below.</p>
  <p>Coming soon...</p>
</div>
<div class="help-section">
  <h3>Order History</h3>
  <p>Past Orders are listed below.</p>
  <p>Coming soon...</p>
</div>


<script>
  onloadFunctions.push( function() {
    $('.clicktohighlight').click(function(e){
      SelectText(this);
      document.execCommand('copy');
    });
  });

  function SelectText(text) {
    var doc = document
        , range, selection
    ;
    if (doc.body.createTextRange) {
        range = document.body.createTextRange();
        range.moveToElementText(text);
        range.select();
    } else if (window.getSelection) {
        selection = window.getSelection();        
        range = document.createRange();
        range.selectNodeContents(text);
        selection.removeAllRanges();
        selection.addRange(range);
    }
  }
</script>