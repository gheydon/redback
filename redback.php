<?php

//
// +--------------------------------------------------------------------+
// | PEAR, RedBack PHP Gateway                                          |
// +--------------------------------------------------------------------+
// | Copyright (c) 2005 The PHP Group                                   |
// +--------------------------------------------------------------------+
// | This source file is subject to version 3.0 of the PHP license,     |
// | that is bundled with this package in the file LICENSE, and is      |
// | available through the world-wide-web at the following url:         |
// | http://www.php.net/license/3_0.txt.                                |
// | If you did not receive a copy of the PHP license and are unable to |
// | obtain it through the world-wide-web, please send a note to        |
// | license@php.net so we can mail you a copy immediately.             |
// +--------------------------------------------------------------------+
// | Authors: Gordon Heydon <gordon@heydon.com.au                       |
// +--------------------------------------------------------------------+
//
// $Id:$
//


define("AM", chr(254));
define("VM", chr(253));
define("SV", chr(252));

define('RETURN_AM', 1);
define('RETURN_VM', 2);
define('RETURN_SM', 4);
define('RETURN_SV_AS_AM', 8);
define('RETURN_SV_AS_VM', 16);
define('RETURN_SV_AS_SM', 32);
define('RETURN_SV_AS_SV', 64);

class redback {
  public $__Debug_Data = array();
  public $RBOHandle = NULL;

  public function __contruct($url = '', $obj = '', $user = NULL, $pass = NULL) {
    if ($url && $method) {
      $this->open($url, $method, $user, $pass);
    }
  }

  public function __destruct() {
    $this->close();
  }
  
  public function __set($property, $value) {
    if ($this->_check_property_access($property)) {
      $this->setproperty($property, $value);
    }
    else {
      trigger_error(sprintf('Undefined property: %s::%s.', get_class($this), $property), E_USER_ERROR);
    }
  }
  
  public function __get($property) {
    if ($this->_check_property_access($property)) {
      return $this->getproperty($property);
    }
    else {
      trigger_error(sprintf('Undefined property: %s::%s.', get_class($this), $property), E_USER_ERROR);
    }
  }
  
  public function __call($method, $args) {
    return $this->callmethod($method);
  }
  
  public function open($url, $obj, $user = NULL, $pass = NULL) {
    if ($user) {
      if (!$this->_authorise($url, $obj, $user, $pass)) {
        return false;
      }
    }
    return $this->_open($url, $obj);
  }
  
/*
 * When the object is closed make sure that all updated properties have been
 * sent to the RBO Server.
 */
  public function close() {
    if ($this->RBOHandle && $this->_tainted) {
      $this->_callmethod(',.Refresh()');
    }
  }
  
  public function callmethod($method) {
    return $this->_callmethod("$this->_object,this.$method");
  }
  
  public function setproperty($property, $value = array(), $override = false) {
    if (is_array($property)) {
      // process array of values to set
      foreach ($property as $k => $v) {
        if ($override || $this->_check_property_access($k)) {
          $this->_properties[$k]['data'] = $this->_buildmv($v);
          $this->_properties[$k]['tainted'] = true;          
          $this->_tainted = true;
        }
      }
    }
    else {
      if ($override || $this->_check_property_access($property)) {
        $this->_properties[$property]['data'] = $this->_buildmv($value);
        $this->_properties[$property]['tainted'] = true;
        $this->_tainted = true;
      }
      else {
        trigger_error(sprintf('Undefined property: %s::%s.', get_class($this), $property), E_USER_ERROR);
      }
    }
  }

/*
get property will return the multi-valued data in an array. This should really return no native multi-valued data to confuse
the poor old php programmer. If there are no AM, VM, or SV then the data will just be returned. If there is multi-valued data
then an embedded array will be returned. eg.

If the multi valued data is 

'Attibute 1þAttribute 2'

The result would be :-

array
  0 => 'Attribute 1'
  1 => 'Attribute 2'

And the following would be

'Attibute 1, VM 1ýAttribute 1, VM 2þAtribute 2'

would turn into this.

array
  0 => 
    array
      0 => 'Attribute 1, VM 1'
      1 => 'Attribute 1, VM 2'
  1 => 
    array
      0 => 'Atribute 2'

*/  
  public function getproperty($property, $override = false) {
    if (array_key_exists($property, $this->_properties) && $override || $this->_check_property_access($property)) {
      if (!strstr($this->_properties[$property]['data'], AM) && 
          !strstr($this->_properties[$property]['data'], VM) && 
          !strstr($this->_properties[$property]['data'], SV)) {
        if ($this->_return_mode & RETURN_SV_AS_AM) {
          $data = array($this->_properties[$property]['data']);
        }
        elseif ($this->_return_mode & RETURN_SV_AS_VM) {
          $data = array(array($this->_properties[$property]['data']));
        }
        elseif ($this->_return_mode & RETURN_SV_AS_SM) {
          $data = array(array(array($this->_properties[$property]['data'])));
        }
        else {
          return $this->_properties[$property]['data'];
        }
      }
      else {
        $data = explode(AM, $this->_properties[$property]['data']);
        if ($addsv = strstr($this->_properties[$property]['data'], SV)) {
          $addvm = true;
        }
        else {
          $addvm = strstr($this->_properties[$property]['data'], VM);
        }
        if ($addvm) {
          foreach ($data as $am => $v) {
            $data[$am] = explode(VM, $v);
            if ($addsv) {
              foreach ($data[$am] as $vm => $v) {
                $data[$am][$vm] = explode(SV, $v);
              }
            }
          }
        }
      }
      if ($this->_return_mode & RETURN_AM) {
        return is_array($data) ? $data : false;
      }
      elseif ($this->_return_mode & RETURN_VM) {
        return is_array($data) ? $data[0] : false;
      }
      elseif ($this->_return_mode & RETURN_SM) {
        return is_array($data) && is_array($data[0]) ? $data[0][0] : false;
      }
    }
    return false;
  }
  
/*
* Return an array of all the errors that have been set.
*/
  public function __getError() {
    return explode('\n', $this->getproperty('HID_ALERT', true));
  }

  public function __setMonitor($mode = NULL) {
    $this->_monitor = $mode !== NULL ? $this->_monitor = $mode : ($this->_monitor ? false : true);
  }

  public function __setReturn($mode = NULL) {
    $this->_return_mode = $mode ? $mode : (RETURN_VM | RETURN_SV_AS_VM);
  }

  public function __setDebug($mode = NULL) {
    $this->_debug_mode = $mode !== NULL ? $this->_debug_mode = $mode : ($this->_debug_mode ? false : true);
  }

  public function __getStats() {
    if (!is_array($this->_monitor_data)) {
      $stats = array();
      foreach (explode("\n", $this->_monitor_data) as $s) {
        if (preg_match('/\[(.*)\]/', $s, $match)) {
          $group = $match[1];
        }
        elseif ($group && preg_match('/^(.*)=(.*)$/', $s, $match)) {
          $stats[$group][$match[1]] = $match[2];
        }
      }
      $this->_monitor_data = $stats;
    }
    return $this->_monitor_data;
  }
  
/*
 * Private varibles
 */
  private $_comms_layer = '';
  private $_url_parts = '';
  private $_object = '';
  private $_properties = NULL;
  private $_tainted = false;
  private $_debug_mode = false;
  private $_monitor = false;
  private $_monitor_data = NULL;
  private $_return_mode = 18;
 
/*
 * Private Functions
 */
  
  private function _open($url, $object) {
    $this->_object = $object;
    $this->_url_parts = parse_url($url);
    if (count($this->_url_parts) == 1) {
      $this->_readini($url);
    }
    if (count($this->_url_parts) == 2) {
      $this->_comms_layer = 'rgw';
    }
    else {
      $this->_comms_layer = 'cgi';
    }
    if (preg_match("/\xfd/", $object)) {
      $handle = explode(':', $object);
      $this->_properties['HID_FORM_INST']['data'] = $handle[0];
      $this->_properties['HID_USER']['data'] = $handle[1];
      $object = ',.Refresh()';
    }
    $ret = $this->_callmethod($object);
    $this->RBOHandle = $this->_properties['HID_FORM_INST']['data'] .':' .$this->_properties['HID_USER']['data'];
    return $ret;
  }

  private function _readini($url) {
    if ($db = dba_popen('phprgw.ini', 'r', 'inifile')) {
      if ($s = dba_fetch("[Databases]$url", $db)) {
        $this->_url_parts = parse_url($s);
      }
    }
  }
  
  private function _authorise($url, $obj, $user, $pass) {
    $obj_parts = explode(':', $obj);
    $this->_open($url, "{$obj_parts[0]}:RPLOGIN");
    $this->setproperty('USERID', $user);
    $this->setproperty('PASSWORD', $pass);
    if ($this->callmethod('ADOLogin')) {
      $props = array();
      $props['HID_FORM_INST'] = $this->_properties['HID_FORM_INST'];
      $props['HID_USER'] = $this->_properties['HID_USER'];
      $this->_properties = $props;
      return true;
    }
    else {
      return false;
    }
  }
  
  private function _callmethod($method) {
    if (isset($this->_url_parts)) {
      switch($this->_comms_layer) {
        case 'cgi':
          return $this->_cgi_callmethod($method);
        case 'rgw':
          return $this->_rgw_callmethod($method);
      }
      $this->_tainted = false;
    }
  }
  
  private function _check_property_access($property) {
    if (array_key_exists($property, $this->_properties)) {
      if ($this->_debug_mode) {
        return true;
      }
      else {
        if (preg_match('/^HID_/', $property)) {
          return false;
        }
        else {
          return true;
        }
      }
    }
    else {
      return false;
    }
  }
/*
 * connection plugins
 */
  private function _build_data() {
    // create post data 
    if ($this->_properties) {
      foreach ($this->_properties as $k => $v) {
        if (isset($v['tainted']) && $v['tainted'] || $k == 'HID_FORM_INST' || $k == 'HID_USER') {
          $data[] = "$k=" .urlencode($v['data']);
          unset($this->_properties[$k]['tainted']);
        }
        if (preg_match('/HID_ROW_\d+/', $k)) {
          unset($this->_properties[$k]);
        }
      }
      if (isset($data)) {
        $data[] = 'redbeans=1';
      }
      $data = implode('&', $data);
    }
    else {
      $data = 'redbeans=1';
    }
    if ($this->_monitor) {
      $data.= "&MONITOR=1";
    }
    return $data;
  }

  private function _cgi_callmethod($method) {
    $debug = array('tx' => '', 'rx' => '');
    $data = $this->_build_data();

    $fp = pfsockopen($this->_url_parts['host'], $this->_url_parts['port'] ? $this->_url_parts['port'] : 80, $errno, $errstr, 30);
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
      if ($this->_debug_mode) {
        $debug['tx'] = $out;
      }

      // strip monitor data from stream
      if ($this->_monitor && preg_match("/(\[BackEnd\]..*)$/s", $s, $match)) {
        $this->_monitor_data = preg_replace("/\x0d/", "\n", $match[1]);
        $s = preg_replace("/\[BackEnd\].*$/s", '', $s);
      }

      if (!feof($fp)) {
        $s = fgets($fp);
        if ($this->_debug_mode) {
          $debug['rx'] .= $s;
        }
        if (preg_match('/^HTTP\/1.1 [12]00/', $s)) {
          while (!feof($fp)) {
            $s = fgets($fp);
            if ($this->_debug_mode) {
              $debug['rx'] .= $s;
            }
            if (preg_match('/^(.*)=(.*)/', $s, $match)) {
              $this->_properties[$match[1]]['data'] = urldecode($match[2]);
            }
          }
          if (array_key_exists('HID_FIELDNAMES', $this->_properties)) {
            $ret = new redset($this);
          }
          else {
            $ret = true;
          }
        }
        else {
          $ret = false;
        }
      }
      else {
        $ret = false;
      }
    }
    fclose($fp);
    if ($this->_debug_mode) {
      $this->__Debug_Data[] = $debug;
    }
    return $ret;
  }

  private function _rgw_callmethod($method) {
    $debug = array('tx' => '', 'rx' => '');
    $qs = $this->_build_data();

    $fp = pfsockopen($this->_url_parts['host'], $this->_url_parts['port'], $errno, $errstr, 30);
    if (!$fp) {
      echo "$errstr ($errno)<br />\n";
    } else {
      $header = sprintf("PATH_INFO\xfeHTTP_USER_AGENT\xfeQUERY_STRING\xfeSPIDER_VERSION");
      $data = sprintf("/rbo/%s\xferedback=1\xfe%s\xfe101", $method, $qs);
      $out = sprintf('%010d%s%010d%s', strlen($header), $header, strlen($data), $data);
      fwrite($fp, $out);
      /*
       * set up debug information
       */
      if ($this->_debug_mode) {
        $debug['tx'] = $out;
      }
      while (!feof($fp)) {
        $length = fread($fp, 10);
        if ($this->_debug_mode) {
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
            $s.= fread($fp, intval($length)-strlen($s));
          }

          if ($this->_debug_mode) {
            $debug['rx'] .= $s;
          }

          // strip monitor data from stream
          if ($this->_monitor && preg_match("/(\[BackEnd\]..*)$/s", $s, $match)) {
            $this->_monitor_data = preg_replace("/\x0d/", "\n", $match[1]);
            $s = preg_replace("/\[BackEnd\].*$/s", '', $s);
          }
          
          if (preg_match('/^N/', $s)) { // Only look at N type records
            $s = substr($s, 1);
            if (preg_match_all('/^(.*?)=(.*?)$/m', $s, $match)) {
              foreach ($match[1] as $k => $v) {
                $this->_properties[$match[1][$k]]['data'] = urldecode($match[2][$k]);
              }
            } 
          } 
        }
      }
      if (array_key_exists('HID_FIELDNAMES', $this->_properties)) {
        $ret = new redset($this);
      }
      else {
        $ret = array_key_exists('HID_ERROR', $this->_properties) ? false : true;
      }
    }
    fclose($fp);
    if ($this->_debug_mode) {
      $this->__Debug_Data[] = $debug;
    }
    return $ret;
  }
 
  private function _buildmv($v) {
    if (is_array($v)) {
      if ($this->_return_mode & RETURN_VM) {
        $top = 253;
      }
      elseif ($this->_return_mode & RETURN_SM) {
        $top = 252;
      }
      else {
        $top = 254;
      }
      foreach ($v as $am => $x) {
        $v[$am] = is_array($x) ? implode(chr($top-2), $x) : $x;
      }
      return implode(chr($top), $v);
    }
    else {
      return $v;
    }
  }
}

class redset implements Iterator {
  /*
   * Public functions
   */ 
  public function __construct($rbo) {
    $this->_rbo = $rbo;
    $this->_fields = $rbo->getproperty('HID_FIELDNAMES', true);
    $this->_setup();
  }

  public function __get($property) {
    if (in_array($property, $this->_fields)) {
      return $this->getproperty($property);
    }
    else {
      trigger_error(sprintf('Undefined property: %s::%s.', get_class($this), $property), E_USER_ERROR);
    }
  }

  public function rewind() {
    $this->_goto(1);
  }

  public function current() {
    return $this->eof() ? false : $this->getproperty();
  }

  public function key() {
    return $this->_position > $this->_maxitems ? false : $this->_position;
  }

  public function next() {
    $this->movenext();
    return $this->eof() ? false : $this->getproperty();
  }

  public function valid() {
    return ($this->current() !== false);
  }

  public function getproperty($property = null) {
    static $position, $arr;
    if ($position != $this->_position) {
      $arr = array();
      $data = $this->_rbo->getproperty('HID_ROW_' .($this->_position-$this->_fromitem+1), true);
      foreach ($this->_fields as $k => $v) {
        $arr[$v] = $data[$k];
      }
    }
    return $property ? $arr[$property] : $arr;
  }

  public function bof() {
    return $this->_position == 1 ? true : false;
  }

  public function eof() {
    return $this->_position > $this->_maxitems ? true : false;
  }

  public function moveprev() {
    return $this->_goto(--$this->_position);
  }

  public function movenext() {
    return $this->_goto(++$this->_position);
  }
  
  /*
   * Private functions
   */
  private $_rbo = NULL;
  private $_pageno = NULL;
  private $_maxitems = NULL;
  private $_fromitem = NULL;
  private $_uptoitem = NULL;
  private $_pagesize = NULL;
  private $_fields = NULL;
  private $_position = NULL;

  private function _goto($position) {
    if ($position > $this->_maxitems || $position < 1) {
      return false;
    }
    $ret = true;
    // get the correct page
    if ($position < $this->_fromitem || $position > $this->_uptoitem) {
      $pageno = intval($position / $this->_pagesize) + 1;
      $this->_rbo->setproperty('HID_PAGENO', $pageno, true);
      if ($this->_rbo->callmethod('PageDisp') === false) {
        $ret = false;
      }
      $this->_setup();
    }
    $this->_position = $position;

    return $ret;
  }

  private function _setup() {
    $this->_pageno = $this->_rbo->getproperty('HID_PAGENO', true);
    $this->_maxitems = $this->_rbo->getproperty('HID_MAX_ITEMS', true);
    $this->_position = $this->_fromitem = $this->_rbo->getproperty('HID_FROM_ITEM', true);
    $this->_uptoitem = $this->_rbo->getproperty('HID_UPTO_ITEM', true);
    $this->_pagesize = $this->_rbo->getproperty('HID_PAGE_SIZE', true);
  }
}

?>
