<?php

class uQueryTest extends \PHPUnit_Framework_TestCase {
  private $connection;
  private $uObject;

  public function setUp() {
    $this->connection = 'MockGateway://' . get_class($this) . '-' . $this->getName() . '.yml';

    if ($connection = getenv('RBEXAMPLES')) {
      $this->connection = $connection;
    }
    $this->uObject = new RocketSoftware\u2\RedBack\uObjectDevel($this->connection);
  }

  public function tearDown() {
    if (isset($this->uObject)) {
      $script_name = get_class($this) . '-' . $this->getName();
      $this->uObject->writeTestScript($script_name);
    }
  }

  public function testEmployeeList() {
    $this->uObject->open('EXMOD:EmployeeList');
    
    $rs = $this->uObject->Select();
    
    foreach ($rs as $item) {
      NULL;
    }
  }
}