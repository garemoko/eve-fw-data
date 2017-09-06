<?php

class Factions {

  private $factions;

  public function __construct(){
    $this->buildFactionData();
  }

  private function buildFactionData(){
    $this->factions = [
      (object)[
        'id' => 500003,
        'shortname' => 'amarr',
        'name' => 'Amarr Empire',
        'enemy' => 'minmatar',
        'corp' => (object)[
          'id' => 1000179,
          'name' => '24th Imperial Crusade'
        ],
        'color' => '#cc9900'
      ],
      (object)[
        'id' => 500002,
        'shortname' => 'minmatar',
        'name' => 'Minmatar Republic',
        'enemy' => 'amarr',
        'corp' => (object)[
          'id' => 1000182,
          'name' => 'Tribal Liberation Force'
        ],
        'color' => '#e60000'
      ],
      (object)[
        'id' => 500004,
        'shortname' => 'gallente',
        'name' => 'Gallente Federation',
        'enemy' => 'caldari',
        'corp' => (object)[
          'id' => 1000181,
          'name' => 'Federal Defense Union'
        ],
        'color' => '#248f24'
      ],
      (object)[
        'id' => 500001,
        'shortname' => 'caldari',
        'name' => 'Caldari State',
        'enemy' => 'gallente',
        'corp' => (object)[
          'id' => 1000180,
          'name' => 'State Protectorate'
        ],
        'color' => '#008fb3'
      ],
    ];
  }

  public function get($filter = null){
    if (is_null($filter)){$filter = (object)[];}

    foreach ($filter as $key => $value) {
      foreach ($this->factions as $faction) {
        if ($value == $faction->$key) {
          return [$faction];
        }
      }
    }
    return $this->factions;
  }

}