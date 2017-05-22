<?php
header("Content-type:application/json");
error_reporting(0);
require_once("classes/lpexchange.php");
$lpExchange = new LPExchange($_GET["corpid"]);
echo json_encode($lpExchange->get(), JSON_PRETTY_PRINT);