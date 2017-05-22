<?php
header("Content-type:application/json");
//error_reporting(0);
require_once("classes/constellations.php");
$constellations = new Constellations();
echo json_encode($constellations->get(), JSON_PRETTY_PRINT);