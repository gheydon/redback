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
 * @category   DB
 * @package    RedBack
 * @author     Gordon Heydon <gordon@heydon.com.au>
 * @copyright  1997-2005 The PHP Group
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    CVS: $Id:$
 * @since      File available since Release 1.2.0
 * @deprecated File deprecated in Release 2.0.0
 */

// {{{ DB_RedBack_socket
/**
 * Connection method to communicate directly with the RedBack Scheduler
 *
 * @package Connection
 */
class DB_RedBack_socket extends DB_RedBack 
{
    // {{{ _callmethod()
    /**
     * Communicate with a RedBack Schedular.
     */

    protected function _callmethod($method) 
    {
        $debug = array('tx' => '', 'rx' => '');
        $qs = $this->_build_data();

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $result = @socket_connect($socket, $this->_url_parts['host'], $this->_url_parts['port']);
        if (!$result) {
            socket_close($socket);
            throw Exception("connecting to server failed, Reason: ($result) " . socket_strerror($result));
        } else {
            $header = sprintf("PATH_INFO\xfeHTTP_USER_AGENT\xfeQUERY_STRING\xfeSPIDER_VERSION");
            $data = sprintf("/rbo/%s\xferedback=1\xfe%s\xfe101", $method, $qs);
            $out = sprintf('%010d%s%010d%s', strlen($header), $header, strlen($data), $data);

            if (is_object($this->_logger)) {
                $this->_logger->log(sprintf('%s %s', $method, $qs));
                $start_time = microtime(true);
            }
            
            socket_write($socket, $out);
            /*
             * set up debug information
             */
            if ($this->_debug_mode) {
                $debug['tx'] = $out;
            }
            while ($length = socket_read($socket, 10, PHP_BINARY_READ)) {
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
                        if ($data = socket_read($socket, intval($length)-strlen($s), PHP_BINARY_READ)) {
                            $s.= $data;
                        }
                        else {
                            $err = socket_last_error($socket);
                            socket_close($socket);
                            throw Exception("Error Reading from Server ($err) " . socket_strerror($err));
                        }
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
                        /* If this is a rboexplorer object the add the
                         * response to the RESPONSE property */
                        elseif ($this->_object == 'rboexplorer') {
                            $this->_properties['RESPONSE']['data'] = $s;
                        }
                    } 
                }
            }

            if ($err = socket_last_error($socket)) {
                socket_close($socket);
                throw Exception("Error Reading from Server ($err) " . socket_strerror($err));
                return false;
            }

            if (is_object($this->_logger)) {
                $this->_logger->log(sprintf('%s duration %fms', $method, (microtime(true) - $start_time) * 1000));
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
        socket_close($socket);
        if ($this->_debug_mode) {
            $this->__Debug_Data[] = $debug;
        }
        $this->_tainted = false;
        return $ret;
    }

    // }}}
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
