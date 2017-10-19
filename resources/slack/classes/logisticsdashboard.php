<?php

require_once(__DIR__ . "/dashboard.php");
require_once(__DIR__ . "/logistics.php");

class LogisticsDashboard extends Dashboard {
  private $logistics;

  public function __construct($slackToken, $slackChannelId){
    $this->type = 'logistics';
    $this->slackToken = $slackToken;
    $this->slackChannelId = $slackChannelId;
    $this->logistics = New Logistics($slackToken, $slackChannelId);
  }

  public function get(){
    $this->logistics->cacheQueues();
    return $this->logistics->get();
  }

  public function getCostPerM3(){
    return $this->logistics->getCostPerM3();
  }

}