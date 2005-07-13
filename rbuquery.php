<?php
require "redback.php";

$rb = new redback;
//$rb->__setDebug();

$rb->open('rangi:8401', 'EXMOD:EmployeeList');
$rs = $rb->Select();

while (!$rs->eof()) {
  var_dump($rs->getproperty());
  flush();
  $rs->movenext();
}

//var_dump($rb->__Debug_Data);

?>
