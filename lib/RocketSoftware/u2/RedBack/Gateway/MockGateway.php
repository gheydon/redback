<?php

namespace Rocketsoftware\u2\Redback\Gateway;

use RocketSoftware\u2\RedBack\uConnectionInterface;
use RocketSoftware\u2\RedBack\uConnection;
use RocketSoftware\u2\RedBack\uCommsException;
use RocketSoftware\u2\RedBack\uServerException;
use RocketSoftware\u2\uArrayContainer;
use Symfony\Component\Yaml\Parser;

class MockGateway extends uConnection implements uConnectionInterface {
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
      if (!empty($this->script[$this->objectName])) {
        $step = array_shift($this->script[$this->objectName]);
      }
      else {
        $updated = array();
        foreach ($input_properties as $key => $value) {
          if ($input_properties[$key]->isTainted()) {
            $updated[] = $key;
          }
         }
        throw new uServerException("No more steps for object {$this->objectName}, method {$method}, updated fields " . implode(', ', $updated));
      }

      if (!empty($step['request'])) {
        foreach ($step['request'] as $key => $value) {
          if (urldecode($value) != $input_properties[$key]) {
            throw new uServerException("{$key} value doesn't equal {$value}, but instead is {$input_properties[$key]}");
          }
        }
      }

      $return = new uArrayContainer(NULL, array('delimiter' => VM));

      foreach ($step['response'] as $key => $value) {
        $return[$key] = urldecode($value);
      }

      return array($return, array(), array());
    }
    else {
      throw new uCommsException('Unable to locate object ' . $this->objectName);
    }
  }
}