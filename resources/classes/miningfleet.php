<?php

require_once(__DIR__ . "/orevalues.php");
require_once(__DIR__ . "/util.php");
require_once(__DIR__ . "/login.php");
require_once(__DIR__ . "/database.php");

class MiningFleet {
  private $util;
  private $login;
  private $db;

  private $oreValues;
  private $reprocessrate;
  private $plain_oretax;
  private $moon_oretax;
  private $ice_tax;

  private $fleet;
  private $miningRecord;

  public function __construct($reprocessrate, $plain_oretax, $moon_oretax, $ice_tax){
    $this->util = new Util();
    $this->login = new Login();
    $this->db = new Database();

    $this->oreValues = new OreValues($reprocessrate, $plain_oretax, $moon_oretax, $ice_tax);
    $this->plain_oretax = $plain_oretax;
    $this->moon_oretax = $moon_oretax;
    $this->ice_tax = $ice_tax;
    $this->reprocessrate = $reprocessrate;

    $this->fleet = (object)[
      'gameFleet' => null,
      'activeFleet' => null
    ];

    $this->miningRecord = (object)[
      'members' => [],
      'unregistered' => []
    ];

    $this->createTables();
  }

  // Action Functions
  public function startFleet($solarSystem){
    $search = $this->util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/search/?categories=solarsystem&strict=false&search=' . urlencode($solarSystem),
      []
    );

    if (!isset($search->solarsystem)){
      return (object)[
        'success' => false,
        'error' => 'Solar system '.$solarSystem.' not found.'
      ];
    }

    $solarSystemId = $search->solarsystem[0];

    // There is no active fleet and the FC user has clicked the start fleet button.
    $activeFleetArr = [
      'fleetId' => $this->fleet->gameFleet->fleet_id,
      'ownerId' => $this->fleet->gameFleet->fleetCommander->character->id,
      'solarSystemId' => $solarSystemId,
      'solarSystemName' => $solarSystem,
      'startDate' => date('c'),
      'active' => 1
    ];
    $this->db->addRow('uk_miningfleet', $activeFleetArr);

    $this->setActiveFleetByObject((object) $activeFleetArr);

    return (object)[
      'success' => true
    ];

  }
  public function closeFleet(){
    $fleetId = $this->fleet->activeFleet->fleetId;

    // Save final ledger to history
    foreach ($this->miningRecord->members as $minerIndex => $miner) {
      foreach ($miner->miningRecord as $oreIndex => $ore) {
        $this->db->addRow('uk_miningfleet_historicmining', [
            'fleetId' => $fleetId,
            'minerId' => $miner->character->id,
            'minerName' => $miner->character->name,
            'typeId' => $ore->id,
            'typeName' => $ore->name,
            'quantity' => $ore->quantity,
            'value' => $ore->value
        ]);
      }
    }

    // Deactivate the fleet
    $this->db->updateRow('uk_miningfleet', [
      'fleetId' => $fleetId
    ],[
      'active' => false
    ]);
    $this->fleet->activeFleet = null;

    // Remove pre-fleet mining from db
    $this->db->deleteRow('uk_miningfleet_prefleetmining', [
      'fleetId' => $fleetId
    ]);
  }

  // Getter / Setter functions
  public function setGameFleet ($characterId, $characterAccessToken){
    $this->fleet->gameFleet = $this->util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/characters/'.$characterId.'/fleet/?token='.$characterAccessToken, 
      null
    );
    if (is_null($this->fleet->gameFleet)){
      return null;
    }
    $this->fleet->gameFleet->members = [];
    $this->fleet->gameFleet->fleetCommander = (object)[
      'solarSystem' => ''
    ];
    return $this->fleet->gameFleet;
  }

  public function getGameFleet(){
    return $this->fleet->gameFleet;
  }

  public function setFleetMembers($accessToken){
    $this->fleet->gameFleet->members = $this->util->requestAndRetry('https://esi.tech.ccp.is/latest/fleets/'.$this->fleet->gameFleet->fleet_id.'/members/?token='.$accessToken, null);

    foreach ($this->fleet->gameFleet->members as $index => $member) {
      if ($member->role === "fleet_commander"){
        $this->fleet->gameFleet->fleetCommander = $this->getFleetMemberDetails($member->character_id, $member->solar_system_id, $member->ship_type_id);
        break;
      }
    }
  }

  public function getFleetMembers(){
    return $this->fleet->gameFleet->members;
  }

  public function getFleetCommander(){
    if (isset($this->fleet->gameFleet->fleetCommander)){
      return $this->fleet->gameFleet->fleetCommander;
    }
    return null;
  }

  public function isFleetCommander($characterId = null){
    if (!is_null($this->fleet->gameFleet) && $this->fleet->gameFleet->role === "fleet_commander"){
      return true;
    }
    if (!is_null($characterId) && !is_null($this->fleet->activeFleet) && $characterId == $this->fleet->activeFleet->ownerId){
      return true;
    }
    return false;
  }

  public function setActiveFleetByOwner($characterId){
    // Check if there is an active fleet associated with the current user
    $fleets = $this->db->getRow('uk_miningfleet', [
      'ownerId' => $characterId,
      'active' => 1
    ]);

    if (count($fleets) > 0){
      $this->fleet->activeFleet = $fleets[0];
      return $this->fleet->activeFleet;
    }

    return null;
  }

  public function setActiveFleetByMember($characterId){
    $dbfleetmembers = $this->db->getRow('uk_miningfleet_members', [
      'minerId' => $characterId
    ]);

    if (count($dbfleetmembers) > 0){
      foreach ($dbfleetmembers as $memberindex => $fleetmember) {
        $fleets = $this->db->getRow('uk_miningfleet', [
          'fleetId' => $fleetmember->fleetId,
          'active' => 1
        ]);
        if (count($fleets) > 0){
          $this->fleet->activeFleet = $fleets[0];
          return $this->fleet->activeFleet;
        }
      }
    }
  }

  public function setActiveFleetByObject($activeFleet){
    $this->fleet->activeFleet = $activeFleet;
  }

  public function getActiveFleet(){
    return $this->fleet->activeFleet;
  }

  public function isActiveFleet(){
    if (is_null($this->fleet->activeFleet)){
      return false;
    }
    return true;
  }

  public function isGameFleet(){
    if (is_null($this->fleet->gameFleet)){
      return false;
    }
    return true;
  }

  public function setCurrentFleetMemberMining(){
    $dbfleetmembers = $this->db->getRow('uk_miningfleet_members', [
      'fleetId' => $this->fleet->activeFleet->fleetId
    ]);

    if (count($dbfleetmembers) > 0){
      // for each fleet member in active fleet already
      foreach ($dbfleetmembers as $index => $dbmember) {
        $miner = $this->getMiner($dbmember->minerId, $dbmember->joinDate);
        if ($miner->registered === true){
          array_push($this->miningRecord->members, $miner);
        }
        else {
          array_push($this->miningRecord->unregistered, $miner);
        }
      }
    }
  }

  public function setNewFleetMemberMining(){
    foreach ($this->fleet->gameFleet->members as $index => $member) {
      // if not in active fleet
      if ($this->isInMiningRecord($member->character_id) == false){
        // get character, location and ship info
        $miner = $this->getFleetMemberDetails($member->character_id, $member->solar_system_id, $member->ship_type_id);

        $registered = $this->setPreFleetMining($member->character_id);

        // Start with empty fleet ledger
        $miner->miningRecord = [];

        if ($registered == true){
          array_push($this->miningRecord->members, $miner);
           $this->db->addRow('uk_miningfleet_members', [ 
            'fleetId' => $this->fleet->activeFleet->fleetId,
            'minerId' => $member->character_id,
            'joinDate' => date('Y-m-d')
          ]);
        }
        else {
          array_push($this->miningRecord->unregistered, $miner);
        }
      }
    }
  }

  private function setPreFleetMining($minerId){
    // get mining ledger for member
    $daysToCheck = 1; 
    $ledger = $this->getMemberMiningLedger(
      $minerId, 
      $this->fleet->activeFleet->solarSystemId, 
      $daysToCheck
    );
    if (is_null($ledger)){
      return false;
    }

    // Store starting ledger in DB
    foreach ($ledger as $index => $ledgerItem) {
      $this->db->addRow('uk_miningfleet_prefleetmining', [
        'fleetId' => $this->fleet->activeFleet->fleetId,
        'minerId' => $minerId,
        'date' => $ledgerItem->date,
        'typeId' => $ledgerItem->type_id,
        'quantity' => $ledgerItem->quantity
      ]);
    }
    return true;
  }

  private function isInMiningRecord($minerId){
    foreach ($this->miningRecord->members as $index => $miner) {
      if ($minerId == $miner->character->id){
        return true;
      }
    }
    return false;
  }

  public function getMinerMining($minerId, $joinDate){
    // Calculate based on days member has been in fleet
    $joinDateTime = new DateTime($joinDate);
    $nowDateTime = new DateTime();
    // Add 1 to include today. 
    $interval = date_diff($joinDateTime, $nowDateTime);
    $daysToCheck = $interval->days + 1; 

    // get mining ledger for member
    $ledger = $this->getMemberMiningLedger(
      $minerId, 
      $this->fleet->activeFleet->solarSystemId, 
      $daysToCheck
    );
    if (is_null($ledger)){
      // If ledger not available, continue to the next fleet member. 
      return (object)[
        'registered' => false,
        'miningRecord' => []
      ];
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

    // get pre fleet mining (if any)
    $preFleetLedger = $this->db->getRow('uk_miningfleet_prefleetmining', [
      'minerId' => $minerId,
      'fleetId' => $this->fleet->activeFleet->fleetId
    ]);

    // Calculate diff per roid
    foreach ($miningByRoid as $typeId => $quantity) {
      foreach ($preFleetLedger as $preFleetLedgerIndex => $preFleetLedgerItem) {
        if ($typeId === $preFleetLedgerItem->typeId){
          $miningByRoid->$typeId -= $preFleetLedgerItem->quantity;
          break;
        }
      }
    }

    // add ISK value
    $miningRecord = [];
    foreach ($miningByRoid as $typeId => $quantity) {
      array_push($miningRecord, (object)[
        'id' => $typeId,
        'name' => $this->oreValues->getNameById($typeId),
        'quantity' => $quantity,
        'value' => $this->oreValues->getOreValuebyID($typeId, $quantity)
      ]);
    }

    return (object)[
      'registered' => true,
      'miningRecord' => $miningRecord
    ];
}

  public function getMiner($minerId, $joinDate){
    // Get the member details from the fleet api
    $member = null;
    if (!is_null($this->fleet->gameFleet)){
      foreach ($this->fleet->gameFleet->members as $index => $apiMember) {
        if ($apiMember->character_id == $minerId){
          $member = $apiMember;
          break;
        }
      }
    }

    // Member has left fleet
    if (is_null($member)){
      $member = (object)[
        'character_id' => $minerId,
        'solar_system_id' => null,
        'ship_type_id' => null
      ];
    }

    $miner = $this->getFleetMemberDetails(
      $member->character_id, 
      $member->solar_system_id, 
      $member->ship_type_id
    );

    $minerMining = $this->getMinerMining($minerId, $joinDate);

    if ($minerMining->registered === true){
      $miner->miningRecord = $minerMining->miningRecord;
      $miner->registered = true;
    }
    else {
      $miner->miningRecord = [];
      $miner->registered = false;
    }

    return $miner;
  }

  public function getFleetMemberDetails($memberId, $solarSystemId, $shipTypeId){
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
      $member->solarSystem = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/universe/systems/'.$solarSystemId.'/', 
        null
      )->name;
    }
    
    if (is_null($shipTypeId)){
      $member->ship = 'unknown';
    }
    else {
      $member->ship = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/universe/types/'.$shipTypeId.'/', 
        null
      )->name;
    }
    return $member;
  }

  public function getMemberMiningLedger($character_id, $solarSystemId, $days){
    // check if we have an api key
    $rows = $this->db->getRow('uk_characters', [
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
    $refreshresponse = $this->login->refreshToken($minerAuth->refreshToken);
    if ($refreshresponse->status == 200){
      $token = json_decode($refreshresponse->content);
      $minerAuth->accessToken = $token->access_token;
      $minerAuth->refreshToken = $token->refresh_token;
      $this->db->updateRow('uk_characters', [
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
    $ledger = $this->util->requestAndRetry('https://esi.tech.ccp.is/latest/characters/'.$minerAuth->id.'/mining/?token='.$minerAuth->accessToken, null);

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

  public function getMiningRecord(){
    return $this->miningRecord;
  }

  // Set up functions
  private function createTables() {
    // Ensure fleet DB tables exist.
    // Mining fleet table lists fleets and their owners.
    if (!$this->db->tableExists('uk_miningfleet')){
      $this->db->createTable('uk_miningfleet', (object)[
        'fleetId' => (object) [
          'type' => 'BIGINT',
          'size' => 20,
          'attributes' => ['NOT NULL','PRIMARY KEY']
        ],
        'ownerId' => (object) [
          'type' => 'BIGINT',
          'size' => 20,
          'attributes' => ['NOT NULL']
        ],
        'solarSystemId' => (object) [
          'type' => 'INT',
          'size' => 10,
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
    if (!$this->db->tableExists('uk_miningfleet_members')){
      $this->db->createTable('uk_miningfleet_members', (object)[
        'recordId' => (object) [
          'type' => 'INT',
          'size' => 10,
          'attributes' => ['NOT NULL','PRIMARY KEY', 'AUTO_INCREMENT']
        ],
        'fleetId' => (object) [
          'type' => 'BIGINT',
          'size' => 20,
          'attributes' => ['NOT NULL']
        ],
        'minerId' => (object) [
          'type' => 'BIGINT',
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
    if (!$this->db->tableExists('uk_miningfleet_prefleetmining')){
      $this->db->createTable('uk_miningfleet_prefleetmining', (object)[
        'recordId' => (object) [
          'type' => 'INT',
          'size' => 10,
          'attributes' => ['NOT NULL','PRIMARY KEY', 'AUTO_INCREMENT']
        ],
        'fleetId' => (object) [
          'type' => 'BIGINT',
          'size' => 20,
          'attributes' => ['NOT NULL']
        ],
        'minerId' => (object) [
          'type' => 'BIGINT',
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
          'size' => 10,
          'attributes' => ['NOT NULL']
        ],
        'quantity' => (object) [
          'type' => 'INT',
          'size' => 10,
          'attributes' => ['NOT NULL']
        ]
      ]);
    }

    // Mining fleet historical mining lists all mining for each member of closed fleets. 
    if (!$this->db->tableExists('uk_miningfleet_historicmining')){
      $this->db->createTable('uk_miningfleet_historicmining', (object)[
        'recordId' => (object) [
          'type' => 'INT',
          'size' => 20,
          'attributes' => ['NOT NULL','PRIMARY KEY', 'AUTO_INCREMENT']
        ],
        'fleetId' => (object) [
          'type' => 'BIGINT',
          'size' => 20,
          'attributes' => ['NOT NULL']
        ],
        'minerId' => (object) [
          'type' => 'BIGINT',
          'size' => 20,
          'attributes' => ['NOT NULL']
        ],
        'minerName' => (object) [
          'type' => 'VARCHAR',
          'size' => 50,
          'attributes' => ['NOT NULL']
        ],
        'typeId' => (object) [
          'type' => 'INT',
          'size' => 10,
          'attributes' => ['NOT NULL']
        ],
        'typeName' => (object) [
          'type' => 'VARCHAR',
          'size' => 50,
          'attributes' => ['NOT NULL']
        ],
        'quantity' => (object) [
          'type' => 'INT',
          'size' => 10,
          'attributes' => ['NOT NULL']
        ],
        'value' => (object) [
          'type' => 'INT',
          'size' => 10,
          'attributes' => ['NOT NULL']
        ]
      ]);
    }
  }
}