<?php

require_once(__DIR__ . "/dashboard.php");
require_once(__DIR__ . "/logistics.php");

class LogisticsDashboard extends Dashboard {

  public function __construct($slackToken, $slackChannelId){
    $this->type = 'logistics';
    $this->slackToken = $slackToken;
    $this->slackChannelId = $slackChannelId;
    $this->cache = new FileCache('logistics/'.$slackToken.'/'.$slackChannelId.'/logistics-orders.json');
  }

}