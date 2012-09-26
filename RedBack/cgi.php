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

// {{{ DB_RedBack_cgi
/**
 * Connection object which allows the access to the RedBack Schedular via
 * the CGI gateway
 *
 * @package Connection
 */
class DB_RedBack_cgi extends DB_RedBack
{
    // {{{ _callmethod()
    /**
     * cgi connection method to allow the DB_RedBack to communicate with the
     * RedBack Scheduler.
     */

    protected function _callmethod($method) 
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
                            $this->_properties[$match[1]]['data'] = new DB_RedBack_Array(urldecode($match[2]));
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
