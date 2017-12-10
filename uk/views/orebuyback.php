<?php
  if (!isset($loggedIn) || $loggedIn != true){
    echo ('Accessing this file directly is not allowed.');
    die();
  }

  require_once( __DIR__ . "/../../resources/classes/orevalues.php");
  $reprocessrate = 0.8;
  $plain_oretax = 0.25;
  $moon_oretax = 0.25;
  $ice_tax = 1;
  $oreValuesFactory = new OreValues($reprocessrate, $plain_oretax, $moon_oretax, $ice_tax);

  $oreValues = $oreValuesFactory->get();


  function outputPriceTable($data){
    ?><div class="station-div">
      <p style="float:right;">Paste EVE Inventory: <input type="text" class="paste"></input></p>
      <table>
        <tr data-total="0">
          <th>Mineral</th>
          <th>Price</th>
          <th>Quantity</th>
          <th>ISK Total</th>
        </tr>
        <?php
          foreach ($data as $id => $ore) {
            ?>
              <tr id="buybackisk-<?php 
                 echo (str_replace(' ', '_', $ore->name));
                ?>" class="buybackisk-row buybackisk-<?php 
                 echo (str_replace(' ', '_', $ore->name));
                ?>" 
                data-price="<?=number_format($ore->value, 2, '.', '')?>" data-total="0">
                <td><?=$ore->name?></td>
                <td><?=number_format($ore->value, 2)?></td>
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

    $('.paste').keyup(function(event){
      var rawPaste = $('.paste').val();
      $('.paste').val('');
      var rows = rawPaste.split(/ISK./);
      var pasteBox = $(this);
      $.each(rows, function(index, row){
        cells = row.split(/\t/);
        var input = pasteBox.closest('div')
          .find('.buybackisk-' + cells[0].replace(/ /g,"_"))
          .find('.buybackisk-quantity')
          .children('input');
        input.val(parseInt(cells[1].replace(/,/g,'')));
        input.change();
      });
    });
 });
</script>
<h2>Calculate the Value of Ore</h2>
<div class="help-section">
  <h3>Ore Value calculator</h3>
  <p>Use this page to calculate the value of ore at 80% refining and 25% tax. It can be used with the output of moon mining fleets.</p>
    <?php
      outputPriceTable($oreValues);
    ?>
</div>
