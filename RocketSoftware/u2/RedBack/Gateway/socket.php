<?php
namespace RocketSoftware\u2\RedBack\Gateway;

use RocketSoftware\u2\RedBack\uObject;
use RocketSoftware\u2\RedBack\uConnection;
use RocketSoftware\u2\RedBack\uQuery;
use RocketSoftware\u2\RedBack\uArray;

/**
 * Connection method to communicate directly with the RedBack Scheduler
 *
 * @package Connection
 */
class Socket extends uConnection {
  private $socket;

  public function __destruct() {
    $this->closeSocket();
  }

  /**
   * Communicate with a RedBack Schedular.
   */
  public function call($method) {
    $debug = array('tx' => '', 'rx' => array());
    $qs = $this->uObject->formatData();
    $rxheaders = array();

    $this->openSocket();

    $header = sprintf("PATH_INFO\xfeRPVERSION\xfeHTTP_USER_AGENT\xfeQUERY_STRING\xfeSPIDER_VERSION");
    $data = sprintf("/rbo/%s\xfe4.3.0.123\xferedback=1\xfe%s\xfe101", $method, $qs);
    $out = sprintf('%010d%s%010d%s', strlen($header), $header, strlen($data), $data);
    $notice = '';
    $properties = array();

    /* if (is_object($this->_logger)) {
      $this->_logger->log(sprintf('%s %s', $method, $qs));
      $start_time = microtime(TRUE);
    } */
            
    @socket_write($this->socket, $out);
    if ($err = socket_last_error($this->socket)) {
      $this->closeSocket();
      throw new \Exception("Error Writing to Server ($err) " . socket_strerror($err));
    }

    /*
     * set up debug information
     */
    if ($this->uObject->isDebugging()) {
      $debug['tx'] = $out;
    }
    $blocks = 0;
    while ($s = $this->getRXData($debug)) {
      $blocks++;
      // strip monitor data from stream
      if ($this->uObject->isMonitoring() && preg_match("/(\[BackEnd\]..*)$/s", $s, $match)) {
        $this->monitorData[] = array('method' => $method, 'data' => preg_replace("/\x0d/", "\n", $match[1]));
        $s = preg_replace("/\[BackEnd\].*$/s", '', $s);
      }

      if (substr($s, 0, 1) == 'H') { // Process Headers
        $s = substr($s, 1);

        if (preg_match_all('/^(.*?): (.*?)$/m', $s, $match)) {
          foreach ($match[1] as $k => $v) {
            $rxheaders[$match[1][$k]] = $match[2][$k];
          }
        }
      }
      elseif (substr($s, 0, 1) == 'N') { // Only look at N type records
        $s = substr($s, 1);
        if ($rxheaders['Content-type'] == 'text/xml') {
          if (preg_match_all('/^(.*?)=(.*?)$/m', $s, $match)) {
            foreach ($match[1] as $k => $v) {
              $properties[$match[1][$k]]['data'] = new uArray(urldecode($match[2][$k]));
            }
          }
          // FIXME: Since I am now looking at the headers I most likely need to do this differently.
          /* If this is a rboexplorer object the add the
           * response to the RESPONSE property */
          elseif ($this->object == 'rboexplorer') {
            $properties['RESPONSE']['data'] = $s;
          }
        }
        /* Notices are in text/plain */
        elseif ($rxheaders['Content-type'] == 'text/plain') {
          $notice .= $s;
        }
      }
    }
    
    if ($blocks == 0) {
      // No response was given from the server.
      $this->closeSocket();
      throw new \Exception('No reponse from request.');
    }

    if (!empty($notice)) {
      $this->closeSocket();
      throw new \Exception($notice);
    }

    if ($err = socket_last_error($this->socket)) {
      $this->closeSocket();
      throw new \Exception("Error Reading from Server ($err) " . socket_strerror($err));
    }

    $this->closeSocket();

    /* if (is_object($this->_logger)) {
      $this->_logger->log(sprintf('%s duration %fms', $method, (microtime(TRUE) - $start_time) * 1000));
      } */
    if ($this->uObject->isDebugging()) {
      $this->debugData[] = $debug;
    }
    $this->_tainted = TRUE;
    if (!isset($this->object)) {
      $this->object = isset($properties['HID_HANDLE']) ? $properties['HID_HANDLE']['data'] : ''; // TODO: Fix this so sRBO's will work.
    }
    $this->uObject->loadProperties($properties);
    
    if (array_key_exists('HID_FIELDNAMES', $properties)) {
      $ret = new uQuery($this->uObject);
      /*
       * In the ASP and IBM version on the Redback Gateway the MaxRows is
       * actually a virtual field that is created when a recordset is
       * returned. This behaviour is going to be duplicated.
       */
      if (array_key_exists('HID_MAX_ITEMS', $properties)) {
        $properties['MaxRows'] = $properties['HID_MAX_ITEMS'];
      }
    }
    else {
      $ret = array_key_exists('HID_ERROR', $properties) ? FALSE : TRUE;
    }
    return $ret;
  }

  private function openSocket() {
    if (!isset($this->socket)) {
      $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
      $result = @socket_connect($this->socket, $this->host, $this->port);
      if (!$result) {
        $this->closeSocket();
        throw new \Exception("connecting to server failed, Reason: ($result) " . socket_strerror($result));
      }
    }
  }
  
  private function closeSocket() {
    if (isset($this->socket)) {
      socket_close($this->socket);
      $this->socket = NULL;
    }
  }
  
  private function getRXData(&$debug) {
    $rx = '';
    if ($length = socket_read($this->socket, 10, PHP_BINARY_READ)) {
      $s = '';

      if ($this->uObject->isDebugging()) {
        $rx .= $length;
      }
      if (is_numeric($length) && intval($length) > 0) {
        // because large packets will be split over multiple packets what
        // would be good is to instead load a packet and then process all
        // of the packet it can. This would be a little more memory
        // efficent than building the whole string and then processing it.
        $length = intval($length);
        while ($length - strlen($s) > 0) {
          if ($data = socket_read($this->socket, intval($length)-strlen($s), PHP_BINARY_READ)) {
            $s.= $data;
          }
          else {
            $err = socket_last_error($this->socket);
            $this->closeSocket();
            throw new \Exception("Error Reading from Server ($err) " . socket_strerror($err));
          }
        }

        if ($this->uObject->isDebugging()) {
          $rx .= $s;
        }
      }
      if ($this->uObject->isDebugging()) {
        $debug['rx'][] = $rx;
      }

      return $s;
    }
  }
}
