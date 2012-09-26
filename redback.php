<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4 foldmethod=marker: */

/**
 * RedBack Gateway for PHP
 *
 * This set of Classes is used to allow PHP to create a connection to a IBM
 * U2 RedBack scheduler. This in turn allows PHP to communicate with the IBM
 * U2 databases UniData and Universe.
 *
 * PHP versions 5
 *
 * LICENSE: This source file is subject to version 3.0 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_0.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category   DB
 * @package    RedBack
 * @author     Gordon Heydon <gordon@heydon.com.au>
 * @copyright  1997-2005 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id:$
 * @since      File available since Release 1.2.0
 * @deprecated File deprecated in Release 2.0.0
 */

// {{{ includes
/**
 * Use Pear Log package to log events
 */
@include 'Log.php';
// }}}
// {{{ constants

/*
 * standard PICK defines to make it easier to convert multi-valued data to
 * something that can be used by PHP
 */
/**
 * PICK Attribute Mark (AM)
 */

define("AM", chr(254));

/**
 * PICK Value Mark (VM)
 */

define("VM", chr(253));

/**
 * PICK Sub-Value Mark (SV or SVM)
 */

define("SV", chr(252));

/**
 * Define order of Pick Delimiters
 */

define('RB_TYPE_AM', 0);
define('RB_TYPE_VM', 1);
define('RB_TYPE_SV', 2);

/**
 * return multi-valued data in an attribute mark format.
 */

define('RETURN_AM', 1);

/**
 * return multi-valued data in an value mark format. eg 
 */

define('RETURN_VM', 2);

/**
 * return multi-valued data in an sub-value mark format. eg 
 */

define('RETURN_SM', 4);

/**
 * return single valued fields in attrribute format
 */

define('RETURN_SV_AS_AM', 8);

/**
 * return single valued fields in value mark format
 */

define('RETURN_SV_AS_VM', 16);

/**
 * return single valued fields in sub-value mark format
 */

define('RETURN_SV_AS_SM', 32);

/**
 * return single valued fields as string format
 */

define('RETURN_SV_AS_SV', 64);

// }}}
// {{{ DB_RedBack
/**
 * DB_RedBack class.
 * 
 * DB_RedBack class is the main class which is used to access your RedBack
 * Server
 *
 * @package DB_RedBack
 */
class DB_RedBack 
{
    // {{{ public properties
    
    /**
     * In debug mode, communication data is stored here.
     *
     * When object has been put into debug mode the communication between
     * the gateway and the RedBack Object Server will be recorded here.
     *
     * @access public
     */
    public $__Debug_Data = array();

    /**
     * The handle of the object which is used to re-associate back the same
     * object.
     * 
     * This is a handle that can be saved in a session/cookie/form/query
     * string and used during consecutive page requests to open the same
     * object again.
     *
     * @access public
     */
    public $RBOHandle = NULL;
    
    // }}}
    // {{{ factory()
    /**
     * The factory will open the DB_RedBack object and load the required
     * communication method.
     *
     * @since 29/11/2005
     *
     * @param   string  $handler A pointer to the handler which is to be
     *                           used to communicate with the RedBack
     *                           scheduler
     *
     * @param   string  $url    A string which contains the path to the U2
     *                          RedBack Server. This can be in the form of a
     *                          standard uri for a web server if the cgi
     *                          gateway is being used or a host:port if the
     *                          communication is directly with the RedBack
     *                          Scheduler.
     *
     * @param   string  $object An identfy which represents which RedBack
     *                          object is to be opened.
     *
     * @param   string  $user   Name of the user the RedBack user to which
     *                          this object is to be opened as.
     *
     * @param   string  $pass   Password for the user.
     *
     * @return  object
     *
     * @access  public
     */
    public function &factory($handler, $url = '', $object = '', $user = NULL, $pass = NULL) {
        $handler = strtolower($handler);
        $class   = 'DB_RedBack_' .$handler;
        $classfile = 'RedBack/' .$handler .'.php';

        @include_once($classfile);

        if (class_exists($class)) {
            $obj = new $class($url, $object, $user, $pass);
            return $obj;
        }
        return false;
    }
    // }}}
    // {{{ __contruct()
    /**
     * DB_RedBack constructor
     *
     * @param   string  $url    A string which contains the path to the U2
     *                          RedBack Server. This can be in the form of a
     *                          standard uri for a web server if the cgi
     *                          gateway is being used or a host:port if the
     *                          communication is directly with the RedBack
     *                          Scheduler.
     *
     * @param   string  $object An identfy which represents which RedBack
     *                          object is to be opened.
     *
     * @param   string  $user   Name of the user the RedBack user to which
     *                          this object is to be opened as.
     *
     * @param   string  $pass   Password for the user.
     *
     * @return  null
     *
     * @access  public
     */
    public function __construct($url = '', $object = '', $user = NULL, $pass = NULL)
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
        
        if ($url && $object) {
            $this->open($url, $object, $user, $pass);
        }
    }

    // }}}
    // {{{ __destruct()
    /**
     * close of the DB_RedBack object
     *
     * The descructor checks to make sure that the are no tainted properties
     * and if there is it will send a refresh() method to the scheduler to
     * update these on the server. If the logging facility has been used
     * then the log file will be closed.
     *
     * @access public
     */

    public function __destruct() 
    {
        $this->close();
        if (is_object($this->_logger)) {
            $this->_logger->close();
        }
    }
    
    // }}}
    // {{{ __set()
    /**
     * The overloaded __set() allows the RBO properties to be exported.
     *
     * Overloading gives the PHP RedBack gateway the advantage in that over
     * other gateways in that properties are able to be exported as if they
     * were normal properties within this object.
     *
     * <code>
     * $rbobj->name = 'John Doe';
     * </code>
     *
     * is the same as the following
     *
     * <code>
     * $rbobj->setproperty('name', 'John Doe');
     * </code>
     *
     * sometime the need will a raise when you will need to use the
     * setproperty() method instead of the overloaded function.
     *
     * @access public
     */
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
    /**
     * Allow RBO properties to be retrieved as if they were apart of the
     * object
     *
     * This is an alias for the getproperty method, which allows the PHP
     * developer to treat the RBO property as if it were are a part of the
     * PHP object. This will make it easier for the PHP developer to
     * understand without having to deal with how PICK works.
     *
     * If a property does not exist in the RBO then access to the property
     * will fail.
     *
     * @access public
     */
    
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
    /**
     * Allow RBO methods to be called as if they are normal PHP methods.
     *
     * When calling RedBack methods, any arguments are ignored, as they are     
     * not used by the RedBack Scheduler
     *
     * @access public
     */
    
    public function __call($method, $args) 
    {
        return $this->callmethod($method);
    }
    
    // }}}
    // {{{ open()
    /**
     * This method will open a connection to the RBO and retrieve
     * the properties of the Object.
     *
     * @access public
     *
     * @param string $url      The network to the RedBack Object server. 
     *                         This will depending on which factory has been
     *                         loaded, or it can be listed in the phprgw.ini
     *                         file.
     *
     * @param string $obj      The description of the Object that is going
     *                         to be connected to.
     *
     * @param string $user     An optional field that when included will
     *                         allow the user to be authenticated.
     *
     * @param string $pass     If the user has been specified then the
     *                         password will also need to be specified to
     *                         allow the authenication to complete.
     *
     *                         Also note that if you are using
     *                         authentication then an additional call the
     *                         RedBack Scheduler will be made.
     */
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
     *
     * This function is automatically called by the destructor.
     */
    public function close() 
    {
        if ($this->RBOHandle && $this->_tainted) {
            $this->_callmethod(',.Refresh()');
        }
    }
    
    // }}}
    // {{{ callmethod()
    /**
     * Call a RBO method.
     *
     * To call a RBO method user this function or use the overloaded option
     * which will allow the developer to call the method as if it were a
     * normal PHP method.
     *
     * As there is no method inside the object to validate that the method
     * is valid without passing the command to the RedBack Scheduler then
     * the return needs to be checked to make sure it is not false.
     *
     * @access public
     * 
     * @param string $method    Name of the method that is to be called.
     *
     * @return mixed            If there has been an error in calling this
     *                          method then false will be returned,
     *                          otherwise true. If this object is a uQuery
     *                          object and the Select or PageDisp methods
     *                          were called a DB_RedBack_RecordSet will be
     *                          returned.
     */
    
    public function callmethod($method) 
    {
        return $this->_callmethod("$this->_object,this.$method");
    }
    
    // }}}
    // {{{ setproperty()
    /**
     * Set a RBO property to a new value.
     *
     * setproperty() will set the an RBO property to any desired value.
     *
     * @access public
     *
     * @param   mixed   $property   This can be specified as either the name
     *                              of the property to be set or an array
     *                              which has the properties listed as keys
     *                              and values will set multiple values at
     *                              once.
     * @param   mixed   $value      This value is a formated as an array
     *                              which represents a multi-valued field.
     *
     * @param   bool    $override   Allows the developer to update any
     *                              internal RedBack properties. Use this
     *                              with caution as if the values are set
     *                              incorrectly then this may cause unknown
     *                              issues.
     */
    
    public function setproperty($property, $value = array(), $override = false) 
    {
        if (is_array($property)) {
            // process array of values to set
            foreach ($property as $k => $v) {
                if ($override || $this->_check_property_access($k)) {
                    $this->_properties[$k]['data']->set($v);
                    $this->_properties[$k]['tainted'] = true;
                    $this->_tainted = true;
                }
            }
        }
        else {
            if ($override || $this->_check_property_access($property)) {
                $this->_properties[$property]['data']->set($value);
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
    
    /**
     * Return the value of a RBO property.
     *
     * get property will return the multi-valued data in an array. This 
     * should really return no native multi-valued data to confuse
     * the poor old php programmer. If there are no AM, VM, or SV then 
     * the data will just be returned. If there is multi-valued data
     * then an embedded array will be returned. eg.
     *
     * If the multi valued data is 
     *
     * 'Attibute 1]Attribute 2'
     *
     * The result would be :-
     *
     * array
     *   0 => 'Attribute 1'
     *   1 => 'Attribute 2'
     *
     * And the following would be
     *
     * 'Attibute 1, VM 1]Attribute 1, VM 2]Atribute 2'
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
     * @param   string  $property   The name of the propety that needs to be
     *                              returned.
     *
     * @param   bool    $override   Used to retrieve values of internal
     *                              RedBack fields
     *
     * @param   mixed   $return     Speifies how the program is going to be
     *                              expecting the multi-value to be
     *                              returned.
     *
     * @return  mixed               Depending on how the return flag is set
     *                              will determine the value that is
     *                              returned.
     */
     
    public function getproperty($property, $override = false, $return = null) 
    {
        $return = ($return ? $return : $this->_return_mode);
        if (array_key_exists($property, $this->_properties) && $override || $this->_check_property_access($property)) {
            return $this->_properties[$property]['data'];
        }
        return false;
    }

    // }}}
    // {{{ __getError()
    
    /**
     * Return an array of all the errors that have been set.
     *
     * Any errors that have occured since the last method call whill be
     * returned in the field as an array
     *
     * @access public
     * @return array    An array of all the errors that have occured.
     */
    public function __getError() 
    {
        return explode('\n', $this->getproperty('HID_ALERT', true, RETURN_SV_AS_SV));
    }

    // }}}
    // {{{ __setMonitor()
    /**
     * Turns on and off the RedBack Scheduler monitor which returns a
     * statistics on how long it took to do certain methods.
     *
     * @access  public
     * @param   bool    $mode   If this field is ommited then the monitor
     *                          will be toggled between on and off. If true
     *                          or false is specified then this value will
     *                          be used.
     *
     */

    public function __setMonitor($mode = NULL) 
    {
        $this->_monitor = $mode !== NULL ? $this->_monitor = $mode : ($this->_monitor ? false : true);
    }

    // }}}
    // {{{ __setReturn()
    /*
     * Sets how PICK multi-valued fields are going to be returned to the
     * application.
     */

    public function __setReturn($mode = NULL) 
    {
        $this->_return_mode = $mode ? $mode : (RETURN_VM | RETURN_SV_AS_VM);
    }

    // }}}
    // {{{ __setDebug()
    /**
     * Allows the developer to record all the information that has been past
     * between this object and the RedBack Scheduler.
     *
     * The transactional information is stored in $this->__Debug_Data Use
     * the following to display this information.
     *
     * <code>
     * print_r($obj->__Debug_Data);
     * </code>
     *
     * @access public
     * @param   mixed   $mode   true or false will set the debug mode to
     *                          this value or if no value is past the debug
     *                          module will be toggled.
     */

    public function __setDebug($mode = NULL) 
    {
        $this->_debug_mode = $mode !== NULL ? $this->_debug_mode = $mode : ($this->_debug_mode ? false : true);
    }

    // }}}
    // {{{ __getStats()
    /**
     * Returns all the statistical data from the RedBack monitor.
     */

    public function __getStats() 
    {
        if (is_array($this->_monitor_data)) {
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
        }
        return $this->_monitor_data;
    }

    // }}}
    // {{{ private properties
    
    /*
     * Private varibles
     */
    /**
     * Specifies which comms layer to use.
     *
     * @deprecated Removed in favour of factory creation.
     * @access private
     */
    protected $_comms_layer = '';
    protected $_url_parts = '';
    protected $_object = '';
    protected $_properties = NULL;
    protected $_tainted = false;
    protected $_debug_mode = false;
    protected $_monitor = false;
    protected $_monitor_data = NULL;
    protected $_return_mode = 18;
    protected $_ini_parameters = array();
    protected $_logger = NULL;

    // }}}
 
    /*
     * Private Functions
     */
    
    // {{{ _open()
    
    protected function _open($url, $object) 
    {
        $this->_url_parts = parse_url($url);
        if (count($this->_url_parts) == 1) {
            if (array_key_exists($this->_url_parts['path'], $this->_ini_parameters['Databases'])) {
                $this->_url_parts = parse_url($this->_ini_parameters['Databases'][$this->_url_parts['path']]);
            }
        }
        if (preg_match("/\xfd/", $object)) {
            $handle = explode(':', $object);
            $this->_properties['HID_FORM_INST']['data'] = new DB_RedBack_Array($handle[0]);
            $this->_properties['HID_USER']['data'] = new DB_RedBack_Array($handle[1]);
            $object = ',.Refresh()';
        }
        $ret = $this->_callmethod($object);
        $this->RBOHandle = $this->_properties['HID_FORM_INST']['data'] .':' .$this->_properties['HID_USER']['data'];
        $this->_object = isset($this->_properties['HID_HANDLE']) ? $this->_properties['HID_HANDLE']['data'] : '';
        return $ret;
    }

    // }}}
    // {{{ _readini()
    /**
     * @access  private
     */

    protected function _readini() 
    {
        global $__RedBack_ini;

        if (!$__RedBack_ini) {
            $ini_path = DIRECTORY_SEPARATOR == '\\' ? array('.', 'C:\\winnt') : array('.', '/etc');
            foreach ($ini_path as $directory) {
                $file = $directory . DIRECTORY_SEPARATOR .'phprgw.ini';
                if (file_exists($file)) {
                    $__RedBack_ini = @parse_ini_file($file, true);
                    break;
                }
                else {
                    $__RedBack_ini = array();
                }
            }
        }
        $this->_ini_parameters = $__RedBack_ini;
    }

    // }}}
    // {{{ _authorise()
    
    protected function _authorise($url, $obj, $user, $pass) 
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
    
    protected function _callmethod($method) 
    {
        return false;
    }

    // }}}
    // {{{ _check_property_access()
    
    protected function _check_property_access($property) 
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
    
    protected function _build_data() 
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
}

// }}}
// {{{ DB_RedBack_RecordSet
/**
 * Used for manipulate the RedBack uQuery Objects
 *
 * This object is only created by the DB_RedBack object when a uQuery
 * Select or PageDisp method are excuted.
 *
 * This object can also be used by Iterator functions like foreach to scroll
 * though the fields.
 *
 * <code>
 * foreach ($obj as $value) {
 *   print_r($value);
 * }
 * </code>
 *
 * @package DB_RedBack
 */
class DB_RedBack_RecordSet implements Iterator {
    /*
     * Public functions
     */ 
    /**
     * Builds the object and links back to the RedBack object.
     */
    public function __construct($rbo) {
        $this->_rbo = $rbo;
        $this->_fields = $rbo->getproperty('HID_FIELDNAMES', true, RETURN_AM);
        $this->_setup();
    }

    /**
     * Overload to allow getting of the fields by the standard PHP method of
     * reading properties.
     *
     * Because uQuery objects allow '.' in field names then some fields may
     * require the use of the getproperty method to retrieve a field.
     *
     * The following example will not work.
     * <code>
     * print_r($obj->FIRST.NAME);
     * </code>
     */
     
    public function __get($property) {
        if (in_array($property, $this->_fields)) {
            return $this->getproperty($property);
        }
        else {
            trigger_error(sprintf('Undefined property: %s::%s.', get_class($this), $property), E_USER_ERROR);
        }
    }

    /**
     * Rewind used by Iterator to move back to the first row
     */
     
    public function rewind() {
        $this->_goto(1);
    }

    /**
     * Used by the Iterator to return the current row
     */
     
    public function current() {
        return $this->eof() ? false : $this->getproperty();
    }

    /**
     * Used by the Iterator to the current key
     */
     
    public function key() {
        return $this->_position > $this->_maxitems ? false : $this->_position;
    }

    /**
     * Used by the Iterator to move to the next row
     */
     
    public function next() {
        $this->movenext();
        return $this->eof() ? false : $this->getproperty();
    }

    /**
     * Used by the Iterator to check if this is a value row
     */
     
    public function valid() {
        return ($this->current() !== false);
    }

    /**
     * Returns the requested property from the row.
     *
     * @access public
     * @param   mixed   $property   Used to specify the property to be
     *                              returned. If no property is specified
     *                              then all properties will be returned in
     *                              an array.
     * @return  array               an array which contains all the
     *                              properties or just the requested
     *                              property.
     */
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

    /**
     * Returns true or false depending if the possition of the row is the
     * first.
     */
     
    public function bof() {
        return $this->_position == 1 ? true : false;
    }

    /**
     * Return true or false depending if the position of the row is at the
     * last row of the recordset.
     */
    public function eof() {
        return $this->_position > $this->_maxitems ? true : false;
    }

    /**
     * Move to the previous row.
     */
     
    public function moveprev() {
        return $this->_goto(--$this->_position);
    }

    /**
     * Move to the Next row
     */

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
/**
 * creates a union of all the keys in an array
 *
 * the resulting array is the combination of all the keys with the 2 arrays.
 * Any duplicate keys will have the contents of the array from the later
 * arrays. Because of the limitation of overloading in php you cannot do
 * this. eg
 *
 * <code>
 * $rb->property[3] = 'blah'
 * </code>
 * 
 * So instead you need to use this function and do the following. eg
 * 
 * <code>
 * $rb->property = array_union_key($rb->property, array(3 => 'blah') 
 * </code>
 *
 * This is not as sexy as in PICK but I think that is it acceptable. If the
 * use of this is not acceptable the only other method is to copy the
 * property to a normal array and then update it, and copy it back.
 *
 * <code>
 * $tmp = $rb->property;
 * $tmp[3] = 'blah';
 * $rb->property = $tmp;
 * </code>
 *
 * @access public
 *
 * @return array
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
// {{{ DB_RedBack_Array

class DB_RedBack_Array implements ArrayAccess, Countable, Iterator {
  private $parent = NULL;
  private $parent_delta = NULL;
  private $iterator_position = 1;
  private $data = array();
  private $parent_type = AM;
  
  public function __construct($value = NULL, $parent = NULL, $delta = NULL) {
    $this->parent = $parent;
    $this->parent_delta = $delta;
    
    if ($value) {
      $this->set($value);
    }
  }
  
  public function __toString() {
    if (empty($this->data)) {
      return '';
    }
    
    if (isset($this->data[0])) {
      return (string)$this->data[0];
    }
    
    // Add in all the blanks and get the values in the right order.
    $data = $this->data + array_fill(1, max(array_keys($this->data)), '');
    ksort($data, SORT_NUMERIC);

    return implode($this->parent_type, $data);
  }
  
  public function get($delta) {
    // In PICK 0 is a special in that it returns all values;
    if ($delta === 0 || ($delta == 1 && isset($this->data[0]))) {
      return $this;
    }
    elseif (is_numeric($delta)) {
      return isset($this->data[$delta]) ? $this->data[$delta] : new DB_RedBack_Array(NULL, $this, $delta);
    }
    else {
      throw Exception('There can be only numerical keyed items in the array');
    }
  }
  
  public function set($value) {
    static $delimiter_order = array(RB_TYPE_AM => AM, RB_TYPE_VM => VM, RB_TYPE_SV => SV);
    
    if (is_scalar($value)) {
      $this->data = array(); // all data is cleared.
      $delmiter_found = FALSE;
      
      foreach ($delimiter_order as $type => $char) {
        if (strpos($value, $char) !== FALSE) {
          $delmiter_found = $type;
          break;
        }
      }
      
      if ($delmiter_found !== FALSE) {
        $this->parent_type = $delimiter_order[$delmiter_found];
        
        foreach (explode($delimiter_order[$delmiter_found], $value) as $delta => $subvalue) {
          $this->data[$delta+1] = new DB_RedBack_Array($subvalue, $this);
        }
      }
      elseif (!empty($value)) {
        $this->data[0] = $value;
      }
      
      if (!empty($this->data) && isset($this->parent) && isset($this->parent_delta)) {
        $this->parent->updateParent($this, $this->parent_delta);
      }
    }
  }
  
  public function updateParent($child, $delta) {
    // if there is a value in 0 move it to 1.
    if (isset($this->data[0])) {
      $value = $this->data[0];
      unset($this->data);
      
      $this->data[1] = new DB_RedBack_Array($value, $this, 1);
    }
    
    $this->data[$delta] = $child;
  }

  public function value($delta) {
    var_dump($this->_data);
  }
  
  // As per how PICK handles this, in that doesn't exist is a null string.
  public function offsetExists($delta) {
    if ($delta === 0) {
      return !empty($this->data);
    }
    else {
      $value = (string)$this->get($delta);
    
      return !empty($value);
    }
  }
  
  public function offsetGet($delta) {
    return $this->get($delta);
  }
  
  public function offsetSet($delta, $value) {
    $this->get($delta)->set($value);
  }
  
  public function offsetUnset($delta) {
    unset($this->data[$delta]);
  }
  
  // Return the count the same as the DCOUNT() in PICK
  public function count() {
    if (empty($this->data)) {
      return 0;
    }
    if ($max = max(array_keys($this->data))) {
      return $max;
    }
    else {
      return 1;
    }
  }
  
  public function current() {
    return $this->get($this->iterator_position);
  }
  
  public function key() {
    return $this->iterator_position;
  }
  
  public function next() {
    $this->iterator_position++;
  }
  
  public function rewind() {
    $this->iterator_position = 1;
  }
  
  public function valid() {
    $max = isset($this->data[0]) ? 1 : max(array_keys($this->data));
    return $this->iterator_position <= $max;
  }
}

// }}}
// {{{ build_assoc_array()
/**
 * Flips the associated array into a normal PHP array as opposed to a PICK
 * assocated array
 *
 * @access public
 * 
 * @param  RB_RedBack $rb     The RedBack object so that the fields can be
 *                            extracted from the properties
 *
 * @param  array      $fields A list of properties which exist in the RedBack
 *                            Object.
 *
 * @return array
 *
 */
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
