<?php

require_once( __DIR__ . "/../../config.php");

class Database {
  public $db;

  public function __construct(){
    $this->open();
  }

  public function open(){
    global $CFG;
    $this->db = new mysqli($CFG->database->host, $CFG->database->user, $CFG->database->password, $CFG->database->database, $CFG->database->port);
  }

  public function close(){
    $this->db->close();
  }

  public function addRow($table, $data){
    $sql = 'INSERT INTO ' . $table . ' (';
    $sql .= implode(', ', array_keys($data));
    $sql .= ')';

    $sql .= PHP_EOL . "VALUES ('";
    $sql .= implode("', '", array_values($data));
    $sql .= "')";

    return $this->db->query($sql);
  }

  public function getRow($table, $queries){
    $sql = 'SELECT * FROM ' . $table;
    $sql .= $this->clause($queries, ' WHERE');
    $result = $this->db->query($sql);

    $rows = [];
    if ($result !== false && mysqli_num_rows($result) > 0) {
      while($row = mysqli_fetch_assoc($result)) {
        array_push($rows, (object) $row);
      }
    }
    return $rows;
  }

  public function updateRow($table, $queries, $data){
    $sql = 'UPDATE ' . $table . ' ';
    $sql .= $this->clause($data, ' SET');
    $sql .= $this->clause($queries, ' WHERE');
    $result = $this->db->query($sql);

    return $result;
  }

  public function deleteRow($table, $queries){
    $sql = 'DELETE FROM ' . $table;
    $sql .= $this->clause($queries, ' WHERE');
    $result = $this->db->query($sql);
    return $result;
  }

  protected function clause($queries, $type){
    $sql = $type . ' ';
    $whereArr = [];
    foreach ($queries as $key => $value) {
      array_push($whereArr, $this->db->real_escape_string($key)."='".$this->db->real_escape_string($value)."'");
    }
    $sql .= implode(' AND ', $whereArr);
    return $sql;
  }


  public function createTable($table, $columns){
    $sql = 'CREATE TABLE ' . $table . ' ('.PHP_EOL;
    $colsArr = [];
    foreach ($columns as $column => $properties) {
      array_push(
        $colsArr, 
        $column . ' ' . $properties->type . '('. $properties->size . ') ' . implode(
          ' ', $properties->attributes
        )
      );
    }
    $sql .= implode(','.PHP_EOL, $colsArr);
    $sql .= PHP_EOL.')';

    return $this->db->query($sql);
  }

  public function deleteTable($table){
    return $this->db->query('DROP TABLE '.$table.';');
  }

  public function tableExists($table){
    if ($result = $this->db->query("SHOW TABLES LIKE '".$table."'")) {
      if($result->num_rows == 1) {
        return true;
      }
    }
    return false;
  }

}