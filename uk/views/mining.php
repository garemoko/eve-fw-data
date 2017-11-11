<?php

// Get courier pilot's refresh token from db
$pilot = (object)[
  "name" => "Yerik"
];
$rows = $db->getRow('uk_characters', [
  'name' => $pilot->name
]);

if (count($rows) == 0){
  echo('<h2>No API Token for Courier Pilot '.$pilot->name.' found in database. Courier Pilot must log in.</h2>');

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
  echo('<h2>Error resfreshing API Token for Courier Pilot '.$pilot->name.'.</h2>');
  die();
}

// Use access token to get courier contracts
$response = $util->requestAndRetry('https://esi.tech.ccp.is/latest/characters/'.$pilot->id.'/contracts/?type=courier&token='.$pilot->accessToken, null);

// Filter Jita to UAV and UAV to Jita, open contracts
$filteredContracts = [];
foreach ($response as $index => $contract) {
  if (
    ($contract->status == 'outstanding')
    && ($contract->type == 'courier')
    && ($contract->start_location_id == 1025644663382 || $contract->end_location_id == 1025644663382)
    && ($contract->start_location_id == 60003760 || $contract->end_location_id == 60003760)
    && ($contract->days_to_complete > 13)
  ){
    $issuerData = new Character($contract->issuer_id);
    $contract->issuer_name = $issuerData->get()->character->name;
    array_push($filteredContracts, $contract);
  }
}
unset($response);



// Build queues.
$cargoSpace = 360000;
$queues = (object)[
  'fromJita' => [
      (object)[
      'size' => 0,
      'orders' => []
    ]
  ],
  'toJita' => [
    (object)[
      'size' => 0,
      'orders' => []
    ]
  ]
];

foreach ($filteredContracts as $contractIndex => $contract) {
  $queueToAddto = $contract->start_location_id == 60003760 ? 'fromJita' : 'toJita';
  $addedToQueue = false;
  $order = (object)[
    'size' => $contract->volume,
    'cost' => $contract->reward,
    'owner' => $contract->issuer_name
  ];
  foreach ($queues->$queueToAddto as $queueIndex => $queue) {
    
    if ($cargoSpace - $queue->size >= $contract->volume) {
      array_push($queue->orders, $order);
      $queue->size += $order->size;
      $addedToQueue = true;
      break;
    }
  }
  if (!$addedToQueue) {
    array_push($queues->$queueToAddto, (object)[
      'size' => $order->size,
      'orders' => [$order]
    ]);
  }
}
?>

<h2>Get your stuff moved</h2>

<div class="help-section">
  <h3>Courier contract calculator</h3>
  <p>Size of contract in m3: <input type="number" id="size" name="size" lang="en-150"/> 
  Order cost: <input type="text" id="cost" name="cost" value="0" disabled="disabled" lang="en-150" />ISK</p>
  <p>To get stuff moved from Jita to UAV-1E you should:</p>
  <ol>
    <li>Make a private courier contract to '<a href="https://evewho.com/pilot/<?=$pilot->name?>" target="_blank"><?=$pilot->name?></a>'.</li>
    <li>Set the Pick Up point to <b>Jita IV - Moon 4 - Caldari Navy Assembly Plant</b> or <b>UAV-1E - The Butterfly Net</b> and select items. <strong>Do not make contracts for any other station or structure</strong>.</li>
    <li>Set the Ship To point to <b>Jita IV - Moon 4 - Caldari Navy Assembly Plant</b> or <b>UAV-1E - The Butterfly Net</b> and select items. <strong>Do not make contracts for any other station or structure</strong>. Use the calculator above to caluclate the minimum reward. Enter that. Use Est. Price to calculate Collateral and set a long Expiration and Days to Complete (minimum 14 Days to Complete).</li>
    <li>Finish the contract</li>
  </ol>
  <p>Once created, contracts that meet these requirements will appear below when you refresh the page. Please allow up to 15 minutes for caching.</p>
</div>

<script type="text/javascript">
  onloadFunctions.push( function() {
    $('#size').change(updateCost);
    $('#size').keyup(updateCost);
    updateCost();
    function updateCost(){
      $('#size').val(parseFloat($('#size').val()));
      var cost = Math.floor($('#size').val()) * 500;
      cost = cost < 2500000 ? '250,0000' : cost.toString().split(/(?=(?:\d{3})+$)/).join(",");
      $('#cost').val(cost);
    }
 });
</script>
<h2>Track open orders</h2>
<?php
  printQueues('Jita to UAV-1E', $queues->fromJita);
  printQueues('UAV-1E to Jita', $queues->toJita);

  function printQueues($title, $queuesToPrint){
    $costPerM3 = 500;

    $colors = (object)[
      'red' => (object)[
        'red' => 230,
        'green' => 0,
        'blue' => 0
      ],
      'yellow' => (object)[
        'red' => 204,
        'green' => 153,
        'blue' => 0
      ],
      'green' => (object)[
        'red' => 36,
        'green' => 143,
        'blue' => 36
      ]
    ];

    foreach ($queuesToPrint as $qIndex => $q) {
      ?><div class="station-div"><h3><?=$title?>: Freighter Load <?=$qIndex + 1?></h3>
      <table>
        <tr>
          <th>Owner</th>
          <th>m3</th>
          <th>Reward</th>
          <th>Expected Reward</th>
        </tr>
        <?php
        $total = (object)[
          'expectedreward' => 0,
          'reward' => 0,
          'size' => 0
        ];
        foreach ($q->orders as $oIndex => $o) {
          $expectedReward = floor($o->size) * $costPerM3;
          $pricePercent = $o->cost / $expectedReward * 100; 
          $color = (object)[
            'red' => 0,
            'green' => 0,
            'blue' => 0
          ];
          if ($pricePercent > 100){
            foreach ($color as $key => $value) {
              $color->$key = ($colors->green->$key * ($pricePercent - 100) / 100) 
                + ($colors->yellow->$key * (200 - $pricePercent) / 100);
                $color->$key = round($color->$key);
            }
          } else {
            foreach ($color as $key => $value) {
              $color->$key = ($colors->yellow->$key * ($pricePercent) / 100) 
                + ($colors->red->$key * (100 - $pricePercent) / 100);
              $color->$key = round($color->$key);
            }
          }
          $colorStr = 'rgb('.$color->red.','.$color->green.','.$color->blue.')';
          ?>
          <tr>
            <td><?=$o->owner?></td>
            <td><?=number_format($o->size, 2)?> m3</td>
            <td style="background-color:<?=$colorStr?>;"><?=number_format($o->cost, 2)?> ISK</td>
            <td><?=number_format($expectedReward, 2)?> ISK</td>
          </tr>
          <?php
          $total->size += $o->size;
          $total->reward += $o->cost;
          $total->expectedreward += $expectedReward;
      }
      ?>
      <tr>
        <th>Total</th>
        <th><?=number_format($total->size, 2)?> m3</th>
        <th><?=number_format($total->reward, 2)?> ISK</th>
        <th><?=number_format($total->expectedreward, 2)?> ISK</th>
      </tr>
      <?php
      ?></table></div><?php
    }
  }
?>