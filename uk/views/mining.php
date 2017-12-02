<pre style="color:white;">

<?php
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
  $miningFleetFactory->setFleetMembers();
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
  $miningFleetFactory->closeFleet()
}

?>
</pre>

<script type="text/javascript">
  /* TODO: refresh the page every x seconds */
  var miningFleetData = <?php
    // Output all data as a single JS object. 
    echo json_encode($miningFleetFactory->getMiningRecord, JSON_PRETTY_PRINT);
  ?>;
  console.log(miningFleetData);
  console.log(JSON.stringify(miningFleetData,null,2));
</script>

<h2>Manage your mining fleet</h2>

<div class="help-section">
  <h3>Mining Fleet Manager</h3>
  <?php
    if (is_null($activeFleet) && $miningFleetFactory->isFleetCommander() === true) {
      ?>
        <form method="post" action="<?php 
          echo htmlspecialchars($_SERVER["PHP_SELF"]);
        ?>?p=mining&action=start">
          <p>Fleet Solar System:
            <input type="text" name="solarSystem" value="<?=$fcSolarSystem->name?>"> 
            <input type="submit" name="start" value="Start Mining Fleet">  
          </p>
        </form>
      <?php
    }
    else if ($miningFleetFactory->isFleetCommander() === true) {
      ?>
      <form method="post" action="<?php 
        echo htmlspecialchars($_SERVER["PHP_SELF"]);
      ?>?p=mining&action=stop">
        <p>Warning! Do not close the fleet until you have saved the data!</p>
        <p>
          <input type="hidden" name="fleetId" value="<?=$activeFleet->fleetId?>"> 
          <input type="submit" name="stop" value="Close Mining Fleet"> 
        </p>
        <p>Note: you must also leave and start a new fleet in game before starting to track a new fleet.</p>
      </form>
    <?php
    }
  ?>
</div>




