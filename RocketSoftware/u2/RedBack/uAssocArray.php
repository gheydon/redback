<?php

namespace RocketSoftware\u2\RedBack;

use RocketSoftware\u2\RedBack\uAssocArrayItem;
use RocketSoftware\u2\RedBack\uAssocArraySource;

class uAssocArray implements \ArrayAccess, \Countable, \Iterator {
  private $source = NULL;
  private $fields = array();
  private $key_field = NULL;
  private $iterator_position = 0;

  public function __construct(uAssocArraySource $source, $fields, $key_field = NULL) {
    $this->source = $source;
    $this->fields = $fields;
    $this->key_field = $key_field;

    foreach ($this->fields as $field) {
      if (!$this->source->fieldExists($field)) {
        throw new \Exception("{$field} is not a valid field");
      }
    }

    if ($key_field) {
      if (!$this->source->fieldExists($key_field)) {
        throw new \Exception("{$field} is not a valid field");
      }
    }
  }

  public function get($delta) {
    if (isset($this->key_field)) {
      $keys = $this->source->get($this->key_field);

      if (($pos = $this->keySearch($delta)) !== FALSE) {
        return new uAssocArrayItem($this->source, $this->fields, $pos);
      }
      return new uAssocArrayItem($this->source, $this->fields, $this->count()+1);
    }
    return new uAssocArrayItem($this->source, $this->fields, $delta);
  }

  public function set($value) {
    throw new \Exception('__METHOD__ not implemented');
  }

  public function search($needle, $field) {
    foreach ($this as $key => $data) {
      if ($data[$field] == $needle) {
        return $key;
      }
    }
    return FALSE;
  }

  public function getArrayCopy() {
    $array = array();

    if (isset($this->key_field)) {
      foreach ($this->getKeys() as $key => $delta) {
        $array[$key] = $this->get($key);
      }
    }
    else {
      for ($i = 1; $i <= $this->count(); $i++) {
        $array[$i] = $this->get($i);
      }
    }

    return $array;
  }

  public function offsetExists($delta) {
    if (isset($this->key_field)) {
      if (($delta = $this->keySearch($delta)) === FALSE) {
        return FALSE;
      }
    }
    foreach ($this->fields as $field) {
      $value = $this->source->get($field);
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
    $this->get($delta)->set($value);
  }

  public function offsetUnset($delta) {
    if (isset($this->key_field)) {
      if ($delta = $this->keySearch($delta) === FALSE) {
        return;
      }
    }

    foreach ($this->fields as $fields) {
      $value = $this->source->get($field);
      unset($value[$delta]);
    }
  }

  public function count() {
    $max = 0;

    foreach ($this->fields as $field) {
      $count = count($this->source->get($field));

      $max = $count > $max ? $count : $max;
    }

    return $max;
  }

  public function current() {
    return $this->get($this->key());
  }

  public function key() {
    if (isset($this->key_field)) {
      $keys = $this->source->get($this->key_field);

      return (string)$keys[$this->iterator_position];
    }
    return $this->iterator_position;
  }

  public function next() {
    if (isset($this->key_field)) {
      $keys = $this->getKeys();
      $key_values = array_values($keys);
      if (($pos = array_search($this->iterator_position, $key_values)) !== FALSE) {
        $pos++;
        if (isset($key_values[$pos])) {
          $this->iterator_position = $key_values[$pos];
        }
        else {
          $this->iterator_position = $this->count()+1;
        }
      }
      else {
        $this->iterator_position = $this->count()+1;
      }
    }
    else {
      $this->iterator_position++;
    }
  }

  public function rewind() {
    $this->iterator_position = 1;
  }

  public function valid() {
    return $this->iterator_position <= $this->count();
  }

  private function getKeys() {
    $keys = (string)$this->source->get($this->key_field);

    foreach (array(RB_TYPE_AM => AM, RB_TYPE_VM => VM, RB_TYPE_SV => SV) as $type => $delimiter) {
      if (strpos($keys, $delimiter) !== FALSE) {
        break;
      }
    }

    $keys_array = explode($delimiter, $keys);
    $keys_array = array_combine($keys_array, array_keys($keys_array));
    $keys_array = array_map(function ($a) {
      return $a+1;
    }, $keys_array);

    return $keys_array;
  }

  private function keySearch($value) {
    if (!isset($this->key_field)) {
      if (is_numeric($value) && $value) {
        return $value;
      }
      else if (!$delta) {
        throw new \Exception('Can only delete positive keyed items in the array');
      }
      else {
        throw new \Exception('There can be only numerical keyed items in the array');
      }
    }

    $keys = $this->getKeys();
    return isset($keys[(string)$value]) ? $keys[(string)$value] : FALSE;
  }
}