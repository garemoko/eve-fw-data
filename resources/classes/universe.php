<?php
require_once(__DIR__ . "/cache.php");
require_once(__DIR__ . "/util.php");

class Universe {
  private $util;
  private $systems;
  private $constellations;
  private $regions;

  public function __construct(){
    $this->util = new Util();
    $this->systems = new FileCache('universe/systems.json');
    if (is_null($this->systems->get())){
      $this->systems->set((object)[]);
    }
    $this->constellations = new FileCache('universe/constellations.json');
    if (is_null($this->constellations->get())){
      $this->constellations->set((object)[]);
    }
    $this->regions = new FileCache('universe/regions.json');
    if (is_null($this->regions->get())){
      $this->regions->set((object)[]);
    }
  }

  public function getSystemById($id){
    $systems = $this->systems->get();
    if (!isset($systems->$id)){
      $systems->$id = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/universe/systems/'. $id 
          .'/?datasource=tranquility&language=en-us', 
        (object)[]
      );
      $systems->$id->region_id = $this->getConstellationById($systems->$id->constellation_id)->region_id;
      $this->systems->set($systems);
    }
    return $systems->$id;
  }

  public function getConstellationById($id){
    $constellations = $this->constellations->get();
    if (!isset($constellations->$id)){
      $constellations->$id = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/universe/constellations/'. $id 
          .'/?datasource=tranquility&language=en-us', 
        (object)[]
      );
      $this->constellations->set($constellations);
    }
    return $constellations->$id;
  }

  public function getRegionById($id){
    $regions = $this->regions->get();
    if (!isset($regions->$id)){
      $regions->$id = $this->util->requestAndRetry(
        'https://esi.tech.ccp.is/latest/universe/regions/'. $id 
          .'/?datasource=tranquility&language=en-us', 
        (object)[]
      );
      $regions->$id->systems = [];
      foreach ($regions->$id->constellations as $index => $constellationId) {
        $regions->$id->systems = array_merge(
          $regions->$id->systems, 
          $this->getConstellationById($constellationId)->systems
        );
      }
      $this->regions->set($regions);
    }
    return $regions->$id;
  }

}