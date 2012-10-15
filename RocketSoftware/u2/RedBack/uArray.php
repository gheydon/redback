<?php
namespace RocketSoftware\u2\RedBack;

use RocketSoftware\u2\RedBack\uArray;

/*
 * The values are normally defined in RocketSoftware\u2\Redback\uObject.php but this can be loaded before this.
 */
if (!defined('AM')) {
  /**
   * PICK Attribute Mark (AM)
   */

  define("AM", chr(254));

  /**
   * PICK Value Mark (VM)
   */

  define("VM", chr(253));

  /**
   * PICK Sub-Value Mark (SV or SVM)
   */

  define("SV", chr(252));
}

/**
 * Define order of Pick Delimiters
 */

define('RB_TYPE_AM', 0);
define('RB_TYPE_VM', 1);
define('RB_TYPE_SV', 2);

class uArray implements \ArrayAccess, \Countable, \Iterator {
  private $parent = NULL;
  private $parent_delta = NULL;
  private $iterator_position = 1;
  private $data = array();
  private $parent_type = AM;

  public function __construct($value = NULL, $parent = NULL, $delta = NULL) {
    $this->parent = $parent;
    $this->parent_delta = $delta;

    if ($value) {
     $this->set($value);
    }
  }

  public function __toString() {
    if (empty($this->data)) {
      return '';
    }

    if (isset($this->data[0])) {
      return (string)$this->data[0];
    }

    // Add in all the blanks and get the values in the right order.
    $data = $this->data + array_fill(1, max(array_keys($this->data)), '');
    ksort($data, SORT_NUMERIC);

    return implode($this->parent_type, $data);
  }

  public function get($delta) {
    // In PICK 0 is a special in that it returns all values;
    if ($delta === 0 || ($delta == 1 && isset($this->data[0]))) {
      return $this;
    }
    elseif (is_numeric($delta)) {
      return isset($this->data[$delta]) ? $this->data[$delta] : new uArray(NULL, $this, $delta);
    }
    else {
      $e = new \Exception('There can be only numerical keyed items in the array');
      throw $e;
    }
  }

  public function set($value) {
    static $delimiter_order = array(RB_TYPE_AM => AM, RB_TYPE_VM => VM, RB_TYPE_SV => SV);

    if (is_scalar($value)) {
      $this->data = array(); // all data is cleared.
      $delmiter_found = FALSE;
      
      foreach ($delimiter_order as $type => $char) {
       if (strpos($value, $char) !== FALSE) {
         $delmiter_found = $type;
         break;
       }
      }
      
      if ($delmiter_found !== FALSE) {
        $this->parent_type = $delimiter_order[$delmiter_found];
      
        foreach (explode($delimiter_order[$delmiter_found], $value) as $delta => $subvalue) {
          $this->data[$delta+1] = new uArray($subvalue, $this);
        }
      }
      elseif (!empty($value)) {
        $this->data[0] = $value;
      }
      
      if (!empty($this->data) && isset($this->parent) && isset($this->parent_delta)) {
        $this->parent->updateParent($this, $this->parent_delta);
      }
    }
  }

  public function updateParent($child, $delta) {
    // if there is a value in 0 move it to 1.
    if (isset($this->data[0])) {
      $value = $this->data[0];
      unset($this->data);

      $this->data[1] = new DB_RedBack_Array($value, $this, 1);
    }

    $this->data[$delta] = $child;
  }

  // As per how PICK handles this, in that doesn't exist is a NULL string.
  public function offsetExists($delta) {
    if ($delta === 0) {
      return !empty($this->data);
    }
    else {
      $value = (string)$this->get($delta);

      return !empty($value);
    }
  }

  public function offsetGet($delta) {
    return $this->get($delta);
  }

  public function offsetSet($delta, $value) {
    $this->get($delta)->set($value);
  }

  public function offsetUnset($delta) {
    unset($this->data[$delta]);
  }

  // Return the count the same as the DCOUNT() in PICK
  public function count() {
    if (empty($this->data)) {
      return 0;
    }
    if ($max = max(array_keys($this->data))) {
      return $max;
    }
    else {
      return 1;
    }
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
    $max = isset($this->data[0]) ? 1 : max(array_keys($this->data));
    return $this->iterator_position <= $max;
  }
}
