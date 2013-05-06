<?php
namespace RocketSoftware\u2\RedBack\Gateway;

use RocketSoftware\u2\RedBack\uObject;
use RocketSoftware\u2\RedBack\uConnection;
use RocketSoftware\u2\RedBack\uCommsException;
use RocketSoftware\u2\uArray;
use RocketSoftware\u2\uArrayContainer;

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
  public function call($method, uArrayContainer $input_properties, $monitor, $debug) {
    $debug = array('tx' => '', 'rx' => array());
    $rxheaders = array();
    $this->openSocket();

    $header = sprintf("PATH_INFO\xfeRPVERSION\xfeHTTP_USER_AGENT\xfeQUERY_STRING\xfeSPIDER_VERSION");
    $data = sprintf("/rbo/%s\xfe4.3.0.123\xferedbeans=1\xfe%s\xfe101", $method, $input_properties->http_build_query(TRUE, array('HID_FORM_INST', 'HID_USER'), '/HID_ROW_\d+/') . '&redbeans=1' . ($monitor ? '&MONITOR=1' : ''));
    $out = sprintf('%010d%s%010d%s', strlen($header), $header, strlen($data), $data);
    $notice = '';
    $return_properties = new uArrayContainer(NULL, array('delimiter' => VM));
    $monitorData = array();
    $debugData = array();

    /* if (is_object($this->_logger)) {
      $this->_logger->log(sprintf('%s %s', $method, $qs));
      $start_time = microtime(TRUE);
    } */

    @socket_write($this->socket, $out);
    if ($err = socket_last_error($this->socket)) {
      $this->closeSocket();
      throw new uCommsException("Error Writing to Server ($err) " . socket_strerror($err));
    }

    /*
     * set up debug information
     */
    if ($debug) {
      $debugData['tx'] = $out;
    }
    $blocks = 0;
    while ($s = $this->getRXData($debug, $debugData)) {
      $blocks++;
      // strip monitor data from stream
      if ($monitor && preg_match("/(\[BackEnd\]..*)$/s", $s, $match)) {
        $monitorData[] = array('method' => $method, 'data' => preg_replace("/\x0d/", "\n", $match[1]));
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
              $return_properties[$match[1][$k]] = urldecode($match[2][$k]);
            }
          }
          // FIXME: Since I am now looking at the headers I most likely need to do this differently.
          /* If this is a rboexplorer object the add the
           * response to the RESPONSE property */
          elseif ($this->object == 'rboexplorer') {
            $return_properties['RESPONSE'] = $s;
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
      throw new uCommsException('No reponse from request.');
    }

    if (!empty($notice)) {
      $this->closeSocket();
      throw new uServerException($notice);
    }

    if ($err = socket_last_error($this->socket)) {
      $this->closeSocket();
      throw new uCommsException("Error Reading from Server ($err) " . socket_strerror($err));
    }

    $this->closeSocket();

    $this->_tainted = TRUE;
    if (!isset($this->object)) {
      $this->object = isset($return_properties['HID_HANDLE']) ? $return_properties['HID_HANDLE'] : ''; // TODO: Fix this so sRBO's will work.
    }

    return array($return_properties, $monitorData, $debugData);
  }

  private function openSocket() {
    if (!isset($this->socket)) {
      $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
      $result = @socket_connect($this->socket, $this->host, $this->port);
      if (!$result) {
        $this->closeSocket();
        throw new uCommsException("connecting to server failed, Reason: ($result) " . socket_strerror($result));
      }
    }
  }

  private function closeSocket() {
    if (isset($this->socket)) {
      socket_close($this->socket);
      $this->socket = NULL;
    }
  }

  private function getRXData($debug, &$debugData) {
    $rx = '';
    if ($length = socket_read($this->socket, 10, PHP_BINARY_READ)) {
      $s = '';

      if ($debug) {
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
            throw new uCommsException("Error Reading from Server ($err) " . socket_strerror($err));
          }
        }

        if ($debug) {
          $rx .= $s;
        }
      }
      if ($debug) {
        $debugData['rx'][] = $rx;
      }

      return $s;
    }
  }
}
