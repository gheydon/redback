<?php

class uObjectTest extends \PHPUnit_Framework_TestCase {
  private $uObject;

  public function setUp() {
    $this->uObject = new RocketSoftware\u2\RedBack\uObject();
    
    $this->uObject->connect('testGateway://uObjectTest.yml');
  }
  
  public function testOpen() {
    $this->uObject->open('EXMOD:Customers');
    
    $this->uObject->ReadCust();
    
    $this->assertEquals("Nik's Musk Emporium", (string)$this->uObject->Name);
  }
  
  public function testAuthOpen() {
    $this->uObject->open('EXMOD:Customers', 'rbadmin', 'redback');
    
    $this->uObject->ReadCust();
    
    $this->assertEquals("Nik's Musk Emporium", (string)$this->uObject->Name);
  }
}