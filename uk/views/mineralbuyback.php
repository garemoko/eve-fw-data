<?php
  require_once( __DIR__ . "/../../resources/classes/mineralprices.php");
  $buyMineralsList = (object)[
    'low' => ['Tritanium', 'Pyerite', 'Mexallon'],
    'mid' => ['Isogen', 'Nocxium'],
    'high' => ['Megacyte', 'Zydrine', 'Morphite']
  ];
  $sellMineralsList = (object)[
    'low' => ['Tritanium', 'Pyerite', 'Mexallon'],
    'mid' => ['Isogen', 'Nocxium'],
    'high' => ['Megacyte', 'Zydrine', 'Morphite']
  ];

  $costPerVolume = 500;
  $buyMineralsData = getPrices($buyMineralsList, 0.05, 0.2, 0.2, $costPerVolume);
  $sellMineralsData = getPrices($sellMineralsList, -0.05, 0.1, 0.1, $costPerVolume);

  $jetCanSize = 27500;
  $efficiency = 0.5;
  $oreValues = getOreValues($jetCanSize, $efficiency, $buyMineralsData);

  $targetStocklevels = (object)[];
  $stockTotalValue = 500 * 1000 * 1000;
  $stockEachValue = $stockTotalValue / count((array) $buyMineralsData);
  foreach ($buyMineralsData as $name => $price) {
    $targetStocklevels->$name = floor($stockEachValue / $price);
  }

  function getPrices($list, $lowTax, $midTax, $highTax, $costPerVolume){
    $mineralPricesFactory = new MineralPrices();
    $data = (object)[];
    foreach ($list->low as $index => $name) {
      $data->$name = $mineralPricesFactory->getJitaMinPriceByName($name, $lowTax);
    }
    foreach ($list->mid as $index => $name) {
      $data->$name = $mineralPricesFactory->getJitaMinPriceByName($name, $midTax);
    }
    foreach ($list->high as $index => $name) {
      $data->$name = $mineralPricesFactory->getExportPriceByName($name, $highTax, $costPerVolume);
    }
    return $data;
  }

  function outputPriceTable($data, $type){
    ?><div class="station-div">
      <table>
        <tr data-total="0">
          <th>Mineral</th>
          <th>T.R.I.A.D <?=$type?> Price</th>
          <th>Quantity</th>
          <th>ISK Total</th>
        </tr>
        <?php
          foreach ($data as $name => $price) {
            ?>
              <tr id="buybackisk-<?=$type?>-<?=$name?>" id="buybackisk-row" data-price="<?=number_format($price, 2, '.', '')?>" data-total="0">
                <td><?=$name?></td>
                <td><?=number_format($price, 2)?></td>
                <td class="buybackisk-quantity"><input type="number"></td>
                <td class="buybackisk-isk">0.00 ISK</td>
              </tr>
            <?php
          }
        ?>
        <tr data-total="0">
          <th>Total</th>
          <th></th>
          <th class="buybackisk-totalquantity"></th>
          <th class="buybackisk-totalisk">0.00 ISK</th>
        </tr>
      </table>
    </div><?php
  }

  function getOreValues($volume, $efficiency, $prices){
    $oreData = (object)[
      'Veldspar' => (object)[
        'volume' => 0.1,
        'minerals' => (object)[
          'Tritanium' => 415
        ]
      ],
      'Scordite' => (object)[
        'volume' => 0.15,
        'minerals' => (object)[
          'Tritanium' => 346,
          'Pyerite' => 173,
        ]
      ],
      'Pyroxeres' => (object)[
        'volume' => 0.3,
        'minerals' => (object)[
          'Tritanium' => 351,
          'Pyerite' => 25,
          'Mexallon' => 50,
          'Nocxium' => 5
        ]
      ],
      'Plagioclase' => (object)[
        'volume' => 0.35,
        'minerals' => (object)[
          'Tritanium' => 107,
          'Pyerite' => 213,
          'Mexallon' => 107
        ]
      ],
      'Omber' => (object)[
        'volume' => 0.6,
        'minerals' => (object)[
          'Tritanium' => 800,
          'Pyerite' => 100,
          'Isogen' => 85
        ]
      ],
      'Kernite' => (object)[
        'volume' => 1.2,
        'minerals' => (object)[
          'Tritanium' => 134,
          'Mexallon' => 267,
          'Isogen' => 134
        ]
      ],
      'Jaspet' => (object)[
        'volume' => 2,
        'minerals' => (object)[
          'Mexallon' => 350,
          'Nocxium' => 75
        ]
      ],
      'Hemorphite' => (object)[
        'volume' => 3,
        'minerals' => (object)[
          'Tritanium' => 2200,
          'Isogen' => 100,
          'Nocxium' => 120,
          'Zydrine' => 15
        ]
      ],
      'Hedbergite' => (object)[
        'volume' => 3,
        'minerals' => (object)[
          'Pyerite' => 1000,
          'Isogen' => 200,
          'Nocxium' => 100,
          'Zydrine' => 19
        ]
      ],
      'Gneiss' => (object)[
        'volume' => 5,
        'minerals' => (object)[
          'Pyerite' => 2200,
          'Mexallon' => 2400,
          'Isogen' => 300
        ]
      ],
      'Dark Ochre' => (object)[
        'volume' => 8,
        'minerals' => (object)[
          'Tritanium' => 10000,
          'Isogen' => 1600,
          'Nocxium' => 120
        ]
      ],
      'Spodumain' => (object)[
        'volume' => 16,
        'minerals' => (object)[
          'Tritanium' => 56000,
          'Pyerite' => 12050,
          'Mexallon' => 2100,
          'Isogen' => 450
        ]
      ],
      'Crokite' => (object)[
        'volume' => 16,
        'minerals' => (object)[
          'Tritanium' => 21000,
          'Nocxium' => 760,
          'Zydrine' => 135
        ]
      ],
      'Bistot' => (object)[
        'volume' => 16,
        'minerals' => (object)[
          'Pyerite' => 12000,
          'Megacyte' => 100,
          'Zydrine' => 450
        ]
      ],
      'Arkonor' => (object)[
        'volume' => 16,
        'minerals' => (object)[
          'Tritanium' => 22000,
          'Mexallon' => 2500,
          'Megacyte' => 320
        ]
      ],
      'Mercoxit' => (object)[
        'volume' => 40,
        'minerals' => (object)[
          'Morphite' => 300
        ]
      ]
    ];

    $oreValues = [];
    foreach ($oreData as $oreName => $data) {
      $unitsOfOre = $volume / $data->volume;
      $batches = $unitsOfOre / 100;
      $value = 0;
      foreach ($data->minerals as $mineralName => $mineralQuantity) {
        $value += $mineralQuantity * $prices->$mineralName * $batches * $efficiency;
      }
      $oreValues[$oreName] = $value;
    }
    arsort($oreValues);
    return (object) $oreValues;
  }
?>
<script type="text/javascript">
  onloadFunctions.push( function() {
    $('.buybackisk-quantity input').change(updateLine);
    $('.buybackisk-quantity input').keyup(updateLine);
    function updateLine(event){
      var price = parseFloat($(this).closest('tr').attr('data-price'));
      var quantity = parseInt($(this).val());
      if (isNaN(quantity)){
        quantity = 0;
      }
      var total = (price * quantity).toFixed(2);
      $(this).closest('tr').children('td:last').text(total.replace(/(\d)(?=(\d{3})+\.)/g, '$1,') + ' ISK');
      $(this).closest('tr').attr('data-total', total);

      var tableTotal = 0;
      $(this).closest('table').find('tr').each(function(index){
        tableTotal += parseFloat($(this).attr('data-total'));
      });
      tableTotal = tableTotal.toFixed(2);
      $(this).closest('table').find('.buybackisk-totalisk').text(tableTotal.replace(/(\d)(?=(\d{3})+\.)/g, '$1,') + ' ISK');
    }
    console.log('Target Stock Levels:');
    console.log(<?php
      echo json_encode($targetStocklevels);
    ?>);
 });
</script>
<h2>Sell your Minerals</h2>

<div class="help-section">
  <h3>T.R.I.A.D UAV-1E Mineral Buyback</h3>
  <p>T.R.I.A.D buy low level minerals at Jita price minus 5%. We buy mid level minerals at Jita price minus 20%. We buy high level minerals at Jita price minus 20%, minus hauling costs.</p>
  <p>To sell minerals to T.R.I.A.D, bring your reprocessed minerals to UAV-1E The Butterfly Net and create a private sell contract to T.R.I.A.D. Enter the values of minerals you are selling into the table below and enter the Total ISK as the 'I will recieve' value in the contract.</p>
    <?php
      outputPriceTable($buyMineralsData, 'Buy');
    ?>
</div>

<div class="help-section">
  <h3>T.R.I.A.D UAV-1E Mineral Store</h3>
  <p>T.R.I.A.D will sell low level minerals at Jita price plus 5%. We sell mid level minerals at Jita price minus 10%. We will sell high level minerals at Jita price minus 10%, minus hauling costs (so traders can make 10% Jita price profit).</p>
  <p>To buy minerals from T.R.I.A.D, please create a buy contract to T.R.I.A.D. Enter the values of minerals need into the table below and enter the Total ISK as the 'I will pay' value in the contract. For large orders, contact DrButterfly PHD to discuss current stock levels before creating the contract.</p>
    <?php
      outputPriceTable($sellMineralsData, 'Sell');
    ?>
</div>

<div class="help-section">
  <h3>What Should I Mine?</h3>
  <p>Based on the buyback prices above and a reprocessing efficiency of 50%, each jetcan of ore (27,500m3) has the following ISK value.</p>
    <div class="station-div">
      <table>
        <tr>
          <th>Ore</th>
          <th>ISK Per Can</th>
        </tr>
    <?php
      foreach ($oreValues as $ore => $value) {
        ?>
          <tr>
            <td><?=$ore?></td>
            <td><?=number_format($value, 2)?> ISK</td>
          </tr>
        <?php
      }
    ?>
  </table>
</div>