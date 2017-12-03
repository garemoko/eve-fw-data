<?php

require_once(__DIR__ . "/database.php");
require_once(__DIR__ . "/character.php");

class MiningHistory {
  private $db;
  private $util;

  public function __construct(){
    $this->db = new Database();
    $this->createTables();
  }

  public function getFleets(){
    $fleets = $this->db->getRow('uk_miningfleet', [
      'active' => 0
    ]);
    $fleets = $this->addCharacterName($fleets);
    return $fleets;
  }

  public function getFleet($fleetId){
    $fleets = $this->db->getRow('uk_miningfleet', [
      'fleetId' => $fleetId
    ]);
    $fleets = $this->addCharacterName($fleets);
    return $fleets[0];
  }

  private function addCharacterName($fleets){
    foreach ($fleets as $index => $fleet) {
      $character = new Character($fleet->ownerId);
      $fleet->owner = $character->get();
    }
    return $fleets;
  }

  public function getLedger($fleetId){
    return $this->db->getRow('uk_miningfleet_historicmining', [
      'fleetId' => $fleetId
    ]);
  }

  // Set up functions
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