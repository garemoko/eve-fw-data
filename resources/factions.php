<?php
header("Content-type:application/json");
error_reporting(0);
require_once("classes/factions.php");
$factions = new Factions();
echo json_encode($factions->get(), JSON_PRETTY_PRINT);