<pre style="color:white;">

<?php
  if (!isset($loggedIn) || $loggedIn != true){
    echo ('Accessing this file directly is not allowed.');
    die();
  }

require_once( __DIR__ . "/../../resources/classes/miningfleet.php");
//Reprocess rate for lowsec + t1 rig + max skills + 4% implant is 0.797675
$reprocessRate = 0.797675;
$oreTax = 0.2;
$moonTax = 0.25;
$iceTax = 0.2;

$miningFleetFactory = new MiningFleet($reprocessRate, $oreTax, $moonTax, $iceTax);

// Get current character's fleet info. 
$miningFleetFactory->setGameFleet($character->id, $character->accessToken);

// Make sure user is FC of a live fleet, or else we won't be able to get the required data.
if (is_null($miningFleetFactory->getGameFleet())){
  echo ('<h2>You are not in a fleet. Please start a fleet in-game first!</h2>');
  die();
}

// Set the active fleet if one exists in the database
if ($miningFleetFactory->isFleetCommander() === true){
  $miningFleetFactory->setFleetMembers($character->accessToken);
  $miningFleetFactory->setActiveFleetByOwner($character->id);
} else {
  // Check for an active fleet the user is a member of.
  $miningFleetFactory->setActiveFleetByMember($character->id);
}

// If there is no active fleet in the database, 
// and the current user is FC, 
// and they clicked the 'start fleet' button
// start a new fleet! 
if (
  $miningFleetFactory->isActiveFleet() === false
  && isset($_GET['action']) 
  && $_GET['action'] == 'start' 
  && $miningFleetFactory->isFleetCommander() === true
){
  $result = $miningFleetFactory->startFleet($_POST['solarSystem']);

  if ($result->success == false){
    echo ('<h2>'.$result->error.'</h2>');
    die();
  }
}

// If there is an active fleet (either from the database or just created by the start fleet button). 
if ($miningFleetFactory->isActiveFleet() === true){

  // Update mining details for all fleet members already in the database;
  $miningFleetFactory->setCurrentFleetMemberMining();

  // If the current user is FC, check for new fleet members and add them to the database;
  if ($miningFleetFactory->isFleetCommander() === true){
    $miningFleetFactory->setNewFleetMemberMining();
  }
}

// Handle stop fleet action.
if (
  isset($_GET['action']) 
  && $_GET['action'] == 'stop' 
  && $miningFleetFactory->isActiveFleet() === true
  && $miningFleetFactory->isFleetCommander() === true
){
  $miningFleetFactory->closeFleet();
}

?>
</pre>

<h2>Manage your mining fleet</h2>

<div class="help-section">
  <h3>Mining Fleet Manager</h3>
  <?php
    if ($miningFleetFactory->isActiveFleet() === false && $miningFleetFactory->isFleetCommander() === true) {
      $solarSystem = $miningFleetFactory->getGameFleet()->fleetCommander->solarSystem;
      ?>
        <form method="post" action="<?php 
          echo htmlspecialchars($_SERVER["PHP_SELF"]);
        ?>?p=mining&action=start">
          <p>Fleet Solar System:
            <input type="text" name="solarSystem" value="<?=$solarSystem?>"> 
            <input type="submit" name="start" value="Start Mining Fleet">  
          </p>
        </form>
      <?php
    }
    else if ($miningFleetFactory->isFleetCommander() === true) {
      $fleetId = $miningFleetFactory->getActiveFleet()->fleetId;
      ?>
        <form method="post" action="<?php 
          echo htmlspecialchars($_SERVER["PHP_SELF"]);
        ?>?p=mining&action=stop">
          <p>
            <input type="hidden" name="fleetId" value="<?=$fleetId?>"> 
            <input type="submit" name="stop" value="Save and Close Mining Fleet"> 
          </p>
          <p>Note: you must also leave and start a new fleet in game before starting to track a new fleet.</p>
        </form>
      <?php 
    }
    if ($miningFleetFactory->isActiveFleet() === true){ 
      $fleet = $miningFleetFactory->getActiveFleet();
      $commander = $miningFleetFactory->getFleetCommander();
      ?>
      <h3>
        <img src="<?=$commander->character->portrait->px64x64?>" style="height:30px;"/>
        <?=$fleet->solarSystemName?> 
        - <?=$commander->character->name?> 
        - <?=date('jS M Y', strtotime($fleet->startDate))?>
      </h3>
      <script type="text/javascript">
        var refreshMinutes = 1;
        var refreshTimer = setTimeout(
          function(){
            window.location.href = '?p=mining';
          },
          1000 * 60 * refreshMinutes
        );
      </script>
      <div class="station-div">
        <?php 
          $miningRecord = $miningFleetFactory->getMiningRecord();
        ?>
        <h4>Ledger</h4>
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
            foreach ($miningRecord->members as $minerIndex => $miner) {
              foreach ($miner->miningRecord as $oreIndex => $ore) {
                $minerName = $miner->character->name;
                if (isset($payout->$minerName)){
                  $payout->$minerName += $ore->value;
                } 
                else {
                  $payout->$minerName = $ore->value;
                }
                $oreName = $ore->name;
                if (isset($totalMined->$oreName)){
                  $totalMined->$oreName += $ore->quantity;
                } 
                else {
                  $totalMined->$oreName = $ore->quantity;
                }
                $totalValue += $ore->value;
                ?>
                  <tr>
                    <td><?=$minerName?></td>
                    <td><?=$oreName?></td>
                    <td><?=number_format($ore->quantity, 0)?></td>
                    <td><?=number_format($ore->value, 2)?></td>
                  </tr>
                <?php
              }
            }
          ?>
        </table>
        <h4>Payouts Due</h4>
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
                  <td><?=number_format($payout, 2)?></td>
                </tr>
              <?php
            }
          ?>
        </table>
        <p>Total Payout: <?=number_format($totalValue, 2)?> ISK</p>
        <h4>Total Mined</h4>
        <table>
          <tr>
            <th>Ore</th>
            <th>Quantity Mined</th>
          </tr>
          <?php 
            foreach ($totalMined as $ore => $quantity) {
              ?>
                <tr>
                  <td><?=$ore?></td>
                  <td><?=number_format($quantity, 0)?></td>
                </tr>
              <?php
            }
          ?>
        </table>
      </div>
      
    <?php
      if (count($miningRecord->unregistered) > 0){
        ?>
          <p>
            The following pilots are in fleet but they are not registered. Their mining is not being tracked:
            <ul>
              <?php
                foreach ($miningRecord->unregistered as $index => $unregistredMiner) {
                  ?>
                    <li style="list-style: none; background-image: url('<?=$unregistredMiner->character->portrait->px64x64?>');background-size: contain; background-repeat: no-repeat; padding-left:20px; height:20px;">
                      <?=$unregistredMiner->character->name?>
                    </li>
                  <?php
                }
              ?>
            </ul>
          </p>
        <?php
      }
    }
  ?>
</div>




