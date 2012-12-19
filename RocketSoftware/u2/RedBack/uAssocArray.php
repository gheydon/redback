<?php

namespace RocketSoftware\u2\RedBack;

use RocketSoftware\u2\RedBack\uAssocArrayItem;

class uAssocArray implements \ArrayAccess, \Countable, \Iterator {
  private $uObject = NULL;
  private $fields = array();
  private $iterator_position = 0;
  
  public function __construct($uObject, $fields) {
    $this->uObject = $uObject;
    $this->fields = $fields;
    
    foreach ($this->fields as $field) {
      if (!$this->uObject->checkAccess($field)) {
        throw new \Exception("{$field} is not a valid field");
      }
    }
  }
  
  public function get($delta) {
    return new uAssocArrayItem($this->uObject, $this->fields, $delta);
  }
  
  public function set($value) {
    throw new \Exception('__METHOD__ not implemented');
  }

  public function getArrayCopy() {
    $array = array();

    for ($i = 1; $i <= $this->count(); $i++) {
      $array[$i] = $this->get($i);
    }

    return $array;
  }

  public function offsetExists($delta) {
    foreach ($this->fields as $fields) {
      $value = $this->uObject->get($field);
      if (!empty($value[$delta])) {
        return TRUE;
      }
    }
    return FALSE;
  }
  
  public function offsetGet($delta) {
    return $this->get($delta);
  }
  
  public function offsetSet($delta, $value) {
    //$this->get($delta)->set($value);
  }
  
  public function offsetUnset($delta) {
    foreach ($this->fields as $fields) {
      $value = $this->uObject->get($field);
      unset($value[$delta]);
    }
  }
  
  public function count() {
    $max = 0;
    
    foreach ($this->fields as $field) {
      $count = count($this->uObject->get($field));
      
      $max = $count > $max ? $count : $max;
    }
    
    return $max;
  }
  
  public function current() {
    return $this->get($this->iterator_position);
  }
  
  public function key() {
    return $this->iterator_position;
  }
  
  public function next() {
    $this->iterator_position++;
  }

  public function rewind() {
    $this->iterator_position = 1;
  }

  public function valid() {
    return $this->iterator_position <= $this->count();
  }
}