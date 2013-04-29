<?php

namespace RocketSoftware\u2\Redback;

use RocketSoftware\u2\uArrayContainer;

class uConnection implements uConnectionInterface {
  protected $host;
  protected $port;
  protected $object;

  public function __construct($url = NULL) {
    if ($url) {
      $this->connect($url);
    }
  }

  /**
   * Setup connection to the Redback server.
   */
  public function connect($url) {
    $connection = parse_url($url);
    $this->host = $connection['host'];
    $this->port = $connection['port'];
  }

  public function call($method) {
    return FALSE;
  }

  public function getStats() {
    return $this->monitorData;
  }

  public function getDebug() {
    return $this->debugData;
  }
}
