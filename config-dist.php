<?php

$CFG = (object)[
  'database' => (object)[
    'host' => 'localhost',
    'port' => '8889',
    'user' => 'root',
    'password' => 'root',
    'database' => 'evewarfare'
  ],
  'sso' => (object)[
    'callback' => 'http://localhost:8888/eve-fw-data/uk/',
    'clientID' => '',
    'secretKey' => '',
    'scope' => 'esi-wallet.read_character_wallet.v1 esi-fleets.read_fleet.v1 esi-contracts.read_character_contracts.v1 esi-industry.read_character_mining.v1'
  ],
  'whitelist' => (object)[
    'characters' => [],
    'corps' => [],
    'alliances' => []
  ]
];