<?php

namespace RocketSoftware\u2\RedBack;

use RocketSoftware\u2\RedBack\uObject;

class uQueryItem Implements \ArrayAccess {
  private $uObject = NULL;
  private $data = NULL;
  private $fields = NULL;
  
  public function __construct(uObject $uObject, $position) {
    $this->uObject = $uObject;
    
    $from = (string)$this->uObject->get('HID_FROM_ITEM', TRUE);
    $fields = $this->uObject->get('HID_FIELDNAMES', TRUE);
    
    $this->fields = array();
    foreach ($fields as $index => $field) {
      $this->fields[(string)$field] = $index;
    }
    
    $this->data = $this->uObject->get('HID_ROW_' . (((string)$position - $from)+1), TRUE);
  }
  
  public function offsetExists($delta) {
    return array_key_exists($delta, $this->fields);
  }
  
  public function offsetGet($delta) {
    if ($this->offsetExists($delta)) {
      return $this->data[$this->fields[$delta]];
    }
    throw new \Exception('Field '. $delta . ' doesn\'t exist.');
  }
  
  public function offsetSet($delta, $value) {
    throw new \Exception('Unable to set values on this object.');
  }
  
  public function offsetUnset($delta) {
    throw new \Exception('Unable to unset values on this object.');
  }
}