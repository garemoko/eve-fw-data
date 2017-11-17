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
    'solarSystemName' => (object) [
      'type' => 'VARCHAR',
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
$fleetMembers = [];
$fcSolarSystem = '';
$fcDetails = null;

if ($characterFleet->role === "fleet_commander"){
  $fleetMembers = $util->requestAndRetry('https://esi.tech.ccp.is/latest/fleets/'.$characterFleet->fleet_id.'/members/?token='.$character->accessToken, null);

  foreach ($fleetMembers as $index => $member) {
    if ($member->role === "fleet_commander"){
      $fcDetails = $member;
      break;
    }
  }

   if (!is_null($fcDetails)){
    $fcSolarSystem = $util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/universe/systems/'.$fcDetails->solar_system_id.'/', 
      null
    );
  }

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
  $dbfleetmembers = $db->getRow('uk_miningfleet_members', [
    'minerId' => $character->id
  ]);

  if (count($dbfleetmembers) > 0){
    foreach ($dbfleetmembers as $memberindex => $fleetmember) {
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

  $search = $util->requestAndRetry(
    'https://esi.tech.ccp.is/latest/search/?categories=solarsystem&strict=false&search=' . urlencode($_POST['solarSystem']),
    []
  );

  if (!isset($search->solarsystem)){
    echo ('<h2>Solar system '.$_POST['solarSystem'].' not found.</h2>');
    die();
  }

  $solarSystem = $search->solarsystem[0];

  // There is no active fleet and the FC user has clicked the start fleet button.
  $activeFleetArr = [
    'fleetId' => $characterFleet->fleet_id,
    'ownerId' => $character->id,
    'solarSystemId' => $solarSystem,
    'solarSystemName' => $_POST['solarSystem'],
    'startDate' => date('c'),
    'active' => 1
  ];
  $db->addRow('uk_miningfleet', $activeFleetArr);

  $activeFleet = (object) $activeFleetArr;
}

if (!is_null($activeFleet)){
  // There is an active fleet either from the database or just created by the start fleet button. 
  // If user is FC, update fleet members.
  $miningRecord = (object)[
    'members' => [],
    'unregistered' => []
  ];

  $dbfleetmembers = $db->getRow('uk_miningfleet_members', [
    'fleetId' => $activeFleet->fleetId
  ]);

  $minerIds = [];
  if (count($dbfleetmembers) > 0){
    // for each fleet member in active fleet already
    foreach ($dbfleetmembers as $index => $member) {
      array_push($minerIds, $member->minerId);

      // get pre fleet mining (if any)
      $startingLedger = $db->getRow('uk_miningfleet_prefleetmining', [
        'minerId' => $member->minerId,
        'fleetId' => $activeFleet->fleetId
      ]);

      // TODO: get mining ledger for member
      // TODO: calculate diff per roid
      // TODO: record in output
        // $miner = ...
        // array_push($miningRecord->members, $miner);
    }
  }

  if ($characterFleet->role === "fleet_commander"){
    // for each fleet member...
    foreach ($fleetMembers as $index => $member) {
      // if not in active fleet
      if (!in_array($member->character_id, $minerIds)){
        // get character, location and ship info
        $miner = (object)[];
        $minerData = new Character($character->id);
        $miner->data = $characterData->get();
        $miner->solarSystem = $util->requestAndRetry(
          'https://esi.tech.ccp.is/latest/universe/systems/'.$member->solar_system_id.'/', 
          null
        );
        $miner->ship = $util->requestAndRetry(
          'https://esi.tech.ccp.is/latest/universe/types/'.$member->ship_type_id.'/', 
          null
        );
        // check if we have an api key
        $rows = $db->getRow('uk_characters', [
          'id' => $member->character_id
        ]);
        // if not, add to 'no api' list and continue to next fleet member
        if (count($rows) == 0){
          array_push($miningRecord->unregistered, $miner);
          continue;
        }

        // refresh API token
        $minerAuth = (object)[
          'id' => $rows[0]->id,
          'refreshToken' => $rows[0]->refreshToken
        ];

        // Resfresh and store access token
        $refreshresponse = $login->refreshToken($minerAuth->refreshToken);
        if ($refreshresponse->status == 200){
          $token = json_decode($refreshresponse->content);
          $minerAuth->accessToken = $token->access_token;
          $minerAuth->refreshToken = $token->refresh_token;
          $db->updateRow('uk_characters', [
            'id' => $minerAuth->id
          ] , [
            'accessToken' => $minerAuth->accessToken,
            'refreshToken' => $minerAuth->refreshToken 
          ]);
        }
        else {
          echo('<h2>Error refreshing API Token for '.$miner->data->character->name.'.</h2>');
          die();
        }
        // get mining ledger for member
        $ledger = $util->requestAndRetry('https://esi.tech.ccp.is/latest/characters/'.$minerAuth->id.'/mining/?token='.$minerAuth->accessToken, null);
        //filter to just today and fleet system

         $startingLedger = array_filter($ledger, function($ledgerItem) use ($activeFleet){
          if ($ledgerItem->date == date("Y-m-d") && $ledgerItem->solar_system_id == $activeFleet->solarSystemId){
            return true;
          }
          return false;
        });
        // TODO: store in DB

         // Start with empty fleet ledger
         $miner->ledger = (object)[];
         $miner->ledger = $startingLedger; //TODO: delete this line
        // record in output as nothing mined yet
        array_push($miningRecord->members, $miner);
      }
    }
  }
}

// TODO: output all mineral prices (use cache!) 

?>

<script type="text/javascript">
var miningFleetData = <?php
  echo json_encode($miningRecord, JSON_PRETTY_PRINT);
?>;
console.log(miningFleetData);
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
          <p>Fleet Solar System:
            <input type="text" name="solarSystem" value="<?=$fcSolarSystem->name?>"> 
            <input type="submit" name="start" value="Start Mining Fleet">  
          </p>
        </form>
      <?php
    }
  ?>
</div>




