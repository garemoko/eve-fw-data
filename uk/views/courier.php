<h2>Get your stuff moved</h2>

<div class="help-section">
  <h3>Courier contract calculator</h3>
  <p>Size of contract in m3: <input type="number" id="size" name="size" lang="en-150"/> 
  Order cost: <input type="text" id="cost" name="cost" value="0" disabled="disabled" lang="en-150" />ISK</p>
  <p>To get stuff moved from Jita to UAV-1E you should:</p>
  <ol>
    <li>Make a private courier contract to '<a href="https://evewho.com/pilot/Yerik" target="_blank">Yerik</a>'.</li>
    <li>Set the Pick Up point to <b>Jita IV - Moon 4 - Caldari Navy Assembly Plant</b> or <b>UAV-1E - The Butterfly Net</b> and select items. <strong>Do not make contracts for any other station or structure</strong>.</li>
    <li>Set the Ship To point to <b>Jita IV - Moon 4 - Caldari Navy Assembly Plant</b> or <b>UAV-1E - The Butterfly Net</b> and select items. <strong>Do not make contracts for any other station or structure</strong>. Use the calculator above to caluclate the minimum reward. Enter that. Use Est. Price to calculate Collateral and set a long Expiration and Days to Complete.</li>
    <li>Finish the contract</li>
  </ol>
  <p>Additional tools to help you track your orders will be added below soon(TM).</p>
</div>

<script type="text/javascript">
  onloadFunctions.push( function() {
    $('#size').change(updateCost);
    $('#size').keyup(updateCost);
    updateCost();
    function updateCost(){
      $('#size').val(parseFloat($('#size').val()));
      var cost = $('#size').val() * 500;
      cost = cost < 2500000 ? '250,0000' : cost.toString().split(/(?=(?:\d{3})+$)/).join(",");
      $('#cost').val(cost);
    }
 });
</script>