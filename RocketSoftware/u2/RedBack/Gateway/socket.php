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
  /**
   * Communicate with a RedBack Schedular.
   */
  public function call($method) {
    $debug = array('tx' => '', 'rx' => '');
    $qs = $this->uObject->formatData();

    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    $result = @socket_connect($socket, $this->host, $this->port);
    if (!$result) {
      socket_close($socket);
      throw new \Exception("connecting to server failed, Reason: ($result) " . socket_strerror($result));
    }
    else {
      $header = sprintf("PATH_INFO\xfeRPVERSION\xfeHTTP_USER_AGENT\xfeQUERY_STRING\xfeSPIDER_VERSION");
      $data = sprintf("/rbo/%s\xfe4.3.0.123\xferedback=1\xfe%s\xfe101", $method, $qs);
      $out = sprintf('%010d%s%010d%s', strlen($header), $header, strlen($data), $data);
      $notice = '';
      $properties = array();

      /* if (is_object($this->_logger)) {
        $this->_logger->log(sprintf('%s %s', $method, $qs));
        $start_time = microtime(TRUE);
      } */
            
      socket_write($socket, $out);
      /*
       * set up debug information
       */
      if ($this->uObject->isDebugging()) {
        $debug['tx'] = $out;
      }
      while ($length = socket_read($socket, 10, PHP_BINARY_READ)) {
        if ($this->uObject->isDebugging()) {
          $debug['rx'] .= $length;
        }
        if (is_numeric($length) && intval($length) > 0) {
          // because large packets will be split over multiple packets what
          // would be good is to instead load a packet and then process all
          // of the packet it can. This would be a little more memory
          // efficent than building the whole string and then processing it.
          $length = intval($length);
          $s = '';
          while ($length - strlen($s) > 0) {
            if ($data = socket_read($socket, intval($length)-strlen($s), PHP_BINARY_READ)) {
              $s.= $data;
            }
            else {
              $err = socket_last_error($socket);
              socket_close($socket);
              throw new \Exception("Error Reading from Server ($err) " . socket_strerror($err));
            }
          }

          if ($this->uObject->isDebugging()) {
            $debug['rx'] .= $s;
          }
          //var_dump($s);

          // strip monitor data from stream
          if ($this->uObject->isMonitoring() && preg_match("/(\[BackEnd\]..*)$/s", $s, $match)) {
            $this->monitorData[] = array('method' => $method, 'data' => preg_replace("/\x0d/", "\n", $match[1]));
            $s = preg_replace("/\[BackEnd\].*$/s", '', $s);
          }
                    
          if (preg_match('/^N/', $s)) { // Only look at N type records
            $s = substr($s, 1);
            if (preg_match_all('/^(.*?)=(.*?)$/m', $s, $match)) {
              foreach ($match[1] as $k => $v) {
                $properties[$match[1][$k]]['data'] = new uArray(urldecode($match[2][$k]));
              }
            }
            /* If this is a rboexplorer object the add the
             * response to the RESPONSE property */
            elseif ($this->object == 'rboexplorer') {
              $properties['RESPONSE']['data'] = $s;
            }
            /* This is most likely a notice from the server, so gather it up and throw an exception */
            else {
              $notice .= $s;
            }
          }
        }
      }

      if ($err = socket_last_error($socket)) {
        socket_close($socket);
        throw new \Exception("Error Reading from Server ($err) " . socket_strerror($err));
      }
      
      if (!empty($notice)) {
        //TODO: Work out why it is onlu getting some of the notice and not all of the notice.
        throw new \Exception($notice);
      }

      /* if (is_object($this->_logger)) {
        $this->_logger->log(sprintf('%s duration %fms', $method, (microtime(TRUE) - $start_time) * 1000));
        } */
    }
    socket_close($socket);
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
}
