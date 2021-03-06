<?php

Namespace RocketSoftware\u2\RedBack;

use RocketSoftware\u2\uException;

class uCommsException extends uException {
  private $debugLog;
  private $monitorLog;
  
  public function __construct($message = NULL, $code = 0, array $monitorLog = array(), array $debugLog = array(), \Exception $previous = NULL) {
    if ($monitorLog) {
      $this->monitorLog = $monitorLog;
    }

    if ($debugLog) {
      $this->debugLog = $debugLog;
    }
    
    parent::__construct($message, $code, $previous);
  }
  
  public function getMonitor() {
    return $this->monitorLog;
  }

  public function getDebug() {
    return $this->debugLog;
  }
}