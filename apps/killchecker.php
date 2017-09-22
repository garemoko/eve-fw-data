<?php
require_once(__DIR__ . "/../resources/classes/util.php");
require_once(__DIR__ . "/../resources/classes/cache.php");
require_once(__DIR__ . "/../resources/classes/universe.php");

$cfg = (object)[
  'alliance' => "Ushra'Khan",
  'blues' => []
];


$file = fopen("killcheck/blues.list", "r");
if ($file) {
    while (($line = fgets($file)) !== false) {
        array_push($cfg->blues, trim($line));
    }
    fclose($file);
} else {
    // error opening the file.
}

$killCheck = new KillCheck($cfg);

echo ('<pre>');
var_dump($killCheck->getBlues());

class KillCheck {
  public function __construct($cfg){
    $this->util = new Util();
    $this->universe = new Universe();
    $this->cache = new FileCache('killcheck/cache.json');
    $data = $this->cache->get();

    if (isset($data->alliance->name) && $data->alliance->name == $cfg->alliance){
      $this->alliance  = $data->alliance;
    }
    else {
      $this->alliance = $this->getEntityByName($cfg->alliance);
    }

    $this->losses = $this->getKills($this->alliance, 'losses');
    $this->kills = $this->getKills($this->alliance, 'kills');

    $this->blues = (object)[];
    $this->nulls = [];
    foreach ($cfg->blues as $index => $name) {
      if (isset($data->blues->$name->id)){
        $this->blues->$name = $data->blues->$name;
      }
      else {
        $entity = $this->getEntityByName($name);
        if (is_null($entity)){
          array_push($this->nulls, $name);
          continue;
        }
        else {
          $this->blues->$name = $entity;
        }
      }
      $this->blues->$name->killmails = [];
      $this->blues->$name->killmails = $this->searchKillMails($this->blues->$name);
    };

    $this->cache->set((object)[
      'alliance' => $this->alliance,
      'blues' => $this->blues,
      'nulls' => $this->nulls
    ]);
  }

  public function getAlliance(){
    return $this->alliance;
  }

  public function getBlues(){
    return $this->blues;
  }

  private function getKills($entity, $type){
    return $this->util->requestAndRetry(
      'https://zkillboard.com/api/'.$type.'/'. $entity->type .'ID/'.$entity->id.'/',
      (object)[]
    );
  }

  private function searchKillMails($entity){
    $killmails = [];

    $prop = $entity->type .'ID';
    foreach ($this->losses as $lossIndex => $kill) {
      foreach ($kill->attackers as $index => $attacker) {
        if ($attacker->$prop == $entity->id) {
          array_push($killmails, $this->shortKill($kill));
        }
      }
    }
    foreach ($this->kills as $killIndex => $kill) {
      if ($kill->victim->$prop == $entity->id) {
        array_push($killmails, $this->shortKill($kill));
      }
    }
    return $killmails;
  }

  private function shortKill($kill){

     $shortkill = (object)[
      'killID' => $kill->killID,
      'killTime' => $kill->killTime,
      'system' => $this->universe->getSystemById($kill->solarSystemID)->name,
      'value' => $kill->zkb->totalValue,
      'victim' => (object)[
        'player' => $kill->victim->characterName,
        'corp' => $kill->victim->corporationName,
        'alliance' => $kill->victim->allianceName,
      ],
      'attackers' => []
    ];

    foreach ($kill->attackers as $index => $attacker) {
      array_push($shortkill->attackers, (object)[
        'player' => $attacker->characterName,
        'corp' => $attacker->corporationName,
        'alliance' => $attacker->allianceName,
      ]);
    }

    return $shortkill;
  }

  private function getEntityByName($name){
    $id = null;
    $response = $this->util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/search/?categories=alliance&datasource=tranquility&language=en-us&strict=false&search=' . urlencode($name),
      (object)[]
    );
    if (isset($response->alliance)){
      return (object)[
        'id' => $response->alliance[0],
        'type' => 'alliance'
      ];
    }

    $response = $this->util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/search/?categories=corporation&datasource=tranquility&language=en-us&strict=false&search=' . urlencode($name),
      (object)[]
    );
    if (isset($response->corporation)){
      return (object)[
        'id' => $response->corporation[0],
        'type' => 'corporation'
      ];
    }

    $response = $this->util->requestAndRetry(
      'https://esi.tech.ccp.is/latest/search/?categories=character&datasource=tranquility&language=en-us&strict=false&search=' . urlencode($name),
      (object)[]
    );
    if (isset($response->character)){
      return (object)[
        'id' => $response->character[0],
        'type' => 'character'
      ];
    }

    return null;
  }

}
