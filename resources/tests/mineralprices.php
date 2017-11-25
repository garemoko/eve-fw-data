<?php
require_once("../classes/mineralprices.php");

echo '<pre>';

echo PHP_EOL.'=Construct and get=========================================='.PHP_EOL.PHP_EOL;
$mineralPrices = New MineralPrices ();
var_dump($mineralPrices->get());


echo PHP_EOL.'=Get trit lowest price by id (34)=========================================='.PHP_EOL.PHP_EOL;
var_dump($mineralPrices->getJitaMinPriceById('34', 0));

echo PHP_EOL.'=Get trit trade price by id (34)=========================================='.PHP_EOL.PHP_EOL;
var_dump($mineralPrices->getExportPriceById('34', 500, 0));

echo PHP_EOL.'=Get trit lowest price by name=========================================='.PHP_EOL.PHP_EOL;
var_dump($mineralPrices->getJitaMinPriceByName('Tritanium', 0));

echo PHP_EOL.'=Get trit trade price by name=========================================='.PHP_EOL.PHP_EOL;
var_dump($mineralPrices->getExportPriceByName('Tritanium', 0, 500));

echo PHP_EOL.'=Get trit lowest price by name 10% tax=========================================='.PHP_EOL.PHP_EOL;
var_dump($mineralPrices->getJitaMinPriceByName('Tritanium', 0.1));

echo PHP_EOL.'=Get trit trade price by name 10% tax=========================================='.PHP_EOL.PHP_EOL;
var_dump($mineralPrices->getExportPriceByName('Tritanium', 0.1, 500));