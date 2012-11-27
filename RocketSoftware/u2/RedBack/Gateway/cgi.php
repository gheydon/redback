<?php

namespace RocketSoftware\u2\RedBack\Gateway;

use RocketSoftware\u2\RedBack\uObject;
use RocketSoftware\u2\RedBack\uQuery;
use RocketSoftware\u2\RedBack\uArray;

/**
 * Connection object which allows the access to the RedBack Schedular via
 * the CGI gateway
 *
 * @package Connection
 */
class cgi extends uConnection {
  /**
   * cgi connection method to allow the DB_RedBack to communicate with the
   * RedBack Scheduler.
   */
  protected function _callmethod($method) {
    $debug = array('tx' => '', 'rx' => '');
    $qs = $this->uObject->formatData();

    $fp = pfsockopen($this->host, $this->port ? $this->port : 80, $errno, $errstr, 30);
    if (!$fp) {
      echo "$errstr ($errno)<br />\n";
    } else {
      $out = ($data ? "POST " : "GET ") .$this->_url_parts['path'] . (preg_match('/\/$/', $this->_url_parts['path']) ? '' : '/') .$method ."?redbeans=1 HTTP/1.1\r\n";
      $out .= "User-agent: redbeans\r\n";
      $out .= "Host: " .$this->_url_parts['host'] ."\r\n";
      if ($data) {
        $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $out .= "Content-Length: " .strlen($data) ."\r\n";
      }
      $out .= "Keep-Alive: 300\r\n";
      $out .= "Connection: Keep-Alive\r\n\r\n";
      if ($data) {
        $out .= "$data";
      }
      fwrite($fp, $out);
      /*
       * set up debug information
       */
       if ($this->uObject->isDebugging()) {
        $debug['tx'] = $out;
      }

      // strip monitor data from stream
      if ($this->uObject->isMonitoring() && preg_match("/(\[BackEnd\]..*)$/s", $s, $match)) {
        $this->_monitor_data[] = array('method' => $method,'data' => preg_replace("/\x0d/", "\n", $match[1]));
        $s = preg_replace("/\[BackEnd\].*$/s", '', $s);
      }

      if (!feof($fp)) {
        $s = fgets($fp);
        if ($this->uObject->isDebugging()) {
          $debug['rx'] .= $s;
        }
        if (preg_match('/^HTTP\/1.1 [12]00/', $s)) {
          while (!feof($fp)) {
            $s = fgets($fp);
            if ($this->uObject->isDebugging()) {
              $debug['rx'] .= $s;
            }
            if (preg_match('/^(.*)=(.*)/', $s, $match)) {
              $this->_properties[$match[1]]['data'] = new uArray(urldecode($match[2]));
            }
          }
          $ret = TRUE;
        }
        else {
          $ret = FALSE;
        }
      }
      else {
        $ret = FALSE;
      }
    }
    fclose($fp);
    if ($this->uObject->isDebugging()) {
      $this->debugData[] = $debug;
    }
    $this->_tainted = FALSE;
    if (!isset($this->object)) {
      $this->object = isset($properties['HID_HANDLE']) ? $properties['HID_HANDLE']['data'] : ''; // TODO: Fix this so sRBO's will work.
    }
    $this->uObject->loadProperties($properties);
    
    if ($ret && array_key_exists('HID_FIELDNAMES', $this->_properties)) {
      $ret = new uQuery($this->uObject);
    }
    
    return $ret;
  }
}
