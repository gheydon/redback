<?php

namespace RocketSoftware\u2\Redback;

interface uConnectionInterface {
  public function connect($url);
  public function call($method);
  public function getStats();
  public function getDebug();
}

class uConnection implements uConnectionInterface {
  protected $uObject;
  protected $host;
  protected $port;
  protected $monitorData = array();
  protected $debugData = array();
  protected $object;

  public function __construct($uObject, $url = NULL) {
    $this->uObject = $uObject;

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
