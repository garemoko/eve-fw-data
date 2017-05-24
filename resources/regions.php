<?php
header("Content-type:application/json");
error_reporting(0);
date_default_timezone_set('UTC');
require_once("classes/regions.php");
$regions = new Regions();
echo json_encode($regions->get(), JSON_PRETTY_PRINT);