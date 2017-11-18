<?php
  // Get existing order from querystring. 
  $order = null;
  if (isset($_GET['order'])){
    $order = json_decode(urldecode($_GET['order']));
  }
  else {
    $order = [];
  }

  $errors = [];

  // Handle add action
  if (isset($_GET['action']) && $_GET['action'] == 'add' && isset($_POST['itemlist'])){
    $lines = explode(PHP_EOL, $_POST['itemlist']);
    foreach ($lines as $index => $line) {
      $line = trim($line);
      if ($line == '') continue;
      $split = preg_split('/(x|X)(?= *\d+$)/', $line);

      $search = $util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/search/?categories=inventorytype&strict=true&search=' . urlencode(trim($split[0])),
        null
      );

      if (!isset($search->inventorytype)){
        array_push($errors, 'Item "' . $split[0] . '" not found.');
        continue;
      }

      $item = (object)[
        'id' => $search->inventorytype[0],
        'quantity' => isset($split[1]) ? $split[1] : 1
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

  // Get order details
  $orderDetails = [];
  foreach ($order as $orderIndex => $item) {
    // Get item name and volume
    $itemdata = $util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/universe/types/' . $item->id.'/?datasource=tranquility&language=en-us',
      null
    );
    if (is_null($itemdata)){
      array_push($errors, 'Details for item "' . $item->id . '" not found. Maybe EVE is down?');
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
      array_push($errors, 'No Jita sell orders for "' . $itemdata->name . '" found. Buy the item yourself and use the courier service.');
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

    $quantityToFind = ($item->quantity * 10);
    $price = null;
    foreach ($JitaSellOrders as $sellIndex => $sellOrder) {
      $quantityToFind -= $sellOrder->volume_remain;
      if ($quantityToFind < 1){
        $price = $sellOrder->price;
        break;
      }
    }
    if (is_null($price)){
      array_push($errors, 'Not enough Jita sell orders for "' . $itemdata->name . '" found. Try a smaller quanitity or buy the item yourself and use the courier service.');
      unset($order[$orderIndex]);
      continue;
    }

    // Push order to $orderDetails array.
    $courierCost = $itemdata->packaged_volume * 500;
    $adminFee = ceil(($price + $courierCost) * 0.1);
    $itemTotal = $price + $courierCost + $adminFee;
    $total = $itemTotal * $item->quantity;

    array_push($orderDetails, (object)[
      'name' => $itemdata->name,
      'jitaPrice' => $price,
      'volume' => $itemdata->packaged_volume,
      'courierCost' => $courierCost,
      'adminFee' => $adminFee,
      'totalitem' => $itemTotal,
      'quantity' => $item->quantity,
      'total' => $total
    ]);
  }
?>

<h2>Online shop</h2>
<div class="help-section">
  <h3>Search for Items</h3>
  <form method="post" action="<?php 
    echo htmlspecialchars($_SERVER["PHP_SELF"]);
  ?>?p=shop&action=add&order=<?php
    echo(urlencode(json_encode($order)));
  ?>">  
    <textarea rows="20" cols="200" id="itemlist" name="itemlist" style="width:100%">
      Barrage XL
      Slasher x4
      VNI
    </textarea>
    <input type="submit" name="submit" value="Add to Order">  
  </form>
</div>
<div class="help-section">
  <h3>Confirm Order</h3>
  <?php
    $total = (object)[
      'jitaPrice' => 0,
      'volume' => 0,
      'courierCost' => 0,
      'adminFee' => 0,
      'total' => 0
    ];
    foreach ($errors as $index => $error) {
      echo ('<p class="error">'.$error.'</p>');
    }
  ?>
  <div class="station-div">
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
        foreach ($orderDetails as $index => $item) {
          $total->jitaPrice += ($item->jitaPrice * $item->quantity);
          $total->volume += ($item->volume * $item->quantity);
          $total->courierCost += ($item->courierCost * $item->quantity);
          $total->adminFee += ($item->adminFee * $item->quantity);
          $total->total += $item->total;
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
        <th><?=number_format($total->jitaPrice, 2)?> ISK</th>
        <th><?=number_format($total->volume, 2)?> m3</th>
        <th><?=number_format($total->courierCost, 2)?> ISK</th>
        <th><?=number_format($total->adminFee, 2)?> ISK</th>
        <th>-</th>
        <th>-</th>
        <th><?=number_format($total->total, 2)?> ISK</th>
      </tr>
    </table>
  </div>
</div>
<div class="help-section">
  <h3>Order History</h3>
</div>