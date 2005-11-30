<?php
require "RedBack.php";

$rb = &DB_RedBack::factory('socket');
//$rb->__setDebug();

$rb->open('rbexamples', 'EXMOD:EmployeeList');
$rs = $rb->Select();

foreach ($rs as $k => $v) {
  var_dump($k);
  var_dump($v);
  flush();
}

//var_dump($rb->__Debug_Data);

?>
