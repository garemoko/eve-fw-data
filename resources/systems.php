<?php
header("Content-type:application/json");
error_reporting(0);
date_default_timezone_set('UTC');
require_once("classes/systems.php");
$systems = new Systems();
echo json_encode($systems->get(), JSON_PRETTY_PRINT);