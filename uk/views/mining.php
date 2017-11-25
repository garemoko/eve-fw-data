<pre style="color:white;">

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
  $db->createTable('uk_miningfleet', (object)[
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
  ]);
}

// Mining fleet members lists all members for each fleet.
if (!$db->tableExists('uk_miningfleet_members')){
  $db->createTable('uk_miningfleet_members', (object)[
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
    'joinDate' => (object) [
      'type' => 'VARCHAR',
      'size' => 50,
      'attributes' => ['NOT NULL']
    ],
  ]);
}

// Mining fleet pre fleet mining lists all prefleet mining for each member of each fleet. 
if (!$db->tableExists('uk_miningfleet_prefleetmining')){
  $db->createTable('uk_miningfleet_prefleetmining', (object)[
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
  ]);
}

$activeFleet = null;
$fleetMembers = [];
$fcSolarSystem = '';
$fcDetails = null;


if (
  isset($_GET['action']) 
  && $_GET['action'] == 'stop' 
  && isset($_POST['fleetId'])
){

  $fleets = $db->getRow('uk_miningfleet', [
    'ownerId' => $character->id,
    'fleetId' => $_POST['fleetId']
  ]);
  if (count($fleets) > 0){
    $db->updateRow('uk_miningfleet', [
      'ownerId' => $character->id,
      'fleetId' => $_POST['fleetId']
    ],[
      'active' => false
    ]);
  }
}

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
    'ownerId' => $character->id,
    'active' => 1
  ]);

  if (count($fleets) > 0){
    $activeFleet = $fleets[0];
  }
} else {
  // Check for an active fleet the user is a member of.
  $dbfleetmembers = $db->getRow('uk_miningfleet_members', [
    'minerId' => $character->id
  ]);

  if (count($dbfleetmembers) > 0){
    foreach ($dbfleetmembers as $memberindex => $fleetmember) {
      $fleets = $db->getRow('uk_miningfleet', [
        'fleetId' => $fleetmember->fleetId,
        'active' => 1
      ]);
      if (count($fleets) > 0){
        $activeFleet = $fleets[0];
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
    foreach ($dbfleetmembers as $index => $dbmember) {
      array_push($minerIds, $dbmember->minerId);

      // get pre fleet mining (if any)
      $preFleetLedger = $db->getRow('uk_miningfleet_prefleetmining', [
        'minerId' => $dbmember->minerId,
        'fleetId' => $activeFleet->fleetId
      ]);

      // Get the member details from the fleet api
      $member = null;
      foreach ($fleetMembers as $index => $apiMember) {
        if ($apiMember->character_id == $dbmember->minerId){
          $member = $apiMember;
          break;
        }
      }

      // Member has left fleet
      if (is_null($member)){
        $member = (object)[
          'character_id' => $dbmember->minerId,
          'solar_system_id' => null,
          'ship_type_id' => null
        ];
      }

      $miner = getFleetMemberDetails($member->character_id, $member->solar_system_id, $member->ship_type_id, $util);

      // Calculate based on days member has been in fleet
      $joinDateTime = new DateTime($dbmember->joinDate);
      $nowDateTime = new DateTime();
      // Add 1 to include today. 
      $interval = date_diff($joinDateTime, $nowDateTime);
      $daysToCheck = $interval->days + 1; 

      // get mining ledger for member
      $ledger = getMemberMiningLedger(
        $member->character_id, 
        $activeFleet->solarSystemId, 
        $daysToCheck, 
        $db, $login, $util
      );
      if (is_null($ledger)){
        // If ledger not available, continue to the next fleet member. 
        array_push($miningRecord->unregistered, $miner);
        continue;
      }

      // Consolidate data
      $miningByRoid = (object)[];
      foreach ($ledger as $index => $ledgerItem) {
        $typeId = $ledgerItem->type_id;
        // If we already have a record of this roid type...
        if (isset($miningByRoid->$typeId)){
          // Add the quantitiy to the existing record
          $miningByRoid->$typeId =+ $ledgerItem->quantity;
        }
        else {
          // else, set the record to the quantity
          $miningByRoid->$typeId = $ledgerItem->quantity;
        }
      }

      // Calculate diff per roid
      foreach ($miningByRoid as $typeId => $quantity) {
        foreach ($preFleetLedger as $preFleetLedgerIndex => $preFleetLedgerItem) {
          if ($typeId === $preFleetLedger->type_id){
            $miningByRoid->$typeId -= $preFleetLedger->quantity;
            break;
          }
        }
      }

      // Record in output
      $miner->miningRecord = $miningByRoid;
      array_push($miningRecord->members, $miner);
    }
  }

  if ($characterFleet->role === "fleet_commander"){
    // for each fleet member...
    foreach ($fleetMembers as $index => $member) {
      // if not in active fleet
      if (!in_array($member->character_id, $minerIds)){
        // get character, location and ship info
        $miner = getFleetMemberDetails($member->character_id, $member->solar_system_id, $member->ship_type_id, $util);

        // get mining ledger for member
        $daysToCheck = 1; 
        $ledger = getMemberMiningLedger(
          $member->character_id, 
          $activeFleet->solarSystemId, 
          $daysToCheck, 
          $db, $login, $util
        );
        if (is_null($ledger)){
          array_push($miningRecord->unregistered, $miner);
          // If ledger not available, continue to the next fleet member. 
          continue;
        }

        // Add member to fleet list (only if $ledger is not null). 
        $db->addRow('uk_miningfleet_members', [ 
          'fleetId' => $activeFleet->fleetId,
          'minerId' => $member->character_id,
          'joinDate' => date('Y-m-d')
        ]);

        // Store starting ledger in DB
        foreach ($ledger as $index => $ledgerItem) {
          $db->addRow('uk_miningfleet_prefleetmining', [
            'fleetId' => $activeFleet->fleetId,
            'minerId' => $member->character_id,
            'date' => $ledgerItem->date,
            'typeId' => $ledgerItem->type_id,
            'quantity' => $ledgerItem->quantity
          ]);
        }

        // Start with empty fleet ledger
        $miner->miningRecord = (object)[];
        // record in output as nothing mined yet
        array_push($miningRecord->members, $miner);
      }
    }
  }
}

// output all mineral prices (use cache!) 
require_once( __DIR__ . "/../../resources/classes/mineralprices.php");
$mineralPricesFactory = new MineralPrices();
$mineralPrices = $mineralPricesFactory->get();

/* 
Other $mineralPricesFactory functions:

Get the lowest value out of current buy and historical prices for a given mineral (at 10% tax):

  $mineralPricesFactory->getJitaMinPriceById('34', 0.1);
  $mineralPricesFactory->getJitaMinPriceByName('Tritanium', 0.1);

Get the trade value of a given mineral, taking account hauling costs per m3 (at 25% tax):

  $mineralPricesFactory->getExportPriceById('34', 0.25, 500);
  $mineralPricesFactory->getExportPriceByName('Tritanium', 0.25, 500);

*/


//TODO - pull these out as a separate mining ledger class. 
function getFleetMemberDetails($memberId, $solarSystemId, $shipTypeId, $util){
  // get character, location and ship info
  $member = (object)[];
  $memberDataFetcher = new Character($memberId);
  $memberData = $memberDataFetcher->get();
  $member->character = (object)[
    'id' => $memberId,
    'name' => $memberData->character->name,
    'corp' => (object)[
      'name' => $memberData->corp->corporation_name,
      'ticker' => $memberData->corp->ticker
    ],
    'alliance' => (object)[
      'name' => $memberData->alliance->alliance_name,
      'ticker' => $memberData->alliance->ticker
    ],
    'portrait' => $memberData->portrait
  ];
  if (is_null($solarSystemId)){
    $member->solarSystem = 'unknown';
  }
  else {
    $member->solarSystem = $util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/universe/systems/'.$solarSystemId.'/', 
      null
    )->name;
  }
  
  if (is_null($shipTypeId)){
    $member->ship = 'unknown';
  }
  else {
    $member->ship = $util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/universe/types/'.$shipTypeId.'/', 
      null
    )->name;
  }
  return $member;
}
function getMemberMiningLedger($character_id, $solarSystemId, $days, $db, $login, $util){
  // check if we have an api key
  $rows = $db->getRow('uk_characters', [
    'id' => $character_id
  ]);
  // if not, return null
  if (count($rows) == 0){
    return null;
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
    return null;
  }
  // get mining ledger for member
  $ledger = $util->requestAndRetry('https://esi.tech.ccp.is/latest/characters/'.$minerAuth->id.'/mining/?token='.$minerAuth->accessToken, null);

  // filter to just $days most recent days and fleet system
  $dates = [];
  for ($i=0; $i < $days; $i++) { 
    array_push($dates, date(('Y-m-d'), strtotime('-'.$i.' days', time())));
  }

  return array_filter($ledger, function($ledgerItem) use ($solarSystemId, $dates){
    if (in_array($ledgerItem->date, $dates) && $ledgerItem->solar_system_id == $solarSystemId){
      return true;
    }
    return false;
  });
}

?>
</pre>

<script type="text/javascript">
var miningFleetData = <?php
  if (isset($miningRecord)){
    echo json_encode($miningRecord, JSON_PRETTY_PRINT);
  }
  else {
    echo '{}';
  }
?>;
var mineralPrices = <?php
  echo json_encode($mineralPrices, JSON_PRETTY_PRINT);
?>;
console.log(miningFleetData);
console.log(JSON.stringify(miningFleetData,null,2));
console.log(mineralPrices);
console.log(JSON.stringify(mineralPrices,null,2));
</script>

<h2>Manage your mining fleet</h2>

<div class="help-section">
  <h3>Mining Fleet Manager</h3>
  <?php
    if (is_null($activeFleet) && $characterFleet->role === "fleet_commander") {
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
    else if ($characterFleet->role === "fleet_commander") {
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




