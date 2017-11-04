<?php
header("Content-type:text/html");
error_reporting(0);
date_default_timezone_set('UTC');
require_once( __DIR__ . "/resources/slack/classes/dashboardregistry.php");
require_once( __DIR__ . "/resources/slack/classes/logisticsdashboard.php");

if (
  !isset($_GET["id"])
) {
  http_response_code(400);
  echo('No dashboard id provided');
  die();
}

$dashboardRegistry = new DashboardRegistry('logistics');
$dashboardMetaData = $dashboardRegistry->getById($_GET["id"]);

if (is_null($dashboardMetaData) || new DateTime($dashboardMetaData->expires) < new DateTime()) {
  // Tidy up expired links.
  $dashboardRegistry->removeExpired();

  http_response_code(404);
  echo('Dashboard not found or expired.');
  die();
}

$dashboard = new LogisticsDashboard($dashboardMetaData->slackToken, $dashboardMetaData->slackChannelId);
$data = $dashboard->get();

$costPerM3 = $dashboard->getCostPerM3();

$colors = (object)[
  'red' => (object)[
    'red' => 230,
    'green' => 0,
    'blue' => 0
  ],
  'yellow' => (object)[
    'red' => 204,
    'green' => 153,
    'blue' => 0
  ],
  'green' => (object)[
    'red' => 36,
    'green' => 143,
    'blue' => 36
  ]
];

?>
<!DOCTYPE html>
<html>
<head>
    <title>Move My Stuff</title>
    <link href="main.css" rel="stylesheet" />
</head>
<body>
  <div class="title">
    <h1>Move My Stuff</h1>
    <img src="c/ushrakhan.png" class="ushrakhan" alt="Ushra'Khan" />
    <img src="c/logo-long.png" class="ushrakhan" alt="Corp Awesome" />
  </div>
  <div class="links">Check Slack for more information.</div>
  <div class="content">
    <?php
      foreach ($data->queues as $qIndex => $q) {
        ?><div class="station-div"><h3>Queue <?=$qIndex?></h3>
        <table>
          <tr>
            <th>Id</th>
            <th>Owner</th>
            <th>m3</th>
            <th>ISK</th>
          </tr>
          <?php
          foreach ($q->orders as $oIndex => $o) {
            $expectedPrice = $o->size * $costPerM3;
            $pricePercent = $o->cost / $expectedPrice * 100; 
            $color = (object)[
              'red' => 0,
              'green' => 0,
              'blue' => 0
            ];
            if ($pricePercent > 100){
              foreach ($color as $key => $value) {
                $color->$key = ($colors->green->$key * ($pricePercent - 100) / 100) 
                  + ($colors->yellow->$key * (200 - $pricePercent) / 100);
                  $color->$key = round($color->$key);
              }
            } else {
              foreach ($color as $key => $value) {
                $color->$key = ($colors->yellow->$key * ($pricePercent) / 100) 
                  + ($colors->red->$key * (100 - $pricePercent) / 100);
                $color->$key = round($color->$key);
              }
            }
            $colorStr = 'rgb('.$color->red.','.$color->green.','.$color->blue.')';
            ?>
            <tr>
              <td><?=$o->id?></td>
              <td><?=$o->owner?></td>
              <td><?=number_format($o->size)?> m3</td>
              <td style="background-color:<?=$colorStr?>;"><?=number_format($o->cost)?> ISK</td>
            </tr>
            <?php
        }
        ?></table></div><?php
      }
    ?>
  </div>
</body>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script type="text/javascript">
var data = <?php
  echo json_encode($data, JSON_PRETTY_PRINT);
?>;
console.log(data);
</script>
</html>