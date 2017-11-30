<?php
require_once( __DIR__ . "/../../resources/classes/store.php");
  // Get jita alt's refresh token from db
  $pilot = (object)[
    "name" => "Professor Pirate"
  ];
  $rows = $db->getRow('uk_characters', [
    'name' => $pilot->name
  ]);

  if (count($rows) == 0){
    echo('<h2>No API Token for Shopper Pilot '.$pilot->name.' found in database. Shopper Pilot must log in.</h2>');
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
  else {
    echo('<h2>Error rereshing API Token for Shopper Pilot '.$pilot->name.'. Is it downtime?</h2>');
    die();
  }

  $adminIds = [$pilot->id];
  $tribalStore = new TribalStore($character->id, $adminIds, $pilot);

  // Get existing order from querystring. 
  if (isset($_GET['order'])){
    $tribalStore->setOrder(json_decode(urldecode($_GET['order'])));
  }

  // Handle add action
  $addErrors = [];
  if (isset($_GET['action']) && $_GET['action'] == 'add' && isset($_POST['itemlist'])){
    $addErrors = $tribalStore->handleAddAction($_POST['itemlist']);
  }

  // Consoldiate orders to avoid multiple lines with the same item type
  $tribalStore->consolidateOrders();

  if (isset($_GET['action']) && $_GET['action'] == 'emptybasket'){
    // empty the basket
    $tribalStore->handleEmptyBasketAction();
  }

  // Handle checkout action
  if (isset($_GET['action']) && $_GET['action'] == 'checkout'){
    $tribalStore->handleCheckoutAction();
  }

  // Get order details
  $orderDetails = $tribalStore->getOrderDetails($tribalStore->getOrder());
  
  $checkoutOrder = $tribalStore->getCheckoutOrder();
  $checkoutOrderDetails = null;
  if (is_null($checkoutOrder)){
    $checkoutOrderDetails = $tribalStore->getOrderDetails([]);
  }
  else {
    $checkoutOrderDetails = $tribalStore->getOrderDetails($checkoutOrder->items);
  }
  

  foreach ($checkoutOrderDetails->errors as $index => $error) {
    // For each error row, remove the item from checkout.
    $rows = $db->deleteRow('uk_tribalstore_orders_items', [
      'typeId' => $error->id,
      'orderId' => $checkoutDBData->order->orderId,
    ]);
  }


  // Get any payments for checked out orders
  $checkoutOrderDetails->payment = $tribalStore->getPayment();
  if ($checkoutOrderDetails->payment > $checkoutOrderDetails->total->total){
    $checkoutOrderDetails->tip = $checkoutOrderDetails->payment - $checkoutOrderDetails->total->total;
    $checkoutOrderDetails->due = 0;
  } else {
    $checkoutOrderDetails->tip = 0;
    $checkoutOrderDetails->due = $checkoutOrderDetails->total->total - $checkoutOrderDetails->payment;
  }

  // handle complete action
  $paymentErrors = [];
  if (isset($_GET['action']) && $_GET['action'] == 'complete'){
    // If there is nothing still to pay. 
    if ($checkoutOrderDetails->due == 0 && $checkoutOrderDetails->payment > 0){
      $paymentErrors = $tribalStore->handleCompleteAction($checkoutOrderDetails->payment);
    }
    else {
      if ($checkoutOrderDetails->due != 0){
        array_push($paymentErrors, (object)[
          'errorcode' => 'notPaidEnough',
          'message' => 'You have not paid enough to complete this order. Remember that Jita prices can change. Please pay more!'
        ]);
      }
      if ($checkoutOrderDetails->payment == 0){
        array_push($paymentErrors, (object)[
          'errorcode' => 'nothingToCheckout',
          'message' => 'There is nothing in your basket!'
        ]);
      }
    }
  }

  // Admin Only Actions
  if (isset($_GET['action']) && in_array($character->id, $adminIds)){
    if ($_GET['action'] == 'progressall'){
      $tribalStore->progressAll('pending', 'in progress');
    }
    else if ($_GET['action'] == 'deliver'){
      $tribalStore->progressById($_POST['orderId'], 'delivered');
    }
  }
  

  // get details of pending orders
  $lastSubmittedDate = null;
  $pendingOrders = $tribalStore->getOrderDetailsByStatus('pending');
  if (count($pendingOrders) > 0){
    $lastSubmittedDate = $pendingOrders[0]->submitted;
    foreach ($pendingOrders as $index => $pendingOrder) {
      if (strtotime($pendingOrder->submitted) > strtotime($lastSubmittedDate)){
        $lastSubmittedDate = $pendingOrder->submitted;
      }
    }
  }

  $progressOrders = $tribalStore->getOrderDetailsByStatus('in progress');
  $finishedOrders = $tribalStore->getOrderDetailsByStatus('delivered');

  /* TODO LIST
    2. Add deliver button per order and action
    3. display delivered orders
  */

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
          echo(urlencode(json_encode($tribalStore->getOrder())));
        ?>#confirm">
    <p>Enter multibuy style text below. You can copy this from fittings in game.</p>
    <textarea rows="20" cols="200" id="itemlist" name="itemlist" style="width:100%"></textarea>
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
  <a name="confirm"></a>
  <h3>2. Confirm Order</h3>
  <p>Check the order below and price, then Check Out. Your order is not saved until you check out!</p>
  <div class="station-div">
    <?php
      orderDetailsTable($orderDetails);
    ?>
    <form method="post" action="<?php 
      echo htmlspecialchars($_SERVER["PHP_SELF"]);
    ?>?p=shop&action=checkout&order=<?php
          echo(urlencode(json_encode($tribalStore->getOrder())));
        ?>#checkout">
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

<div class="help-section">
  <a name="checkout"></a>
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
    <a name="payment"></a>
    <h4>Payment</h4>
    <?php
      foreach ($paymentErrors as $index => $error) {
        ?>
          <div class="error-message">
            <p>There was an error processing your payment.</p>
            <p><?=$error->message?></p>
          </div>
        <?php
      }
    ?>
    <p>Once you are happy with your order, please send ISK to <a href="https://evewho.com/pilot/Professor+Pirate" target="_blank">Professor Pirate</a> in game. Use the 'Check for recent payments' button below to check for recent payments.</p>
    <table>
      <tr>
        <th>Total Price</th>
        <th>Payment Made</th>
        <th>Payment Due</th>
        <th>Tip</th>
        <th>Multibuy</th>
      </tr>
      <tr>
        <td><?=number_format($checkoutOrderDetails->total->total, 2)?> ISK</td>
        <td><?=number_format($checkoutOrderDetails->payment, 2)?> ISK</td>
        <td><?=number_format($checkoutOrderDetails->due, 2)?> ISK</td>
        <td><?=number_format($checkoutOrderDetails->tip, 2)?> ISK</td>
        <td class="multibuy"><pre class="clicktohighlight"><?=$checkoutOrderDetails->total->multibuy?></pre></td>
      </tr>
    </table>
    <form method="post" style="float:left;" action="<?php 
      echo htmlspecialchars($_SERVER["PHP_SELF"]);
    ?>?p=shop&order=<?php
      echo(urlencode(json_encode($tribalStore->getOrder())));
    ?>#payment">
      <p>
        <input type="submit" name="checkpaymenets" value="Check for recent payments"> 
      </p>
    </form>
    <?php
      if ($checkoutOrderDetails->due == 0 && $checkoutOrderDetails->total->total > 0){
        ?>
        <form method="post" action="<?php 
          echo htmlspecialchars($_SERVER["PHP_SELF"]);
        ?>?p=shop&action=complete&order=<?php
          echo(urlencode(json_encode($tribalStore->getOrder())));
        ?>#track">
          <p>
            <input type="submit" name="complete" value="Complete order"> 
          </p>
        </form>
        <?php
      }
    ?>
    <br style="clear:both;"/>
  </div>
</div>
<div class="help-section">
  <a name="track"></a>
  <h3>4. Track your order</h3>
  <p>Details of in-progress orders are listed below.</p>
  <h4>Pending</h4>
  <div class="station-div">
    <table>
      <tr>
        <th>Owner</th>
        <th>Paid</th>
        <th>Current Cost</th>
        <th>Current Admin Payment</th>
        <th>Volume</th>
        <th>Multibuy</th>
      </tr>
      <?php
        $pendingTotal = (object)[
          'paid' => 0,
          'cost' => 0,
          'profit' => 0,
          'volume' => 0,
          'multibuy' => ''
        ];
        foreach ($pendingOrders as $index => $pendingOrder) {
          foreach ($pendingOrder->errors as $index => $error) {
          ?>
            <tr><td colspan="5">
              <div class="error-message">
                <p>There was an error with the order row below: <code class="clicktohighlight"><?=$error->name?> x <?=$error->quantity?></code></p>
                <p><?=$error->message?></p>
                <p>The item has not been included in the totals and multibuy string.</p>
              </div>
            </td></tr>
          <?php
        }

          $profit = $pendingOrder->total->paid - $pendingOrder->total->courierCost - $pendingOrder->total->jitaPrice;
          $pendingTotal->paid += $pendingOrder->total->paid;
          $pendingTotal->cost += $pendingOrder->total->total;
          $pendingTotal->profit += $profit;
          $pendingTotal->volume += $pendingOrder->total->volume;
          $pendingTotal->multibuy .= $pendingOrder->total->multibuy . PHP_EOL;
          ?>
            <tr>
              <td><?=$pendingOrder->owner?></td>
              <td><?=number_format($pendingOrder->total->paid, 2)?> ISK</td>
              <td><?=number_format($pendingOrder->total->total, 2)?> ISK</td>
              <td><?=number_format($profit, 2)?> ISK</td>
              <td><?=number_format($pendingOrder->total->volume, 2)?> m3</td>
              <td class="multibuy"><pre class="clicktohighlight"><?=$pendingOrder->total->multibuy?></pre></td>
            </tr>
          <?php
        }
      ?>
      <tr>
        <th>Total</td>
        <th><?=number_format($pendingTotal->paid, 2)?> ISK</th>
        <th><?=number_format($pendingTotal->cost, 2)?> ISK</th>
        <th><?=number_format($pendingTotal->profit, 2)?> ISK</th>
        <th><?=number_format($pendingTotal->volume, 2)?> m3</th>
        <th class="multibuy"><pre class="clicktohighlight"><?=$pendingTotal->multibuy?></pre></th>
      </tr>
    </table>
    <?php
      if (in_array($character->id, $adminIds)) {
        ?>
        <form method="post" action="<?php 
          echo htmlspecialchars($_SERVER["PHP_SELF"]);
        ?>?p=shop&action=progressall&order=<?php
          echo(urlencode(json_encode($tribalStore->getOrder())));
        ?>&submitteduntil=<?php
          echo(urlencode($lastSubmittedDate));
        ?>#track">
          <p>
            <input type="submit" name="progress" value="Progress All"> 
          </p>
        </form>
      <?php
      }
    ?>
  </div>
  <h4>In Progress</h4>
  <div class="station-div">
    <table>
      <tr>
        <th>Owner</th>
        <th>Cost</th>
        <th>Volume</th>
        <th>Date Submitted</th>
        <th>Multibuy</th>
        <?php
          if (in_array($character->id, $adminIds)) {
            ?>
              <th>Complete Order</th>
            <?php
          }
        ?>
      </tr>
      <?php
        foreach ($progressOrders as $index => $progressOrder) {
          ?>
            <tr>
              <td><?=$progressOrder->owner?></td>
              <td><?=number_format($progressOrder->total->paid, 2)?> ISK</td>
              <td><?=number_format($progressOrder->total->volume, 2)?> m3</td>
              <td><?=date('jS M Y', strtotime($progressOrder->submitted))?></td>
              <td class="multibuy"><pre class="clicktohighlight"><?=$progressOrder->total->multibuy?></pre></td>
              <?php
                if (in_array($character->id, $adminIds)) {
                  ?><td><form method="post" action="<?php 
                    echo htmlspecialchars($_SERVER["PHP_SELF"]);
                  ?>?p=shop&action=deliver&order=<?php
                    echo(urlencode(json_encode($tribalStore->getOrder())));
                  ?>#track">
                    <p>
                      <input type="hidden" name="orderId" value="<?=$progressOrder->orderId?>">
                      <input type="submit" name="deliver-<?=$progressOrder->orderId?>" value="Deliver Order"> 
                    </p>
                  </form>
                </td><?php
                }
              ?>
            </tr>
          <?php
        }
      ?>
    </table>
  </div>
</div>
<div class="help-section">
  <a name="history"></a>
  <h3>Order History</h3>
  <p>Past Orders are listed below.</p>
  <div class="station-div">
    <table>
      <tr>
        <th>Owner</th>
        <th>Cost</th>
        <th>Volume</th>
        <th>Date Submitted</th>
        <th>Multibuy</th>
      </tr>
      <?php
        foreach ($finishedOrders as $index => $finishedOrder) {
          ?>
            <tr>
              <td><?=$finishedOrder->owner?></td>
              <td><?=number_format($finishedOrder->total->paid, 2)?> ISK</td>
              <td><?=number_format($finishedOrder->total->volume, 2)?> m3</td>
              <td><?=date('jS M Y', strtotime($finishedOrder->submitted))?></td>
              <td class="multibuy"><pre class="clicktohighlight"><?=$finishedOrder->total->multibuy?></pre></td>
            </tr>
          <?php
        }
      ?>
    </table>
  </div>
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