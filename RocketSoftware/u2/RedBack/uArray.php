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
  private $iterator_position = 1;
  private $data = array();
  private $delimiter_order = array(RB_TYPE_AM => AM, RB_TYPE_VM => VM, RB_TYPE_SV => SV);
  private $allow_more_levels = TRUE;
  private $is_tainted = FALSE;
  private $needs_exploding = FALSE;
  private $output = NULL;
  private $options = array(
    'parent' => NULL,
    'parent_delta' => NULL,
    'delimiter' => AM,
    'taint_parent' => TRUE,
  );

  /**
   * $parent = NULL, $delta = NULL, $taint_parent = TRUE
   */

  public function __construct($value = NULL, array $options = array()) {
    $this->options = $options+$this->options;

    // Get the parents value mark type and shift it down 1. i.e. AM => VM and throw an error if the parent mark is SV
    if ($this->options['parent']) {
      $delimiter = $this->options['parent']->getParentMark();

      if (($mark_type = array_search($delimiter, $this->delimiter_order)) !== FALSE) {
        if (isset($this->delimiter_order[$mark_type+1])) {
          $this->options['delimiter'] = $this->delimiter_order[$mark_type+1];
        }
        else {
          $this->options['delimiter'] = $this->delimiter_order[$mark_type]; // We are at the lowest level.
          $this->allow_more_levels = FALSE;
        }
      }
      else {
        throw new \Exception('Parents Mark is not valid');
      }
    }

    if (isset($value)) {
      $this->set($value, $this->options['taint_parent']);
      $this->resetTaintedFlag();
    }
  }

  public function __toString() {
    if (!isset($this->output)) {
      // Don't cache the first 2 as it is not worth it.
      if (empty($this->data)) {
        return '';
      }
      else if (isset($this->data[0])) {
        return (string)$this->data[0];
      }
      else {
        // Add in all the blanks and get the values in the right order.
        $data = $this->data + array_fill(1, max(array_keys($this->data)), '');
        ksort($data, SORT_NUMERIC);

        $this->output = implode($this->options['delimiter'], $data);
      }
    }

    return $this->output;
  }

  public function get($delta) {
    // In PICK 0 is a special in that it returns all values;
    if ($delta === 0) {
      return $this;
    }
    elseif ($delta == 1 && isset($this->data[0]) && !$this->needs_exploding) {
      return new uArray($this->data[0], array('parent' => $this, 'parent_delta' => 1));
    }
    elseif (is_numeric($delta)) {
      $this->explode_array();
      return isset($this->data[$delta]) ? $this->data[$delta] : new uArray(NULL, array('parent' =>  $this, 'parent_delta' =>  $delta));
    }
    else {
      throw new \Exception("There can be only numerical keyed items in the array [{$delta}]");
    }
  }

  public function set($value, $taint_parent = TRUE) {
    if (is_scalar($value) || is_object($value)) {
      $this->data = array(); // all data is cleared.
      $delmiter_found = FALSE;

      if (is_scalar($value) && strpbrk($value, AM . VM . SV)) { // This should be much quicker to check if a delimiter exists, but I still need to work out the highest delimiter.
        if (!isset($this->options['parent'])) { // We don't need to do this if this has a parent, as we will determine the parent mark from the parent object.
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

        $this->options['delimiter'] = $this->delimiter_order[$delmiter_found];
        $this->data[0] = $value;
        $this->taintArray();
        $this->needs_exploding = TRUE;
      }
      elseif (isset($value)) {
        $this->data[0] = $value;
        $this->taintArray();
      }

      $this->output = NULL;
      if (!empty($this->data) && isset($this->options['parent']) && isset($this->options['parent_delta'])) {
        $this->options['parent']->updateParent($this, $this->options['parent_delta'], $taint_parent);
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

        $this->data[$delta+1] = new uArray($value, array('parent' =>  $this, 'parent_delta' =>  $delta+1));
        $this->taintArray();
      }

      $this->output = NULL;
      if (!empty($this->data) && isset($this->options['parent']) && isset($this->options['parent_delta'])) {
        $this->options['parent']->updateParent($this, $this->options['parent_delta'], $taint_parent);
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
      $this->explode_array();

      if (isset($this->data[0])) {
        $existing = $this->data[0];
        unset($this->data);

        $this->data[1] = new uArray($existing, array('parent' =>  $this, 'parent_delta' =>  1));
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

      $this->data[$delta] = new uArray($value, array('parent' =>  $this, 'parent_delta' =>  $delta));
    }
    else if (!$delta) {
      throw new \Exception('Can only delete positive keyed items in the array');
    }
    else {
      throw new \Exception('There can be only numerical keyed items in the array');
    }
    unset($this->output);
  }

  /**
   * Delete a value from the array, and move all values up. Giving the same charactorisics as the PICK DEL command
   */
  public function del($delta) {
    $this->explode_array();

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
    unset($this->output);
  }

  public function setDelta($delta) {
    $this->options['parent_delta'] = $delta;
  }

  public function updateParent($child, $delta, $taint_parent = TRUE) {
    // if there is a value in 0 move it to 1.
    if (isset($this->data[0])) {
      $value = $this->data[0];
      unset($this->data);

      $this->data[1] = new uArray($value, array('parent' =>  $this, 'parent_delta' =>  1));
    }

    $this->data[$delta] = $child;
    $this->output = NULL;
    if ($taint_parent) {
      $this->taintArray();
    }

    if (!empty($this->data) && isset($this->options['parent']) && isset($this->options['parent_delta'])) {
      $this->options['parent']->updateParent($this, $this->options['parent_delta'], $taint_parent);
    }
  }

  private function explode_array() {
    if ($this->needs_exploding) {
      $data = $this->data[0];
      unset($this->data[0]);
      foreach (explode($this->options['delimiter'], $data) as $delta => $value) {
        $this->data[$delta+1] = new uArray($value, array('parent' => $this, 'parent_delta' => $delta+1, 'taint_parent' => FALSE));
      }
      $this->needs_exploding = FALSE;
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
    return $this->options['delimiter'];
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
      $value = (string)$this;
      return !empty($value);
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
    if ((string)$value !== '' && (string)$value !== NULL) {
      if (!isset($delta)) {
        $delta = count($this)+1;
      }
      $this->get($delta)->set($value);
    }
    // If there is no value then delte the value.
    else {
      $this->explode_array();
      unset($this->data[$delta]);
      $this->output = NULL;
    }
  }

  public function offsetUnset($delta) {
    unset($this->data[$delta]);
    unset($this->output);
  }

  // Return the count the same as the DCOUNT() in PICK
  public function count() {
    if (empty($this->data) || (count($this->data) == 1 && isset($this->data[0]) && !$this->data[0])) {
      return 0;
    }
    if ($this->needs_exploding) {
      return substr_count($this->data[0], $this->options['delimiter']) + 1;
    }
    elseif ($max = max(array_keys($this->data))) {
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

  public function getValues() {
    $value = (string)$this;

    if ($value) {
      foreach (array(RB_TYPE_AM => AM, RB_TYPE_VM => VM, RB_TYPE_SV => SV) as $type => $delimiter) {
        if (strpos($value, $delimiter) !== FALSE) {
          break;
        }
      }

      $values = explode($delimiter, $delimiter . $value);
      unset($values[0]);
      return $values;
    }
    else {
      return array();
    }
  }

  public function search($value) {
    $values = $this->getValues();

    $position = array_search($value, $values);
    return $position !== FALSE ? $position+1 : FALSE;
  }

  public function searchUnique($value) {
    $values = $this->getValues();

    if (!empty($values)) {
      $array = array_combine($values, array_keys($values));

      return isset($array[(string)$value]) ? $array[(string)$value] : FALSE;
    }
    else {
      return FALSE;
    }
  }

  public function max() {
    $values = $this->getValues();

    return empty($values) ? 0 : max($values);
  }
}
