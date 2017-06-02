<?php

class FileCache {
  private $dir;
  private $fileName = '';
  private $data;

  public function __construct($fileName){
    $pathArr = explode('/', dirname(__DIR__) . '/files/' . $fileName);
    $this->fileName = array_pop($pathArr);
    $this->dir  = implode('/', $pathArr).'/';
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

    if(!file_exists($this->dir)){
      mkdir($this->dir, 0777, true);
    }
    file_put_contents($this->dir . $this->fileName, json_encode($contents, JSON_PRETTY_PRINT));
  }

  public function delete(){
    $this->data = null;
    unlink($this->dir . $this->fileName);
  }
}