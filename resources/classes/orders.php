<?php

require_once(__DIR__ . "/systems.php");
require_once(__DIR__ . "/factions.php");

class Orders {

  private $orders;
  public function __construct(){
    $this->buildOrders();
  }

  private function buildOrders(){

    $factions = new Factions();
    $factionResponse = $factions->get();
    $orders = (object)[];

    foreach ($factions->get() as $index => $faction) {
      $factionName = $faction->shortname;
      $orders->$factionName = (object)[
        "attack" => [],
        "defend" => []
      ];
    }

    $systems = new Systems();
    foreach ($systems->get()->systems as $index => $system) {
      if ($system->solarSystemName == 'Arzad'){
        $system->solarSystemName = 'Starkman';
      }
      $contestedPercent = round(($system->victoryPoints / $system->victoryPointThreshold * 100), 2);

      $faction = $factions->get((object)[
        'name' => $system->occupyingFactionName
      ])[0];
      $defendingFactionName = $faction->shortname;
      $attackingFactionName = $faction->enemy;

      if (count($orders->{$defendingFactionName}->defend) < 2){
        array_push($orders->{$defendingFactionName}->defend, (object)[
          "solarSystemName" => $system->solarSystemName,
          "contestedPercent" => $contestedPercent
        ]);
      }

      if (count($orders->{$attackingFactionName}->attack) < 2){
        array_push($orders->{$attackingFactionName}->attack, (object)[
          "solarSystemName" => $system->solarSystemName,
          "contestedPercent" => $contestedPercent
        ]);
      }
    }

    $this->orders = $orders;
  }

  public function get(){
      return $this->orders;
  }

}