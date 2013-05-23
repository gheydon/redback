<?php

class uObjectTest extends \PHPUnit_Framework_TestCase {
  private $connection;
  private $uObject;

  public function setUp() {
    $this->connection = 'MockGateway://' . get_class($this) . '-' . $this->getName() . '.yml';

    if ($connection = getenv('RBEXAMPLES')) {
      $this->connection = $connection;
    }
    //$this->connection = 'RedBack4://203.60.7.49:8404';
    $this->uObject = new RocketSoftware\u2\RedBack\uObjectDevel($this->connection);
  }

  public function tearDown() {
    if (isset($this->uObject)) {
      $script_name = get_class($this) . '-' . $this->getName();
      $this->uObject->writeTestScript($script_name);
    }
  }

  public function testOpen() {
    $this->uObject->open('EXMOD:Customers');

    $this->assertFalse(isset($this->uObject->Name));

    $this->uObject->CustId = 1;
    $this->uObject->ReadCust();

    $this->assertTrue(isset($this->uObject->Name));
    $this->assertEquals("Nik's Musk Emporium", (string)$this->uObject->Name);
    
    $this->uObject->CustId = 2;
  }

  public function testOpenFromConstructor() {
    $this->uObject = new RocketSoftware\u2\RedBack\uObjectDevel($this->connection, 'EXMOD:Customers');

    $this->uObject->CustId = 1;
    $this->uObject->ReadCust();

    $this->assertEquals("Nik's Musk Emporium", (string)$this->uObject->Name);
  }

  public function testOpenFromFactory() {
    if (!preg_match('/^redback/i', $this->connection)) {
      $this->markTestSkipped('Requires real connection to RedBack server');
      return;
    }
    $uObject = RocketSoftware\u2\RedBack\uObjectDevel::factory($this->connection, 'EXMOD:Customers');

    $uObject->CustId = 1;
    $uObject->ReadCust();

    $this->assertEquals("Nik's Musk Emporium", (string)$uObject->Name);
    $this->uObject = NULL;
  }


  public function testAuthOpen() {
    $this->uObject->open('EXMOD:Customers', 'rbadmin', 'redback');

    $this->uObject->CustId = 1;
    $this->uObject->ReadCust();

    $this->assertEquals("Nik's Musk Emporium", (string)$this->uObject->Name);
  }
  
  /**
   * @expectedException RocketSoftware\u2\RedBack\uUserException
   * @expectedExceptionMessage Access denied to user 'rbadmin'
   */
  public function testAuthOpenFail() {
    $this->uObject->open('EXMOD:Customers', 'rbadmin', 'xxxxx');
  }
  
  /**
   * @expectedException RocketSoftware\u2\uException
   * @expectedExceptionMessage Undefined property: RocketSoftware\u2\RedBack\uObjectDevel::field_doesnt_exist
   */
  public function testErrorSet() {
    $this->uObject->open('EXMOD:Customers');
    
    $this->uObject->field_doesnt_exist = 'abc';
  }
  
  /**
   * @expectedException RocketSoftware\u2\uException
   * @expectedExceptionMessage Undefined property: RocketSoftware\u2\RedBack\uObjectDevel::field_doesnt_exist
   */
  public function testErrorGet() {
    $this->uObject->open('EXMOD:Customers');
    
    $var = $this->uObject->field_doesnt_exist;
  }
  
  public function testOpenHandle() {
    $this->uObject->open('EXMOD:Employee');

    $this->uObject->EmpId = 1012;
    $this->uObject->ReadData();
    
    $handle = $this->uObject->RBOHandle;

    $this->uObject->close();
    
    $this->uObject->open($handle);

    $this->assertEquals("Tim", (string)$this->uObject->FirstName);    
  }
}