<?php

class FileCache {
  private $dir;
  private $fileName = '';
  private $data;

  public function __construct($fileName){
    $this->dir  = dirname(__DIR__) . '/files/';
    $this->fileName = $fileName;
    $this->data = $this->getFromFile();
  }

  private function getFromFile(){
    if(file_exists($this->dir . $this->fileName)){
      return json_decode(file_get_contents($this->dir . $this->fileName));
    }
    return null;
  }

  public function get(){
    return $this->data;
  }

  public function set($contents){
    $this->data = $contents;
    file_put_contents($this->dir . $this->fileName, json_encode($contents, JSON_PRETTY_PRINT));
  }

  public function delete(){
    $this->data = null;
    unlink($this->dir . $this->fileName);
  }
}