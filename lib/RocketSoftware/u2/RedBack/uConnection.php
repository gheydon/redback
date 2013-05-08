<?php

namespace RocketSoftware\u2\Redback;

use RocketSoftware\u2\RedBack\uConnectionInterface;
use RocketSoftware\u2\uArrayContainer;

abstract class uConnection implements uConnectionInterface {
  protected $host;
  protected $port;
  protected $object;

  public function __construct($url = NULL) {
    if ($url) {
      $this->connect($url);
    }
  }

  abstract public function connect($url);
  abstract public function call($method, uArrayContainer $input_properties, $monitor, $debug);
}
