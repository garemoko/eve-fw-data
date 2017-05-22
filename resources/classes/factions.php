<?php

class Factions {

  private $factions;

  public function __construct(){
    $this->buildFactionData();
  }

  private function buildFactionData(){
    $this->factions = [
      (object)[
        'shortname' => 'amarr',
        'name' => 'Amarr Empire',
        'enemy' => 'minmatar',
        'corp' => (object)[
          'id' => 1000179,
          'name' => '24th Imperial Crusade'
        ]
      ],
      (object)[
        'shortname' => 'minmatar',
        'name' => 'Minmatar Republic',
        'enemy' => 'amarr',
        'corp' => (object)[
          'id' => 1000182,
          'name' => 'Tribal Liberation Force'
        ]
      ],
      (object)[
        'shortname' => 'gallente',
        'name' => 'Gallente Federation',
        'enemy' => 'caldari',
        'corp' => (object)[
          'id' => 1000181,
          'name' => 'Federal Defense Union'
        ]
      ],
      (object)[
        'shortname' => 'caldari',
        'name' => 'Caldari State',
        'enemy' => 'gallente',
        'corp' => (object)[
          'id' => 1000180,
          'name' => 'State Protectorate'
        ]
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