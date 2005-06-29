<?php

define("AM", chr(254));
define("VM", chr(253));
define("SV", chr(252));

class redback {
  public $__Debug_Data = array();
  
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
      $this->_authorise($user, $pass);
    }
    $this->_open($url, $obj);
  }
  
  public function close() {
  }
  
  public function callmethod($method) {
    return $this->_callmethod("$this->_object,this.$method");
  }
  
  public function setproperty($property, $value = array(), $override = false) {
    if (is_array($property)) {
      // process array of values to set
      foreach ($property as $k => $v) {
        if ($override || $this->_check_property_access($k)) {
          $this->_properties[$k]['data'] = $v;
          $this->_properties[$k]['tainted'] = true;          
        }
      }
    }
    else {
      if ($override || $this->_check_property_access($property)) {
        $this->_properties[$property]['data'] = $value;
        $this->_properties[$property]['tainted'] = true;
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

'Attibute 1şAttribute 2'

The result would be :-

array
  0 => 'Attribute 1'
  1 => 'Attribute 2'

And the following would be

'Attibute 1, VM 1ıAttribute 1, VM 2şAtribute 2'

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
    if ($override || $this->_check_property_access($property)) {
      if (!strstr($this->_properties[$property]['data'], AM) && 
          !strstr($this->_properties[$property]['data'], VM) && 
          !strstr($this->_properties[$property]['data'], SV)) {
        return $this->_properties[$property]['data'];
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
        return $data;
      }
    }
    return false;
  }
  
  public function __Set_Debug($mode = NULL) {
    $this->_debug_mode = $this->_debug_mode ? false : true;
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
 
/*
 * Private Functions
 */
  
  private function _open($url, $object) {
    $this->_object = $object;
    $this->_url_parts = parse_url($url);
    if (count($this->_url_parts) == 2) {
      $this->_comms_layer = 'rgw';
    }
    $this->_callmethod($object);
  }
  
  private function _authorise($user, $name) {
  }
  
  private function _callmethod($method) {
    if (isset($this->_url_parts)) {
      switch($this->_comms_layer) {
        case 'socket':
          return $this->_socket_callmethod($method);
        case 'rgw':
          return $this->_rgw_callmethod($method);
      }
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
          $data[] = "$k=" .$this->_buildmv($v['data']);
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
    return $data;
  }

  private function _socket_callmethod($method) {
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
    $this->_tainted = false;
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
      $header = sprintf("PATH_INFO\xfeHTTP_USER_AGENT\xfeQUERY_STRING\xfeSPIDER_VERSION\xfeRGWHOST\xfeRGWADDR");
      $data = sprintf("/rbo/%s\xferedback=1\xfe%s\xfe101\xfe%s\xfe%s", $method, $qs, $hostname, $ipaddr);
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
          $s = fread($fp, intval($length));
          if ($this->_debug_mode) {
            $debug['rx'] .= $s;
          }
          if (preg_match('/^N/', $s)) { // Only look at N type records
            $s = substr($s, 1);
            foreach (explode("\n", $s) as $v) {
              if (preg_match('/^(.*)=(.*)/', $v, $match)) {
                $this->_properties[$match[1]]['data'] = urldecode($match[2]);
              }
            }
          }
        }
      }
      if (array_key_exists('HID_FIELDNAMES', $this->_properties)) {
        $ret = new redset($this);
      }
      else {
        $ret = true;
      }
    }
    fclose($fp);
    $this->_tainted = false;
    if ($this->_debug_mode) {
      $this->__Debug_Data[] = $debug;
    }
    return $ret;
  }
 
  private function _buildmv($v) {
    if (is_array($v)) {
      foreach ($v as $am => $x) {
        if (is_array($x)) {
          $x = implode(SV, $x);
        }
        $v[$am] = implode(VM, $x);
      }
      return implode(AM, $v);
    }
    else {
      return $v;
    }
  }
}

class redset {
  /*
   * Public functions
   */ 
  public function __construct($rbo) {
    echo "create rset";
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

  public function getproperty($property = null) {
    static $position, $arr;
    if ($position != $this->_position) {
      $arr = array();
      $data = $this->_rbo->getproperty('HID_ROW_' .$this->_position, true);
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
    echo $this->_position ."\n";
    // get the correct page
    if ($position < $this->_fromitem || $position > $this->_uptoitem) {
      $pageno = intval($position / $this->_pagesize) + 1;
      $this->_rbo->set_property('HID_PAGENO', $pageno);
      $this->_rbo->callmethod('PageDisp');
      $this->_setup();
    }
    $this->_position = $position;
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
