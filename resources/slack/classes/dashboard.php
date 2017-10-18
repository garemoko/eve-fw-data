<?php

require_once( __DIR__ . "/../../classes/cache.php");
require_once(__DIR__ . "/dashboardregistry.php");

class Dashboard {
  protected $util;
  protected $cache;
  protected $slackToken;
  protected $slackChannelId;
  protected $type;

  public function __construct($slackToken, $slackChannelId){
    $this->type = 'dashboard';
    $this->slackToken = $slackToken;
    $this->slackChannelId = $slackChannelId;
    $this->cache = new FileCache('dashboard/'.$slackToken.'/'.$slackChannelId.'/dashboard.json');
  }

  public function getURL($expiry = "+30 days"){
    if (trim($expiry) === ""){
      $expiry = "+30 days";
    }
    $dashboardRegistry = new DashboardRegistry($this->type);
    // Pre-cache the dashboard
    $this->get();
    return $dashboardRegistry->add($this->slackToken, $this->slackChannelId, $expiry);
  }

  public function expireAll(){
    $dashboardRegistry = new DashboardRegistry($this->type);
    return $dashboardRegistry->expireAll($this->slackToken, $this->slackChannelId);
  }

  public function get(){
    return $this->cache->get();
  }

  protected function jsonDirectoryToArray($path){
    $array = [];
    if (file_exists($path)){
      $files = array_diff(scandir($path), ['.', '..']);
      foreach ($files as $index => $file) {
        $fileArr = explode('.', $file);
        if (end($fileArr) === 'json') {
          $data = json_decode(file_get_contents($path . $file));
          array_push($array, $data);
        }
      }
    }
    return $array;
  }

}