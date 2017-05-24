<?php
header("Content-type:application/json");
error_reporting(0);
date_default_timezone_set('UTC');
require_once("classes/factions.php");
$factions = new Factions();
echo json_encode($factions->get(), JSON_PRETTY_PRINT);