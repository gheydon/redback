<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */

/**
 * RedBack Gateway for PHP
 *
 * Long description for file (if any)...
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   Net
 * @package    RedBack
 * @author     Gordon Heydon <gordon@heydon.com.au>
 * @copyright  1997-2005 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id:$
 * @since      File available since Release 1.2.0
 * @deprecated File deprecated in Release 2.0.0
 */

// {{{ includes
@include 'Log.php';
// }}}
// {{{ constants

/*
 * standard PICK defines to make it easier to convert multi-valued data to
 * something that can be used by PHP
 */
define("AM", chr(254));
define("VM", chr(253));
define("SV", chr(252));

/*
 * return multi-valued data in an attribute mark format.
 */

define('RETURN_AM', 1);

/*
 * return multi-valued data in an value mark format. eg 
 */

define('RETURN_VM', 2);

/*
 * return multi-valued data in an sub-value mark format. eg 
 */

define('RETURN_SM', 4);

/*
 * return single valued fields in attrribute format
 */

define('RETURN_SV_AS_AM', 8);

/*
 * return single valued fields in value mark format
 */

define('RETURN_SV_AS_VM', 16);

/*
 * return single valued fields in sub-value mark format
 */

define('RETURN_SV_AS_SM', 32);

/*
 * return single valued fields as string format
 */

define('RETURN_SV_AS_SV', 64);

// }}}
// {{{ DB_RedBack

class DB_RedBack 
{

    // {{{ public properties
    
    /*
     * When object has been put into debug mode the communication between
     * the gateway and the RedBack Object Server will be recorded here.
     */
    public $__Debug_Data = array();

    /*
     * This is a handle that can be saved in a session/cookie/form/query
     * string and used during consecutive page requests to open the same
     * object again.
     */
    public $RBOHandle = NULL;
    
    // }}}
    // {{{ __contruct()

    public function __construct($url = '', $method = '', $user = NULL, $pass = NULL)
    {
        $this->_readini();

        if (array_key_exists('Parameters', $this->_ini_parameters)) {
            foreach ($this->_ini_parameters['Parameters'] as $k => $v) {
                switch ($k) {
                    case 'debug':
                        $this->__setDebug($v ? true : false);
                        break;
                    case 'monitor':
                        $this->__setMonitor($v ? true : false);
                        break;
                    case 'log':
                        if (class_exists('Log') && $v) {
                            $this->_logger = &Log::factory('file', $v, 'redback', array('buffering' => true));
                        }
                }
            }
        }
        
        if ($url && $method) {
            $this->open($url, $method, $user, $pass);
        }
    }

    // }}}
    // {{{ __destruct()

    public function __destruct() 
    {
        $this->close();
        if (is_object($this->_logger)) {
            $this->_logger->flush();
            $this->_logger->close();
        }
    }
    
    // }}}
    // {{{ __set()
    
    public function __set($property, $value) 
    {
        if ($this->_check_property_access($property)) {
            $this->setproperty($property, $value);
        }
        else {
            trigger_error(sprintf('Undefined property: %s::%s.', get_class($this), $property), E_USER_ERROR);
        }
    }
    
    // }}}
    // {{{ __get()
    
    public function __get($property) 
    {
        if ($this->_check_property_access($property)) {
            return $this->getproperty($property);
        }
        else {
            trigger_error(sprintf('Undefined property: %s::%s.', get_class($this), $property), E_USER_ERROR);
        }
    }
    
    // }}}
    // {{{ __call()
    
    public function __call($method, $args) 
    {
        return $this->callmethod($method);
    }
    
    // }}}
    // {{{ open()
    
    public function open($url, $obj, $user = NULL, $pass = NULL) 
    {
        if ($user) {
            if (!$this->_authorise($url, $obj, $user, $pass)) {
                return false;
            }
        }
        return $this->_open($url, $obj);
    }
    
    // }}}
    // {{{ close()
     
    /*
     * When the object is closed make sure that all updated properties have 
     * been sent to the RBO Server.
     */
    public function close() 
    {
        if ($this->RBOHandle && $this->_tainted) {
            $this->_callmethod(',.Refresh()');
        }
    }
    
    // }}}
    // {{{ callmethod()
    
    public function callmethod($method) 
    {
        return $this->_callmethod("$this->_object,this.$method");
    }
    
    // }}}
    // {{{ setproperty()
    
    public function setproperty($property, $value = array(), $override = false) 
    {
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

    // }}}
    // {{{ getproperty()
    
    /*
     * get property will return the multi-valued data in an array. This 
     * should really return no native multi-valued data to confuse
     * the poor old php programmer. If there are no AM, VM, or SV then 
     * the data will just be returned. If there is multi-valued data
     * then an embedded array will be returned. eg.
     *
     * If the multi valued data is 
     *
     * 'Attibute 1þAttribute 2'
     *
     * The result would be :-
     *
     * array
     *   0 => 'Attribute 1'
     *   1 => 'Attribute 2'
     *
     * And the following would be
     *
     * 'Attibute 1, VM 1ýAttribute 1, VM 2þAtribute 2'
     *
     * would turn into this.
     *
     * array
     *   0 => 
     *   array
     *     0 => 'Attribute 1, VM 1'
     *     1 => 'Attribute 1, VM 2'
     *   1 => 
     *      array
     *        0 => 'Atribute 2'
     *
     */
     
    public function getproperty($property, $override = false, $return = null) 
    {
        $return = ($return ? $return : $this->_return_mode);
        if (array_key_exists($property, $this->_properties) && $override || $this->_check_property_access($property)) {
            if (!strstr($this->_properties[$property]['data'], AM) && 
                    !strstr($this->_properties[$property]['data'], VM) && 
                    !strstr($this->_properties[$property]['data'], SV)) {
                if ($return & RETURN_SV_AS_AM) {
                    $data = array($this->_properties[$property]['data']);
                }
                elseif ($return & RETURN_SV_AS_VM) {
                    $data = array(array($this->_properties[$property]['data']));
                }
                elseif ($return & RETURN_SV_AS_SM) {
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
            if ($return & RETURN_AM) {
                return is_array($data) ? $data : false;
            }
            elseif ($return & RETURN_VM) {
                return is_array($data) ? $data[0] : false;
            }
            elseif ($return & RETURN_SM) {
                return is_array($data) && is_array($data[0]) ? $data[0][0] : false;
            }
        }
        return false;
    }

    // }}}
    // {{{ __getError()
    
    /*
     * Return an array of all the errors that have been set.
     */
    public function __getError() 
    {
        return explode('\n', $this->getproperty('HID_ALERT', true, RETURN_SV_AS_SV));
    }

    // }}}
    // {{{ __setMonitor()

    public function __setMonitor($mode = NULL) 
    {
        $this->_monitor = $mode !== NULL ? $this->_monitor = $mode : ($this->_monitor ? false : true);
    }

    // }}}
    // {{{ __setReturn()

    public function __setReturn($mode = NULL) 
    {
        $this->_return_mode = $mode ? $mode : (RETURN_VM | RETURN_SV_AS_VM);
    }

    // }}}
    // {{{ __setDebug()

    public function __setDebug($mode = NULL) 
    {
        $this->_debug_mode = $mode !== NULL ? $this->_debug_mode = $mode : ($this->_debug_mode ? false : true);
    }

    // }}}
    // {{{ __getStats()

    public function __getStats() 
    {
        foreach ($this->_monitor_data as $k => $v) {
            if (isset($v['data'])) {
                $stats = array();
                foreach (explode("\n", $v['data']) as $s) {
                    if (preg_match('/\[(.*)\]/', $s, $match)) {
                        $group = $match[1];
                    }
                    elseif ($group && preg_match('/^(.*)=(.*)$/', $s, $match)) {
                        $stats[$group][$match[1]] = $match[2];
                    }
                }
                unset($this->_monitor_data[$k]['data']);
                $this->_monitor_data[$k] = array_merge($this->_monitor_data[$k],$stats);
            }
        }
        return $this->_monitor_data;
    }

    // }}}
    // {{{ private properties
    
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
    private $_ini_parameters = NULL;
    private $_logger = NULL;

    // }}}
 
    /*
     * Private Functions
     */
    
    // {{{ _open()
    
    private function _open($url, $object) 
    {
        $this->_url_parts = parse_url($url);
        if (count($this->_url_parts) == 1) {
            if (array_key_exists($this->_url_parts['path'], $this->_ini_parameters['Databases'])) {
                $this->_url_parts = parse_url($this->_ini_parameters['Databases'][$this->_url_parts['path']]);
            }
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
        $this->_object = isset($this->_properties['HID_HANDLE']) ? $this->_properties['HID_HANDLE']['data'] : '';
        return $ret;
    }

    // }}}
    // {{{ _readini()

    private function _readini() 
    {
        global $__RedBack_ini;

        if (!$__RedBack_ini) {
            $ini_path = DIRECTORY_SEPARATOR == '\\' ? array('.', 'C:\\winnt') : array('.', '/etc');
            foreach ($ini_path as $directory) {
                $file = $directory . DIRECTORY_SEPARATOR .'phprgw.ini';
                if (file_exists($file)) {
                    $__RedBack_ini = parse_ini_file($file, true);
                    break;
                }
            }
        }
        $this->_ini_parameters = $__RedBack_ini;
    }

    // }}}
    // {{{ _authorise()
    
    private function _authorise($url, $obj, $user, $pass) 
    {
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

    // }}}
    // {{{ _callmethod()
    
    private function _callmethod($method) 
    {
        if (isset($this->_url_parts)) {
            switch($this->_comms_layer) {
                case 'cgi':
                    return $this->_cgi_callmethod($method);
                case 'rgw':
                    return $this->_rgw_callmethod($method);
            }
        }
    }

    // }}}
    // {{{ _check_property_access()
    
    private function _check_property_access($property) 
    {
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

    // }}}
    // {{{ _build_data()
    
    private function _build_data() 
    {
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

    // }}}
    // {{{ _buildmv()
 
    /*
     * this function turns a PHP array back into a multivalued field.
     *
     * TODO: I also think this could be a little tidier and faster by using
     * array_walk_recusive() instead.
     *
     * WARNING: This function is relying on the fact that all of the keys in
     * the array are numeric. If there is a alpha key then the max() will
     * return a alpha for the array_fill().
     */
    private function _buildmv($v) 
    {
        if (is_array($v)) {
            ksort($v);
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
                if (is_array($x)) {
                     ksort($x);
                     if (($max = max(array_keys($x))) > 0) {
                         $x = array_union_key(array_fill(0, $max, ''), $x);
                     }
                     $v[$am] = implode(chr($top-2), $x);
                }
                else {
                     $v[$am] = $x;
                }
            }
            if (($keys = array_keys($v)) && ($max = max($keys)) > 0) {
                $v = array_union_key(array_fill(0, $max, ''), $v);
            }
            return implode(chr($top), $v);
        }
        else {
            return $v;
        }
    }

    // }}}
    /*
     * connection plugins
     */
    // {{{ _cgi_callmethod()

    private function _cgi_callmethod($method) 
    {
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
                $this->_monitor_data[] = array('method' => $method,'data' => preg_replace("/\x0d/", "\n", $match[1]));
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
                        $ret = new DB_RedBack_RecordSet($this);
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
        $this->_tainted = false;
        return $ret;
    }

    // }}}
    // {{{ _rgw_callmethod()

    private function _rgw_callmethod($method) 
    {
        $debug = array('tx' => '', 'rx' => '');
        $qs = $this->_build_data();

        $fp = pfsockopen($this->_url_parts['host'], $this->_url_parts['port'], $errno, $errstr, 30);
        if (!$fp) {
            echo "$errstr ($errno)<br />\n";
        } else {
            $header = sprintf("PATH_INFO\xfeHTTP_USER_AGENT\xfeQUERY_STRING\xfeSPIDER_VERSION");
            $data = sprintf("/rbo/%s\xferedback=1\xfe%s\xfe101", $method, $qs);
            $out = sprintf('%010d%s%010d%s', strlen($header), $header, strlen($data), $data);

            if (is_object($this->_logger)) {
                $this->_logger->log(sprintf('%s %s', $method, $qs));
                $start_time = microtime(true);
            }
            
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
                        $this->_monitor_data[] = array('method' => $method, 'data' => preg_replace("/\x0d/", "\n", $match[1]));
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

            if (is_object($this->_logger)) {
                $this->_logger->log(sprintf('%s duration %f', $method, microtime(true) - $start_time));
            }
            
            if (array_key_exists('HID_FIELDNAMES', $this->_properties)) {
                $ret = new DB_RedBack_RecordSet($this);
                /*
                 * In the ASP and IBM version on the Redback Gateway the MaxRows is
                 * actually a virtual field that is created when a recordset is
                 * returned. This behaviour is going to be duplicated.
                 */
                if (array_key_exists('HID_MAX_ITEMS', $this->_properties)) {
                    $this->_properties['MaxRows'] = $this->_properties['HID_MAX_ITEMS'];
                }
            }
            else {
                $ret = array_key_exists('HID_ERROR', $this->_properties) ? false : true;
            }
        }
        fclose($fp);
        if ($this->_debug_mode) {
            $this->__Debug_Data[] = $debug;
        }
        $this->_tainted = false;
        return $ret;
    }

    // }}}
}

// }}}
// {{{ DB_RedBack_RecordSet

class DB_RedBack_RecordSet implements Iterator {
    /*
     * Public functions
     */ 
    public function __construct($rbo) {
        $this->_rbo = $rbo;
        $this->_fields = $rbo->getproperty('HID_FIELDNAMES', true, RETURN_AM);
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
            $data = $this->_rbo->getproperty('HID_ROW_' .(($this->_position-$this->_fromitem)+1), true, RETURN_AM);
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
        $this->_pageno = $this->_rbo->getproperty('HID_PAGENO', true, RETURN_SV_AS_SV);
        $this->_maxitems = $this->_rbo->getproperty('HID_MAX_ITEMS', true, RETURN_SV_AS_SV);
        $this->_position = $this->_fromitem = $this->_rbo->getproperty('HID_FROM_ITEM', true, RETURN_SV_AS_SV);
        $this->_uptoitem = $this->_rbo->getproperty('HID_UPTO_ITEM', true, RETURN_SV_AS_SV);
        $this->_pagesize = $this->_rbo->getproperty('HID_PAGE_SIZE', true, RETURN_SV_AS_SV);
    }
}

// }}}
// {{{ array_union_key()

/*
 * creates a union of all the keys in an array
 *
 * the resulting array is the combination of all the keys with the 2 arrays.
 * Any duplicate keys will have the contents of the array from the later
 * arrays. Because of the limitation of overloading in php you cannot do
 * this. eg
 * 
 * $rb->property[3] = 'blah'
 * 
 * So instead you need to use this function and do the following. eg
 * 
 * $rb->property = array_union_key($rb->property, array(3 => 'blah') 
 *
 * This is not as sexy as in PICK but I think that is it acceptable.
 */
function array_union_key() 
{
    if (func_num_args() < 2) {
            trigger_error(sprintf('Warning: Wrong parameter count for array_union_key()', get_class($this), $property), E_USER_WARNING);
            return false;
    }
    $a1 = func_get_arg(0);
    for ($i = 1; $i < func_num_args() ; $i++) {
        $a2 = func_get_arg($i);
        foreach ($a2 as $k => $v) {
            $a1[$k] = $v;
        }
    }
    return $a1;
}

// }}}
// {{{ build_assoc_array()

function build_assoc_array($rb, $fields) 
{
    $arr = array(); $flds = array();
    foreach ($fields as $f) {
        $flds[$f] = '';
    }
    foreach ($fields as $f) {
        foreach ($rb->getproperty($f) as $k => $v) {
            if (!array_key_exists($k, $arr)) {
                $arr[$k] = $flds;
            }
            $arr[$k][$f] = $v;
        }
    }
    return $arr;
}

// }}}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * foldmethod: marker
 * End:
 */
?>
