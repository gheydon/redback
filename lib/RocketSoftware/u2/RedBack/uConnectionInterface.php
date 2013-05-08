<?php

namespace RocketSoftware\u2\Redback;

use RocketSoftware\u2\uArrayContainer;

interface uConnectionInterface {
  public function connect($url);
  public function call($method, uArrayContainer $input_properties, $monitor, $debug);
}