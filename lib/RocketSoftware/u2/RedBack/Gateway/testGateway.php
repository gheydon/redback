<?php

namespace Rocketsoftware\u2\Redback\Gateway;

use RocketSoftware\u2\RedBack\uConnectionInterface;
use RocketSoftware\u2\RedBack\uConnection;
use RocketSoftware\u2\RedBack\uCommsException;
use RocketSoftware\u2\RedBack\uServerException;
use RocketSoftware\u2\uArrayContainer;
use Symfony\Component\Yaml\Parser;

class testGateway extends uConnection implements uConnectionInterface {
  private $script;
  private $objectName;

  public function connect($url) {
    $basePath = realpath(__DIR__ . '/../../../../../tests/scripts');
    $parts = parse_url($url) + array('path' => '');
    $path = $basePath . '/' . $parts['host'] . $parts['path'];

    if (file_exists($path)) {
      $yaml = new Parser();

      $this->script = $yaml->parse(file_get_contents($path));
    }
    else {
      throw new uCommsException('Unable to locate test script ' . $path . '.json');
    }
  }
  
  public function call($method, uArrayContainer $input_properties, $monitor, $debug) {
    // If this->objectName is not set then this must be an open.
    if ($method == ',.Refresh()') {
      $methodName = $this->objectName;
    }
    elseif (substr($method, 0, 6) != ',this.') {
      $methodName = $this->objectName = $method;
    }
    else {
      $methodName = substr($method, 6);
    }
    
    if (isset($this->script[$this->objectName])) {
      if (isset($this->script[$this->objectName][$methodName])) {
        $response = $this->script[$this->objectName][$methodName];
      }
      else if (isset($this->script[$this->objectName]['default'])) {
        $response = $this->script[$this->objectName]['default'];
      }
      else {
        throw new uServerException("Method {$method} not found");
      }
      
      if (isset($response['received'])) {
        foreach ($response['received'] as $key => $value) {
          if ($value != $input_properties[$key]) {
            throw new uServerException("{$key} value doesn't equal {$value}, but instead is {$input_properties[$key]}");
          }
        }

        $response = $response['response'];
      }
      
      $return = new uArrayContainer(NULL, array('delimiter' => VM));
      
      foreach ($response as $key => $value) {
        $return[$key] = strtr($value, array('^' => "\xfe", ']' => "\xfd"));
      }
      
      return array($return, array(), array());
    }
    else {
      throw new uCommsException('Unable to locate object ' . str_replace('|', ':', $this->objectName));
    }
  }
}