<?php

namespace RocketSoftware\u2\RedBack;

use RocketSoftware\u2\RedBack\uObject;
use RocketSoftware\u2\RedBack\Gateway\MockGateway;
use RocketSoftware\u2\RedBack\Gateway\RedBack4;
use Symfony\Component\Yaml\Dumper;

class uObjectDevel extends uObject {
  public function __contruct($url = '', $object = '', $user = NULL, $pass = NULL) {
    parent::__contruct($url, $object, $user, $pass, TRUE);
  }

  public function writeTestScript($script_name, $append = FALSE) {
    if ($this->connection instanceof RedBack4) {
      $this->close();

      foreach ($this->getDebugData() as $debug) {
        $step = array('method' => NULL, 'request' => array(), 'response' => array());
        
        // Decode transmitted
        $s = $debug['tx'];
        $l = substr($s, 0, 10)+0;
        $s = substr($s, 10);
        $header = substr($s, 0, $l);
        $s = substr($s, $l);
        $l = substr($s, 0, 10)+0;
        $s = substr($s, 10);
        $data = substr($s, 0, $l);
        
        $values = explode("\xfe", $data);

        $method = substr($values[0], strrpos($values[0], '/')+1);
        
        if (substr($method, 0, 1) == ',') {
          list($x, $method) = explode('.', $method);
          $step['method'] = $method;
        }
        else {
          $object = $method;
          $step['method'] = $method;
        }
        
        parse_str(urldecode($values[3]), $step['request']);
        unset($step['request']['redbeans'], $step['request']['MONITOR']);

        $step['request'] = array_map(function ($a) {
          return urlencode($a);
        }, $step['request']);

        $headers = array();
        foreach ($debug['rx'] as $block) {
          $s = substr($block, 10);
          
          if (substr($s, 0, 1) == 'H') {
            $s = substr($s, 1);

            if (preg_match_all('/^(.*?): (.*?)$/m', $s, $match)) {
              foreach ($match[1] as $k => $v) {
                $headers[$match[1][$k]] = $match[2][$k];
              }
            }
          }
          else if (substr($s, 0, 1) == 'N') {
            $s = substr($s, 1);
            if ($headers['Content-type'] == 'text/xml') {
              if (preg_match_all('/^(.*?)=(.*?)$/m', $s, $match)) {
                foreach ($match[1] as $k => $v) {
                  $step['response'][$match[1][$k]] = $match[2][$k];
                }
              }
              elseif ($object == 'rboexplorer') {
                $step['response']['RESPONSE'] = $s;
              }
            }
          }
        }
        
        $script[$object][] = $step;
      }
      
      if (!empty($script)) {
        $basePath = realpath(__DIR__ . '/../../../../tests/scripts');

        $dumper = new Dumper();
        $yaml = $dumper->dump($script, 2);
      
        file_put_contents($basePath . '/' . $script_name . '.yml', $yaml);
      }
    }
  }
}