<!DOCTYPE html>
<html>
<head>
  <title>KillCheck</title>
  <style type="text/css">
    body {
      background-color: white;
      text-align: center;
      font-family: Arial,sans-serif;
    }
    table, form{
      margin:auto;
      text-align: center;
      width:1000px;
      background-color: #eee;
      color: #000;
      padding: 5px 10px;
    }
    table {
        border-collapse: collapse;
    }
    table, th, td {
        border: 1px solid black;
    }
    th {
      color: #eee;
      background-color: #000;
    }
    a {
       color: #2980b9;
       text-decoration: none;
       font-weight: bold;
    }
    a:hover {
      color: #00aeff;
    }
    
  </style>
</head>
<body>
<h1>KillCheck</h1>

<?php
require_once(__DIR__ . "/../resources/classes/util.php");
require_once(__DIR__ . "/../resources/classes/cache.php");
require_once(__DIR__ . "/../resources/classes/universe.php");

if (
  isset($_GET["alliance"]) 
  && isset($_GET["blues"])
){
  $cfg = (object)[
    'alliance' => $_GET["alliance"],
    'blues' => explode(PHP_EOL, $_GET['blues'])
  ];
  $killCheck = new KillCheck($cfg);
  ?>
    <p><?=strval($killCheck->getLossCount())?> of your last 1000 losses have been to listed entities. </p>
    <p><?=strval($killCheck->getKillCount())?> of your last 1000 kills have been of listed entities. </p>
  <?php
  foreach ($killCheck->getBlues() as $name => $blue) {
    echo ('<h2>'.$name.' ('.$blue->type.')</h2>');
    if (count($blue->killmails)>0){
      ?>
        <table>
          <tr>
            <th>Victim</th>
            <th>Attackers</th>
            <th>Location</th>
            <th>Timestamp</th>
          </tr>
        <?php 
          foreach ($blue->killmails as $killmailIndex => $killmail) {
            ?>
            <tr>
              <td><?=outputPlayer($killmail->victim)?></td>
              <td><?php
                $attackerStr = [];
                foreach ($killmail->attackers as $attackerIndex => $attacker) {
                  array_push($attackerStr, outputPlayer($attacker));
                }
                echo (implode('<br/>', $attackerStr));
              ?></td>
              <td><?=$killmail->system?></td>
              <td><a href="https://zkillboard.com/kill/<?=$killmail->killID?>/" target="_blank">
                <?=$killmail->killTime?>
              </a></td>
            </tr>
            <?php
          }
        ?>
        </table>
      <?php
    }
  }
}
else {
 ?>
  <form action="" method="get" enctype="multipart/form-data">
    <p>Check to see if you've been in fights with would-be-blues (or actual blues) in your last 1000 kills and last 1000 losses.</p>
    <p>Your Alliance: <br/><input type="text" name="alliance"/></p>
    <p>Line separated blues list: <br/><textarea name= "blues"></textarea></p>
    <input type="submit" value="Submit">
  </form>
<?php
}

function outputPlayer($player){
  return $player->player . ' (' . $player->corp . ') ['. $player->alliance .']';
}

class KillCheck {
  public function __construct($cfg){
    $this->killIDs = [];
    $this->lossIDs = [];
    $this->util = new Util();
    $this->universe = new Universe();
    $this->cache = new FileCache('killcheck/'.urlencode($cfg->alliance).'.json');
    $data = $this->cache->get();

    if (isset($data->alliance->name) && $data->alliance->name == $cfg->alliance){
      $this->alliance  = $data->alliance;
    }
    else {
      $this->alliance = $this->getEntityByName($cfg->alliance);
    }

    $this->losses = $this->getKills($this->alliance, 'losses', 5);
    $this->kills = $this->getKills($this->alliance, 'kills', 5);

    $this->blues = (object)[];
    $this->nulls = [];
    foreach ($cfg->blues as $index => $name) {
      $name = trim($name);
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

  public function getKillCount(){
    return count($this->killIDs);
  }

  public function getLossCount(){
    return count($this->lossIDs);
  }

  public function getAlliance(){
    return $this->alliance;
  }

  public function getBlues(){
    return $this->blues;
  }

  private function getKills($entity, $type, $pages){
    $kills = [];
    for ($i=1; $i <= $pages; $i++) { 
      $kills = array_merge($kills, $this->getKillsPage($entity, $type, $i));
    }
    return $kills;
  }

  private function getKillsPage($entity, $type, $page){
    $kills =  $this->util->requestAndRetry(
      'https://zkillboard.com/api/'.$type.'/'. $entity->type .'ID/'.$entity->id
        .'/page/'.strval($page).'/',
      (object)[]
    );
    return $kills;
  }

  private function searchKillMails($entity){
    $killmails = [];

    $prop = $entity->type .'ID';
    foreach ($this->losses as $lossIndex => $kill) {
      foreach ($kill->attackers as $index => $attacker) {
        if ($attacker->$prop == $entity->id) {
          array_push($killmails, $this->shortKill($kill));
          $this->lossIDs[$kill->killID] = true;
          break;
        }
      }
    }
    foreach ($this->kills as $killIndex => $kill) {
      if ($kill->victim->$prop == $entity->id) {
        array_push($killmails, $this->shortKill($kill));
        $this->killIDs[$kill->killID] = true;
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
?>
</body>
</html>
