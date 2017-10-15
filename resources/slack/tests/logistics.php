<?php
require_once("../classes/logistics.php");

echo '<pre>';

echo PHP_EOL.'=Construct and get=========================================='.PHP_EOL.PHP_EOL;
$logistics = New Logistics ('test-token', 'test-channel-id');
var_dump($logistics->get());

echo PHP_EOL.'=addOrder=========================================='.PHP_EOL.PHP_EOL;
var_dump($logistics->addOrder('100000', 'DrButterfly PHD'));

echo PHP_EOL.'=addOrder small order=========================================='.PHP_EOL.PHP_EOL;
var_dump($logistics->addOrder('1', 'Pial Te'));

echo PHP_EOL.'=addOrder oversize order=========================================='.PHP_EOL.PHP_EOL;
var_dump($logistics->addOrder('320000.01', 'Pial Te'));

echo PHP_EOL.'=addOrder order with string formatting=========================================='.PHP_EOL.PHP_EOL;
var_dump($logistics->addOrder('300,000m3', 'Pial Te'));

echo PHP_EOL.'=getOrders=========================================='.PHP_EOL.PHP_EOL;
var_dump($logistics->getOrders());

echo PHP_EOL.'=removeOrder=========================================='.PHP_EOL.PHP_EOL;
$id = $logistics->addOrder(50000, 'Minnaroth');
var_dump($logistics->getOrders());
var_dump($logistics->removeOrder($id));
var_dump($logistics->getOrders());

echo PHP_EOL.'=removeAllOrders=========================================='.PHP_EOL.PHP_EOL;
$logistics->addOrder(50000, 'Minnaroth');
var_dump($logistics->getOrders());
var_dump($logistics->removeAllOrders($id));
var_dump($logistics->getOrders());

echo PHP_EOL.'=set up for getQueues=========================================='.PHP_EOL.PHP_EOL;
$logistics->addOrder(100000, 'DrButterfly PHD');
$logistics->addOrder(50000, 'Minnaroth');
$logistics->addOrder(1, 'Pial Te');
$logistics->addOrder(50000, 'toon 2');
$logistics->addOrder(100000, 'toon 3');
$logistics->addOrder(50000, 'toon 4');
$logistics->addOrder(100000, 'toon 5');
$logistics->addOrder(50000, 'toon 6');
$logistics->addOrder(100000, 'toon 7');
$logistics->addOrder(50000, 'toon 8');
$logistics->addOrder(35485, 'toon 9');
$logistics->addOrder(123548, 'toon 10');
var_dump($logistics->getOrders());

echo PHP_EOL.'=getQueues=========================================='.PHP_EOL.PHP_EOL;
var_dump($logistics->getQueues());

echo PHP_EOL.'=removeQueue=========================================='.PHP_EOL.PHP_EOL;
var_dump($logistics->acceptQueue(1));

echo PHP_EOL.'=End of tests=========================================='.PHP_EOL.PHP_EOL;
$logistics->delete();