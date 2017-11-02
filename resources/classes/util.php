<?php

class Util {
  private $maxRequests = 5;
  public function requestAndRetry($url, $default, $type = 'json'){
    $success = false;
    $attempts = 0;
    $response;
    while ($success === false && $attempts < $this->maxRequests) {
      $response = $this->getPublicResource($url);
      if ($response['status'] == 200){
        $success = true;
        if ($type == 'json'){
          $response['content'] = json_decode($response['content']);
        }
        else if ($type == 'xml'){
          $response['content'] = simplexml_load_string($response['content']);
        }
        return $response['content'];
      } 
      else {
        $attempts++;
      }
    }
    return $default;
  }

  private function getPublicResource($url) {
      $ch = curl_init(); 
      curl_setopt($ch, CURLOPT_URL, $url); 
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
      $output = curl_exec($ch); 
      $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      return array (
          "content" => $output,
          "status" => $responseCode
      );
  }

  public function setMaxRetries($max){
    $this->maxRequests = $max;
  }
}