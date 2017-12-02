<h2>Fleet Mining History</h2>

<div class="help-section">
  <?php
    require_once( __DIR__ . "/../../resources/classes/mininghistory.php");
    $miningHistory = new MiningHistory();
    if (isset($_GET['fleetid'])){
      outputFleet(
        $miningHistory->getFleet($_GET['fleetid']),
        $miningHistory->getLedger($_GET['fleetid'])
      );
    }
    else {
      outputFleetList(
        $miningHistory->getFleets()
      );
    }

    function outputFleetList($list){
      ?>
        <h3>Fleet List</h3>
        <div class="station-div">
          <table>
            <tr>
              <th>Owner</th>
              <th>System</th>
              <th>Start Date</th>
              <th>View</th>
            </tr>
            <?php 
              foreach ($list as $index => $fleet) {
                ?>
                  <tr>
                    <td><img src="<?=$fleet->owner->portrait->px64x64?>"/> <?=$fleet->owner->character->name?></td>
                    <td><?=$fleet->solarSystemName?></td>
                    <td><?=date('jS M Y', strtotime($fleet->startDate))?></td>
                    <td>
                      <a href="?p=mininghistory&fleetid=<?=$fleet->fleetId?>" target="_blank">View Ledger</a>
                    </td>
                  </tr>
                <?php
              }
            ?>
          </table>
        </div>
      <?php
    }

    function outputFleet($fleet, $ledger){
      ?>
        <h3>
          <?=$fleet->solarSystemName?> 
          - <?=$fleet->owner->character->name?> 
          - <?=date('jS M Y', strtotime($fleet->startDate))?>
        </h3>
        <div class="station-div">
          <table>
            <tr>
              <th>Miner</th>
              <th>Ore</th>
              <th>Quantity</th>
              <th>Value</th>
            </tr>
            <?php 
              $payout = (object)[];
              $totalMined = (object)[];
              $totalValue = 0;
              foreach ($ledger as $index => $row) {
                $miner = $row->minerName;
                if (isset($payout->$miner)){
                  $payout->$miner += $row->value;
                } 
                else {
                  $payout->$miner = $row->value;
                }
                $oreName = $row->typeName;
                if (isset($totalMined->$oreName)){
                  $totalMined->$oreName += $row->quantity;
                } 
                else {
                  $totalMined->$oreName = $row->quantity;
                }
                $totalValue += $row->value;
                ?>
                  <tr>
                    <td><?=$miner?></td>
                    <td><?=$oreName?></td>
                    <td><?=$row->quantity?></td>
                    <td><?=$row->value?></td>
                  </tr>
                <?php
              }
            ?>
          </table>
          <table>
            <tr>
              <th>Miner</th>
              <th>Payout</th>
            </tr>
            <?php 
              foreach ($payout as $miner => $payout) {
                ?>
                  <tr>
                    <td><?=$miner?></td>
                    <td><?=$payout?></td>
                  </tr>
                <?php
              }
            ?>
          </table>
          <table>
            <tr>
              <th>Ore</th>
              <th>Quantitiy Mined</th>
            </tr>
            <?php 
              foreach ($totalMined as $ore => $quantity) {
                ?>
                  <tr>
                    <td><?=$ore?></td>
                    <td><?=$quantity?></td>
                  </tr>
                <?php
              }
            ?>
          </table>
          <p>Total Payout: <?=$totalValue?>ISK</p>
        </div>
      <?php
    }
  ?>
</div>