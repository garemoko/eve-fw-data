<?php
require_once("../classes/database.php");

echo '<pre>';

echo PHP_EOL.'=Construct and create table=========================================='.PHP_EOL.PHP_EOL;
$database = New Database ();
echo 'table exists already? ';
var_dump($database->tableExists('testtable'));

$rows = (object)[
  'id' => (object) [
    'type' => 'INT',
    'size' => 6,
    'attributes' => ['UNSIGNED','AUTO_INCREMENT','PRIMARY KEY']
  ],
  'somestring' => (object) [
    'type' => 'VARCHAR',
    'size' => 30,
    'attributes' => ['NOT NULL']
  ]
];

$database->createTable('testtable', $rows);
echo 'table exists now? ';
var_dump($database->tableExists('testtable'));

echo PHP_EOL.'=add and get row=========================================='.PHP_EOL.PHP_EOL;
$somestring = '; DROP *;';
$database->addRow('testtable', [
  'somestring' => $somestring
]);

$row = $database->getRow('testtable', [
  'somestring' => $somestring
]);

var_dump($row[0]->somestring);

echo PHP_EOL.'=update and get row=========================================='.PHP_EOL.PHP_EOL;
$newstring = 'it works';
$database->updateRow('testtable', [
    'id' => $row[0]->id
  ] , [
    'somestring' => $newstring
]);

$row = $database->getRow('testtable', [
  'somestring' => $newstring
]);

var_dump($row[0]->somestring);

echo PHP_EOL.'=delete and get row=========================================='.PHP_EOL.PHP_EOL;
var_dump($database->deleteRow('testtable', [
  'id' => $row[0]->id
]));

$row = $database->getRow('testtable', []);

var_dump($row);

echo PHP_EOL.'=drop table=========================================='.PHP_EOL.PHP_EOL;
$database->deleteTable('testtable', $rows);
echo 'table exists after delete? ';
var_dump($database->tableExists('testtable'));


