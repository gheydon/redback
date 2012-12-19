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
  private $parent_mark = AM;
  private $delimiter_order = array(RB_TYPE_AM => AM, RB_TYPE_VM => VM, RB_TYPE_SV => SV);
  private $allow_more_levels = TRUE;
  private $is_tainted = FALSE;
  

  public function __construct($value = NULL, $parent = NULL, $delta = NULL) {
    $this->parent = $parent;
    $this->parent_delta = $delta;

    // Get the parents value mark type and shift it down 1. i.e. AM => VM and throw an error if the parent mark is SV
    if ($parent) {
      $parent_mark = $parent->getParentMark();
      
      if (($mark_type = array_search($parent_mark, $this->delimiter_order)) !== FALSE) {
        if (isset($this->delimiter_order[$mark_type+1])) {
          $this->parent_mark = $this->delimiter_order[$mark_type+1];
        }
        else {
          $this->parent_mark = $this->delimiter_order[$mark_type]; // We are at the lowest level.
          $this->allow_more_levels = FALSE;
        }
      }
      else {
        throw new \Exception('Parents Mark is not valid');
      }
    }

    if (isset($value)) {
      $this->set($value);
      $this->resetTaintedFlag();
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

    return implode($this->parent_mark, $data);
  }

  public function get($delta) {
    // In PICK 0 is a special in that it returns all values;
    if ($delta === 0) {
      return $this;
    }
    elseif ($delta == 1 && isset($this->data[0])) {
      return new uArray($this->data[0], $this, 1);
    }
    elseif (is_numeric($delta)) {
      return isset($this->data[$delta]) ? $this->data[$delta] : new uArray(NULL, $this, $delta);
    }
    else {
      throw new \Exception("There can be only numerical keyed items in the array [{$delta}]");
    }
  }

  public function set($value) {
    if (is_scalar($value)) {
      $this->data = array(); // all data is cleared.
      $delmiter_found = FALSE;
      
      if (strpbrk($value, AM . VM . SV)) { // This should be much quicker to check if a delimiter exists, but I still need to work out the highest delimiter.
        if (!isset($this->parent)) { // We don't need to do this if this has a parent, as we will determine the parent mark from the parent object.
          foreach ($this->delimiter_order as $type => $char) {
            if (strpos($value, $char) !== FALSE) {
              $delmiter_found = $type;
              break;
            }
          }
          
          // we want the default to be a VM if there is a delimiter.
          if ($delmiter_found !== FALSE && $delmiter_found > RB_TYPE_VM) {
            $delmiter_found = RB_TYPE_VM;
          }
        }
        else {
          // Work out what the delimiter should be based upon the parent object.
          $delmiter_found = 254 - (ord($this->getParentMark()));
        }
      }
      
      if ($delmiter_found !== FALSE) {
        if (!$this->allow_more_levels) {
          throw new \Exception('Too many levels created.');
        }
        $this->parent_mark = $this->delimiter_order[$delmiter_found];
      
        foreach (explode($this->delimiter_order[$delmiter_found], $value) as $delta => $subvalue) {
          $this->data[$delta+1] = new uArray($subvalue, $this, $delta+1);
          $this->taintArray();
        }
      }
      elseif (isset($value)) {
        $this->data[0] = $value;
        $this->taintArray();
      }
      
      if (!empty($this->data) && isset($this->parent) && isset($this->parent_delta)) {
        $this->parent->updateParent($this, $this->parent_delta);
      }
    }
    // If this is a standard PHP indexed array starting at 0 then insert each value into ::data as delta+1
    else if (is_array($value)) {
      if (!$this->allow_more_levels) {
        throw new \Exception('Too many levels created.');
      }
      $this->data = array(); // all data is cleared
      $array = $value;
      foreach ($array as $delta => $value) {
        if (!is_numeric($delta)) {
          throw new \Exception('There can be only numerical keyed items in the input array');
        }
        
        $this->data[$delta+1] = new uArray($value, $this, $delta+1);
        $this->taintArray();
      }
      
      if (!empty($this->data) && isset($this->parent) && isset($this->parent_delta)) {
        $this->parent->updateParent($this, $this->parent_delta);
      }
    }
    else {
      throw new \Exception('Unsupported data type');
    }
  }
  
  /**
   * Insert value before delta
   */
  public function ins($value, $delta) {
    if (is_numeric($delta) && $delta) {
      if (isset($this->data[0])) {
        $existing = $this->data[0];
        unset($this->data);

        $this->data[1] = new uArray($existing, $this, 1);
      }
      
      $keys = array_filter(array_keys($this->data), function ($a) use ($delta) {
        return $a <= $delta;
      });
      ksort($keys, SORT_NUMERIC);
    
      foreach (array_reverse($keys) as $key) {
        $this->data[$key+1] = $this->data[$key];
        $this->data[$key+1]->setDelta($key+1);
        unset($this->data[$key]);
      }
      
      $this->data[$delta] = new uArray($value, $this, $delta);
    }
    else if (!$delta) {
      throw new \Exception('Can only delete positive keyed items in the array');
    }
    else {
      throw new \Exception('There can be only numerical keyed items in the array');
    }
  }
  
  /**
   * Delete a value from the array, and move all values up. Giving the same charactorisics as the PICK DEL command
   */
  public function del($delta) {
    if (is_numeric($delta) && $delta) {
      unset($this->data[$delta]);
    
      $keys = array_filter(array_keys($this->data), function ($a) use ($delta) {
        return $a > $delta;
      });
      ksort($keys, SORT_NUMERIC);
    
      foreach ($keys as $key) {
        $this->data[$key-1] = $this->data[$key];
        $this->data[$key-1]->setDelta($key-1);
        unset($this->data[$key]);
      }
    }
    else if (!$delta) {
      throw new \Exception('Can only delete positive keyed items in the array');
    }
    else {
      throw new \Exception('There can be only numerical keyed items in the array');
    }
  }

  public function setDelta($delta) {
    $this->parent_delta = $delta;
  }
  
  public function updateParent($child, $delta) {
    // if there is a value in 0 move it to 1.
    if (isset($this->data[0])) {
      $value = $this->data[0];
      unset($this->data);

      $this->data[1] = new uArray($value, $this, 1);
    }

    $this->data[$delta] = $child;
    $this->taintArray();
    
    if (!empty($this->data) && isset($this->parent) && isset($this->parent_delta)) {
      $this->parent->updateParent($this, $this->parent_delta);
    }
  }
  
  public function resetTaintedFlag() {
    $this->is_tainted = FALSE;
  }
  
  public function isTainted() {
    return $this->is_tainted;
  }
  
  public function taintArray() {
    $this->is_tainted = TRUE;
  }
  
  public function getParentMark() {
    return $this->parent_mark;
  }

  public function getArrayCopy() {
    $array = array();

    for ($i = 1; $i <= $this->count(); $i++) {
      $array[$i] = $this->get($i);
    }

    return $array;
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
    $max = empty($this->data) || (isset($this->data[0]) && empty($this->data[0]) && count($this->data) == 1) ? 0 : (isset($this->data[0]) ? 1 : max(array_keys($this->data)));
    return $this->iterator_position <= $max;
  }
}
