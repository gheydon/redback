<?php

namespace RocketSoftware\u2\RedBack;

use RocketSoftware\u2\RedBack\uAssocArraySource;

class uAssocArrayItem implements \ArrayAccess, \Iterator {
  private $source = NULL;
  private $key_field = NULL;
  private $fields = array();
  private $delta = NULL;
  private $key = NULL;
  private $current_field;

  public function __construct(uAssocArraySource $source, $fields, $delta = NULL, $key_field = NULL, $key = NULL) {
    $this->source = $source;
    $this->key_field = $key_field;
    $this->fields = $fields;
    $this->delta = $delta;
    $this->key = $key;

    if (!is_numeric($delta) && isset($delta)) {
      throw new \Exception("{$delta} must be numeric");
    }

    if (isset($key_field) && !$this->source->fieldExists($key_field)) {
      throw new \Exception("{$key_field} is not a valid field");
    }

    foreach ($this->fields as $field) {
      if (!$this->source->fieldExists($field)) {
        throw new \Exception("{$field} is not a valid field");
      }
    }
    reset($this->fields);
  }

  public function get($field) {
    if (!in_array($field, $this->fields)) {
      throw new \Exception("{$field} is not a valid field");
    }
    $value = $this->source->get($field);
    return $value[$this->delta];
  }

  public function set(array $values) {
    if (!isset($this->delta) && isset($this->key_field)) {
      if (isset($this->key) || $this->key == 0) {
        $keys = $this->source->get($this->key_field);

        if (($position = $keys->searchUnique($this->key)) !== FALSE) {
          throw new \Exception("{$this->key} already exists, cannot add new field.");
        }

        $keys[] = $this->key;
        $this->delta = $keys->searchUnique($this->key);
      }
      else {
        throw new \Exception('No key specified for new array item.');
      }
    }
    // There is no key field so just append everything to the end of the array.
    else {
      $this->delta = 1;
      foreach ($this->fields as $field) {
        $new_delta = count($this->source->get($field))+1;
        $this->delta = ($this->delta < $new_delta) ? $new_delta : $this->delta;
      }
    }
    foreach ($values as $field => $value) {
      if ($field == $this->key_field) {
        throw new \Exception("{$field} is a key field and cannot be set here");
      }
      if (!in_array($field, $this->fields)) {
        throw new \Exception("{$field} is not a valid field");
      }
      $data = $this->source->get($field);
      $data[$this->delta] = $value;
    }
  }

  /**
   * Returns the current delta for this item
   */
  public function getDelta() {
    return $this->delta;
  }

  public function offsetExists($field) {
    if (!in_array($field, $this->fields)) {
      throw new \Exception("{$field} is not a valid field");
    }

    $value = $this->source->get($field);
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

    $value = $this->source->get($field);
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