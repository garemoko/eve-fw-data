<?php
require_once("../classes/orevalues.php");

echo '<pre>';

echo PHP_EOL.'=Construct and get=========================================='.PHP_EOL.PHP_EOL;
$oreValues = New OreValues (0.797675, 0.2, 0.25, 0.2);
var_dump($oreValues->get());


echo PHP_EOL.'=Get ark lowest price by id (22)=========================================='.PHP_EOL.PHP_EOL;
var_dump($oreValues->getOreValuebyID('22', 100));
