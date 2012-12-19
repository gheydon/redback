<?php

namespace RocketSoftware\u2\RedBack;

use RocketSoftware\u2\RedBack\uAssocArrayItem;

class uAssocArray implements \ArrayAccess, \Countable, \Iterator {
  private $uObject = NULL;
  private $fields = array();
  private $key_field = NULL;
  private $iterator_position = 0;
  
  public function __construct($uObject, $fields, $key_field = NULL) {
    $this->uObject = $uObject;
    $this->fields = $fields;
    $this->key_field = $key_field;
    
    foreach ($this->fields as $field) {
      if (!$this->uObject->checkAccess($field)) {
        throw new \Exception("{$field} is not a valid field");
      }
    }
    
    if ($key_field) {
      if (!$this->uObject->checkAccess($key_field)) {
        throw new \Exception("{$field} is not a valid field");
      }
    }
  }
  
  public function get($delta) {
    if (isset($this->key_field)) {
      $keys = $this->uObject->get($this->key_field);
      
      foreach ($keys as $pos => $value) {
        if ((string)$value == $delta) {
          return new uAssocArrayItem($this->uObject, $this->fields, $pos);
        }
      }
      return new uAssocArrayItem($this->uObject, $this->fields, $this->count()+1);
    }
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
    return $this->get($this->key());
  }
  
  public function key() {
    if (isset($this->key_field)) {
      $keys = $this->uObject->get($this->key_field);
      
      return (string)$keys[$this->iterator_position];
    }
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