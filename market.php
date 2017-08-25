<?php
header("Content-type:text/html");
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
    | <a href="https://forums.eveonline.com/t/t-r-i-a-d-recruiting-minmatar-factionwarfare-ushrakhan/19519" target="_blank">Ushra'Khan Recruitment</a> 
    | <a href="http://evemaps.dotlan.net/" target="_blank">dotlan</a> 
    | <a href="https://eve-central.com/" target="_blank">EVE Central</a>
  </div><p class="times">&nbsp;</p>
  <div class="content">
  <?php
    foreach ($data->stations as $stationIndex => $station) {
      ?><div class="station-div"><h2><?=$station->name?></h2>
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
            <td><?=$item->price?> ISK</td>
            <td><?=$item->quantity?></td>
            <td style="background-color:<?=$colorStr?>;"><?=$item->volume_remain?></td>
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