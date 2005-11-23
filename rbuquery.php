<?php
require "RedBack.php";

$rb = new DB_RedBack;
$rb->__setDebug();

$rb->open('rbexamples', 'EXMOD:EmployeeList');
$rs = $rb->Select();

while (!$rs->eof()) {
  var_dump($rs->getproperty());
  flush();
  $rs->movenext();
}

//var_dump($rb->__Debug_Data);

?>
