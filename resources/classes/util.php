<?php

class Util {
  private $maxRequests = 50;
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
      $http = array(
          'max_redirects' => 0,
          'request_fulluri' => 1,
          'ignore_errors' => true,
          'method' => 'GET',
          'header' => array()
      );

      $context = stream_context_create(array( 'http' => $http ));
      $fp = fopen($url, 'rb', false, $context);
      $metadata = stream_get_meta_data($fp);
      $content  = stream_get_contents($fp);
      $responseCode = (int)explode(' ', $metadata["wrapper_data"][0])[1];
      fclose($fp);

      return array (
          "metadata" => $metadata,
          "content" => $content,
          "status" => $responseCode
      );
  }
}