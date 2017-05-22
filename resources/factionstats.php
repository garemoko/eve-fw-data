<?php
header("Content-type:application/json");
//error_reporting(0);
require_once("classes/factionstats.php");
$factionstats = new FactionStats();
echo json_encode($factionstats->get(), JSON_PRETTY_PRINT);