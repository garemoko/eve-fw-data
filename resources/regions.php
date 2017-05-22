<?php
header("Content-type:application/json");
//error_reporting(0);
require_once("classes/regions.php");
$regions = new Regions();
echo json_encode($regions->get(), JSON_PRETTY_PRINT);