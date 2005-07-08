<?php
require "redback.php";

$rb = new redback;
//$rb->__Set_Debug();

$rb->open('rangi:8401', 'EXMOD:EmployeeList');
$rs = $rb->Select();

foreach ($rs as $k => $v) {
  var_dump($k);
  var_dump($v);
  flush();
}

//var_dump($rb->__Debug_Data);

?>
