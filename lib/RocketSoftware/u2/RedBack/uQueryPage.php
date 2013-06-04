<?php

namespace RocketSoftware\u2\RedBack;

use \RocketSoftware\u2\RedBack\uQuery;
use \RocketSoftware\u2\uException;

class uQueryPage implements \Iterator {
  private $uObject;
  private $uQuery;
  private $page;
  private $start;
  private $end;
  private $iterator;

  public function __construct(uQuery $uQuery, $page) {
    $this->uQuery = $uQuery;
    $this->page = $page;
    
    $this->end = $this->uQuery->getPageSize() * $this->page;
    $this->iterator = $this->start = $this->end - $this->uQuery->getPageSize() + 1;

    if ($this->end > count($this->uQuery)) {
      $this->end = count($this->uQuery);
    }
  }

  public function get($delta) {
    if ($this->start > count($this->uQuery)) {
      throw new \uException('Start of page is after end if record set.');
    }

    if ($delta >= $this->start && $delta <= $this->end) {
      return $this->uQuery[$delta];
    }
    else {
      throw new uException('Item is outside of the range of the current page.');
    }
  }
  
  public function current() {
    return $this->get($this->iterator);
  }
  
  public function next() {
    $this->iterator++;
  }
  
  public function key() {
    return $this->iterator;
  }
  
  public function valid() {
    if ($this->iterator >= $this->start && $this->iterator <= $this->end) {
      return TRUE;
    }
    return FALSE;
  }
  
  public function rewind() {
    $this->iterator = $this->start;
  }
}