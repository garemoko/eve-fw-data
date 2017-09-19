<?php
header("Content-type:text/html");
error_reporting(0);
date_default_timezone_set('UTC');
require_once( __DIR__ . "/resources/slack/classes/dashboardregistry.php");
require_once( __DIR__ . "/resources/slack/classes/dashboard.php");

if (
  !isset($_GET["id"])
) {
  http_response_code(400);
  echo('No dashboard id provided');
  die();
}

$dashboardregistry = new DashboardRegistry();
$dashboardMetaData = $dashboardregistry->getById($_GET["id"]);

if (is_null($dashboardMetaData) || new DateTime($dashboardMetaData->expires) < new DateTime()) {
  // Tidy up expired links.
  $dashboardRegistry->removeExpired();

  http_response_code(404);
  echo('Dashboard not found or expired.');
  die();
}

$dashboard = new Dashboard($dashboardMetaData->slackToken, $dashboardMetaData->slackChannelId);
$data = $dashboard->get();

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
    <title>Market Dashboard</title>
    <link href="main.css" rel="stylesheet" />
</head>
<body>
  <div class="title">
    <h1>Market Dashboard</h1>
    <p>By <a href="https://gate.eveonline.com/Profile/DrButterfly%20PHD" target="_blank">DrButterfly PHD</a></p>
  </div>
  <div class="links">
    <b>Other Useful sites: </b>
    <a href="http://evewarfare.com/" target="_blank">EVE Warfare</a>
    | <a href="https://forums.eveonline.com/t/t-r-i-a-d-recruiting-minmatar-factionwarfare-ushra-khan/25609" target="_blank">Ushra'Khan Recruitment</a> 
    | <a href="http://evemaps.dotlan.net/" target="_blank">dotlan</a> 
    | <a href="https://eve-central.com/" target="_blank">EVE Central</a>
  </div><p class="times">&nbsp;</p>
  <div class="content">
  <h2>Stations</h2>
  <?php
    foreach ($data->stations as $stationIndex => $station) {
      ?><div class="station-div"><h3><?=$station->name?></h3>
      <dl class="collections-list heading-note">
        <dt>Collections required at this station:</dt>
        <dd><?php
        foreach ($station->orders as $index => $collection) {
          echo '<a href="#' . $collection . '">' . $collection . '</a>, ';
        }
      ?></dd></dl>
      <table>
        <tr>
          <th>Item</th>
          <th>Max Price</th>
          <th>Quantity Required</th>
          <th>Quantity Available</th>
        </tr>
        <?php
        foreach ($station->market as $itemIndex => $item) {
          $percentRemain = $item->percent_remain > 100 ? 100 : $item->percent_remain;
          $color = (object)[
            'red' => 0,
            'green' => 0,
            'blue' => 0
          ];
          if ($percentRemain > 50){
            foreach ($color as $key => $value) {
              $color->$key = ($colors->green->$key * ($percentRemain - 50) / 50) 
                + ($colors->yellow->$key * (100 - $percentRemain) / 50);
            }
          } else {
            foreach ($color as $key => $value) {
              $color->$key = ($colors->yellow->$key * ($percentRemain) / 50) 
                + ($colors->red->$key * (50 - $percentRemain) / 50);
            }
          }
          $colorStr = 'rgb('.$color->red.','.$color->green.','.$color->blue.')';
          ?>
          <tr>
            <td><?=$item->name?></td>
            <td><?=number_format($item->price)?> ISK</td>
            <td><?=$item->quantity?></td>
            <td style="background-color:<?=$colorStr?>;"><?=$item->volume_remain?></td>
          </tr>
          <?php
      }
      ?></table></div><?php
    }
  ?>
  <h2>Collections</h2>
  <?php
    foreach ($data->collections as $collectionIndex => $collection) {
      ?><div class="collection-div"><a name="<?=$collection->name?>"></a><h3><?=$collection->name?></h3>
      <table>
        <tr>
          <th>Item</th>
          <th>Max Price</th>
          <th>Quantity Required</th>
        </tr>
        <?php
        foreach ($collection->items as $itemIndex => $item) {
          ?>
          <tr>
            <td><?=$item->name?></td>
            <td><?=number_format($item->price)?> ISK</td>
            <td><?=$item->quantity?></td>
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