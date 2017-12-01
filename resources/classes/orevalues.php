<?php

require_once(__DIR__ . "/mineralprices.php");

class OreValues {
  private $mineralPrices;
  private $reprocessingValues;
  private $reprocessrate;
  private $plain_oretax;
  private $moon_oretax;
  private $ice_tax;


  //Reprocess rate for lowsec + t1 rig + max skills + 4% implant is 0.797675

  public function __construct($reprocessrate, $plain_oretax, $moon_oretax, $ice_tax){
    $this->mineralPrices = new MineralPrices();
    $this->reprocessingValues =
      json_decode(file_get_contents(dirname(__DIR__) . "/static/spacerocks.json"),false);
    $this->plain_oretax = $plain_oretax;
    $this->moon_oretax = $moon_oretax;
    $this->ice_tax = $ice_tax;
    $this->reprocessrate = $reprocessrate;
  }

  public function get(){
    $ores = $this->reprocessingValues;
    foreach ($ores as $id => $ore) {
      $ore->value = $this->getOreValuebyID($id, 1);
    }
    return $ores;
  }

  public function getOreValueByName($orename, $oreQuantity) {
    $oreId = $this->getIdByName($orename);
    return $this->getOreValuebyID($oreId, $oreQuantity);
  }

  public function getOreValuebyID($oreId, $oreQuantity) {
    $tax = $this->getTaxByOreId($oreId);

    $oreValue = 0;
    foreach ($this->reprocessingValues->$oreId->yield as $mineralName => $mineralQuantity) {
      $mineralValue = $this->mineralPrices->getJitaMinPriceByName($mineralName, $tax);
      $reprocessedMineralQuantity = floor($mineralQuantity * $this->reprocessrate);
      $oreValue += $reprocessedMineralQuantity * $mineralValue;
    }

    return floor($oreValue * $oreQuantity) / 100;
  }

  private function getTaxByOreId($oreId){
    if ($this->reprocessingValues->$oreId->type === "ore") {
      return $this->plain_oretax; 
    }
    else if ($this->reprocessingValues->$oreId->type === "moon") {
      return $this->moon_oretax; 
    }
    else if ($this->reprocessingValues->$oreId->type === "ice") {
      return $this->ice_tax; 
    }
    else {
      return 1;
    }
  }

  public function set_reprocessrate($newreprocessrate) {
    $this->reprocessrate = $newreprocessrate;
  }

  public function set_oretax($neworetax) {
    $this->plain_oretax = $neworetax;
  }

  public function set_moontax($newmoontax) {
    $this->moon_oretax = $newmoontax;
  }

  public function set_icetax($newicetax) {
    $this->ice_tax = $newicetax;
  }

// method to fetch names from IDs
  public function getNameById($id){
    foreach ($this->reprocessingValues as $IdIndex => $ore) {
      if (strtolower($IdIndex) == strtolower($id)) {
        return $ore->name;
      }
    }
    return null;
  }

// method to convert user input names to IDs
  public function getIdByName($name){
    foreach ($this->reprocessingValues as $id => $ore) {
      if (strtolower($ore->name) == strtolower($name)) {
        return $id;
      }
    }
    return null;
  }
}