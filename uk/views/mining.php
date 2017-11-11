<?php

// Get current character's fleet info. 
$characterFleet = $util->requestAndRetry('https://esi.tech.ccp.is/latest/characters/'.$character->id.'/fleet/?token='.$character->accessToken, null);

// Make sure user is FC of a live fleet, or else we won't be able to get the required data.
if (is_null($characterFleet)){
  echo ('<h2>You are not in a fleet. Please start a fleet in-game first!</h2>');
  die();
}

// Ensure fleet DB tables exist.
// Mining fleet table lists fleets and their owners.
if (!$db->tableExists('uk_miningfleet')){
  var_dump($db->createTable('uk_miningfleet', (object)[
    'fleetId' => (object) [
      'type' => 'INT',
      'size' => 20,
      'attributes' => ['NOT NULL','PRIMARY KEY']
    ],
    'ownerId' => (object) [
      'type' => 'INT',
      'size' => 20,
      'attributes' => ['NOT NULL']
    ],
    'solarSystemId' => (object) [
      'type' => 'INT',
      'size' => 20,
      'attributes' => ['NOT NULL']
    ],
    'startDate' => (object) [
      'type' => 'VARCHAR',
      'size' => 50,
      'attributes' => ['NOT NULL']
    ],
    'active' => (object) [
      'type' => 'BIT',
      'size' => 1,
      'attributes' => ['NOT NULL']
    ]
  ]));
}

// Mining fleet members lists all members for each fleet.
if (!$db->tableExists('uk_miningfleet_members')){
  var_dump($db->createTable('uk_miningfleet_members', (object)[
    'recordId' => (object) [
      'type' => 'INT',
      'size' => 20,
      'attributes' => ['NOT NULL','PRIMARY KEY', 'AUTO_INCREMENT']
    ],
    'fleetId' => (object) [
      'type' => 'INT',
      'size' => 20,
      'attributes' => ['NOT NULL']
    ],
    'minerId' => (object) [
      'type' => 'INT',
      'size' => 20,
      'attributes' => ['NOT NULL']
    ]
  ]));
}

// Mining fleet pre fleet mining lists all prefleet mining for each member of each fleet. 
if (!$db->tableExists('uk_miningfleet_prefleetmining')){
  var_dump($db->createTable('uk_miningfleet_prefleetmining', (object)[
    'recordId' => (object) [
      'type' => 'INT',
      'size' => 20,
      'attributes' => ['NOT NULL','PRIMARY KEY', 'AUTO_INCREMENT']
    ],
    'fleetId' => (object) [
      'type' => 'INT',
      'size' => 20,
      'attributes' => ['NOT NULL']
    ],
    'minerId' => (object) [
      'type' => 'INT',
      'size' => 20,
      'attributes' => ['NOT NULL']
    ],
    'date' => (object) [
      'type' => 'VARCHAR',
      'size' => 50,
      'attributes' => ['NOT NULL']
    ],
    'typeId' => (object) [
      'type' => 'INT',
      'size' => 20,
      'attributes' => ['NOT NULL']
    ],
    'quantity' => (object) [
      'type' => 'INT',
      'size' => 20,
      'attributes' => ['NOT NULL']
    ]
  ]));
}

$activeFleet = null;

if ($characterFleet->role === "fleet_commander"){
  // Check if there is an active fleet associated with the current user
  $fleets = $db->getRow('uk_miningfleet', [
    'ownerId' => $character->id
  ]);

  if (count($fleets) > 0){
    foreach ($fleets as $index => $fleetrow) {
      if ($fleetrow->active == 1){
        $activeFleet = $fleetrow;
        break;
      }
    }
  }
} else {
  // Check for an active fleet the user is a member of.
  $fleetmembers = $db->getRow('uk_miningfleet_members', [
    'minerId' => $character->id
  ]);

  if (count($fleetmembers) > 0){
    foreach ($fleetmembers as $memberindex => $fleetmember) {
      $fleets = $db->getRow('uk_miningfleet', [
        'fleetId' => $fleetmember->fleetId
      ]);
      if (count($fleets) > 0){
        foreach ($fleets as $fleetindex => $fleetrow) {
          if ($fleetrow->active == 1){
            $activeFleet = $fleetrow;
            break;
          }
        }
      }
      if (!is_null($activeFleet)){
        break;
      }
    }
  }
}

if (
  is_null($activeFleet) 
  && isset($_GET['action']) 
  && $_GET['action'] == 'start' 
  && $characterFleet->role === "fleet_commander"
){
  // There is no active fleet and the FC user has clicked the start fleet button.

  // TODO: set up the fleet database entries and refresh the page.  
  echo ('<h2>Hey! You started a fleet! Woo!</h2>');
  die();
}

if (!is_null($activeFleet)){
  // There is an active fleet either from the database or just created by the start fleet button. 
  // TODO: Build out active fleet object with all the lovely data and display that on the page.

  // TODO: If user is FC, update fleet members.
  if ($characterFleet->role === "fleet_commander"){
    $fleetMembers = $util->requestAndRetry('https://esi.tech.ccp.is/latest/fleets/'.$characterFleet->fleet_id.'/members/?token='.$character->accessToken, null);
  }

  echo ('<h2>Hey! You have an active fleet! Woo!</h2>');
  die();
}



?>

<script type="text/javascript">
var miningFleetData = <?php
  echo json_encode($character, JSON_PRETTY_PRINT);
?>;
</script>

<h2>Manage your mining fleet</h2>

<div class="help-section">
  <h3>Mining Fleet Manager</h3>
  <?php
    if (is_null($activeFleet)) {
      ?>
        <form method="post" action="<?php 
          echo htmlspecialchars($_SERVER["PHP_SELF"]);
        ?>?p=mining&action=start">  
          <input type="submit" name="start" value="Start Mining Fleet">  
        </form>
      <?php
    }
  ?>
  <pre><?php var_dump($character)?></pre>
  <pre><?php var_dump($characterFleet)?></pre>
  <pre><?php var_dump($fleetMembers)?></pre>
</div>




