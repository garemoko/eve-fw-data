<?php
header("Content-type:application/json");
//error_reporting(0);
date_default_timezone_set('UTC');
require_once("classes/orders.php");
$orders = new Orders();
echo json_encode($orders->get(), JSON_PRETTY_PRINT);