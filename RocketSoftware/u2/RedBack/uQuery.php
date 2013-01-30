<?php

namespace RocketSoftware\u2\RedBack;

use \RocketSoftware\u2\RedBack\uQuery;
use \RocketSoftware\u2\RedBack\uQueryItem;


/**
 * Used for manipulate the RedBack uQuery Objects
 *
 * This object is only created by the DB_RedBack object when a uQuery
 * Select or PageDisp method are excuted.
 *
 * This object can also be used by Iterator functions like foreach to scroll
 * though the fields.
 *
 * <code>
 * foreach ($obj as $value) {
 *  print_r($value);
 * }
 * </code>
 *
 * @package RocketSoftware\RedBack\uQuery
 */
class uQuery implements \Iterator {
  /*
   * Public functions
   */
  /**
   * Builds the object and links back to the RedBack object.
   */
  public function __construct($rbo) {
    $this->_rbo = $rbo;
    $this->_fields = $rbo->get('HID_FIELDNAMES', TRUE);
    $this->_setup();
  }

  /**
   * Overload to allow getting of the fields by the standard PHP method of
   * reading properties.
   *
   * Because uQuery objects allow '.' in field names then some fields may
   * require the use of the getproperty method to retrieve a field.
   *
   * The following example will not work.
   * <code>
   * print_r($obj->FIRST.NAME);
   * </code>
   */

  public function __get($property) {
    if (in_array($property, $this->_fields)) {
      return $this->get($property);
    }
    else {
      trigger_error(sprintf('Undefined property: %s::%s.', get_class($this), $property), E_USER_ERROR);
    }
  }

  /**
   * Rewind used by Iterator to move back to the first row
   */

  public function rewind() {
    $this->_goto(1);
  }

  /**
   * Used by the Iterator to return the current row
   */

  public function current() {
    return $this->eof() ? FALSE : $this->get();
  }

  /**
   * Used by the Iterator to the current key
   */

  public function key() {
    return $this->_position > $this->_maxitems ? FALSE : $this->_position;
  }

  /**
   * Used by the Iterator to move to the next row
   */

  public function next() {
    $this->movenext();
    return $this->eof() ? FALSE : $this->get();
  }

  /**
   * Used by the Iterator to check if this is a value row
   */

  public function valid() {
    return ($this->current() !== FALSE);
  }

  /**
   * Returns the requested property from the row.
   *
   * @access public
   * @param  mixed  $property  Used to specify the property to be
   *               returned. If no property is specified
   *               then all properties will be returned in
   *               an array.
   * @return uQueryItem  an array which contains all the
   *               properties or just the requested
   *               property.
   */
  public function get($property = NULL) {
    $item = new uQueryItem($this->_rbo, $this->_position);
    return $property ? $item[$property] : $item;
  }

  /**
   * Returns TRUE or FALSE depending if the possition of the row is the
   * first.
   */

  public function bof() {
    return $this->_position == 1 ? TRUE : FALSE;
  }

  /**
   * Return TRUE or FALSE depending if the position of the row is at the
   * last row of the recordset.
   */
  public function eof() {
    return $this->_position > $this->_maxitems ? TRUE : FALSE;
  }

  /**
   * Move to the previous row.
   */

  public function moveprev() {
    return $this->_goto(--$this->_position);
  }

  /**
   * Move to the Next row
   */

  public function movenext() {
    return $this->_goto(++$this->_position);
  }

  /*
   * Private functions
   */
  private $_rbo = NULL;
  private $_pageno = NULL;
  private $_maxitems = NULL;
  private $_fromitem = NULL;
  private $_uptoitem = NULL;
  private $_pagesize = NULL;
  private $_fields = NULL;
  private $_position = NULL;

  private function _goto($position) {
    if ($position > $this->_maxitems || $position < 1) {
      return FALSE;
    }
    $ret = TRUE;
    // get the correct page
    if ($position < $this->_fromitem || $position > $this->_uptoitem) {
      $pageno = intval($position / $this->_pagesize) + 1;
      $this->_rbo->set('HID_PAGENO', $pageno, TRUE);
      if ($this->_rbo->callmethod('PageDisp') === FALSE) {
        $ret = FALSE;
      }
      $this->_setup();
    }
    $this->_position = $position;

    return $ret;
  }

  private function _setup() {
    $this->_pageno = (string)$this->_rbo->get('HID_PAGENO', TRUE);
    $this->_maxitems = (string)$this->_rbo->get('HID_MAX_ITEMS', TRUE);
    $this->_position = $this->_fromitem = (string) $this->_rbo->get('HID_FROM_ITEM', TRUE);
    $this->_uptoitem = (string)$this->_rbo->get('HID_UPTO_ITEM', TRUE);
    $this->_pagesize = (string)$this->_rbo->get('HID_PAGE_SIZE', TRUE);
  }
}