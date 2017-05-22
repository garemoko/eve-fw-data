<?php
header("Content-type:application/json");
//error_reporting(0);
require_once("classes/dashboarddata.php");
$dashboarddata = new DashboardData();
echo json_encode($dashboarddata->get(), JSON_PRETTY_PRINT);