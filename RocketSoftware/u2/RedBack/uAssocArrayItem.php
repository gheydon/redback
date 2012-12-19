<?php

namespace RocketSoftware\u2\RedBack;

class uAssocArrayItem implements \ArrayAccess, \Iterator {
  private $uObject = NULL;
  private $fields = array();
  private $delta = NULL;
  private $current_field;
  
  public function __construct($uObject, $fields, $delta) {
    $this->uObject = $uObject;
    $this->fields = $fields;
    $this->delta = $delta;
    
    if (!is_numeric($delta)) {
      throw new \Exception("{$delta} must be numeric");
    }
    
    foreach ($this->fields as $field) {
      if (!$this->uObject->checkAccess($field)) {
        throw new \Exception("{$field} is not a valid field");
      }
    }
    reset($this->fields);
  }
  
  public function get($field) {
    if (!in_array($field, $this->fields)) {
      throw new \Exception("{$field} is not a valid field");
    }
    $value = $this->uObject->get($field);
    return $value[$this->delta];
  }
  
  public function set($value) {
    throw new \Exception('__METHOD__ not implemented');
  }

  public function offsetExists($field) {
    if (!in_array($field, $this->fields)) {
      throw new \Exception("{$field} is not a valid field");
    }

    $value = $this->uObject->get($field);
    if (!empty($value[$this->delta])) {
      return TRUE;
    }
    return FALSE;
  }
  
  public function offsetGet($field) {
    if (!in_array($field, $this->fields)) {
      throw new \Exception("{$field} is not a valid field");
    }

    return $this->get($field);
  }
  
  public function offsetSet($field, $value) {
    if (!in_array($field, $this->fields)) {
      throw new \Exception("{$field} is not a valid field");
    }

    $this->get($field)->set($value);
  }
  
  public function offsetUnset($field) {
    if (!in_array($field, $this->fields)) {
      throw new \Exception("{$field} is not a valid field");
    }

    $value = $this->uObject->get($field);
    unset($value[$this->delta]);
  }
  
  public function current() {
    return $this->get($this->current_field);
  }
  
  public function key() {
    return $this->current_field;
  }
  
  public function next() {
    $this->current_field =  next($this->fields);
  }

  public function rewind() {
    $this->current_field = reset($this->fields);
  }

  public function valid() {
    return !empty($this->current_field);
  }
}