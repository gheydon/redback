<?php
require "redback.php";

$rb = new redback;

$rb->open('http://192.168.211.2:80/cgi-bin/rgw/rbexamples', 'EXMOD:EmployeeList');
$rs = $rb->Select();

//var_dump($rb->__Debug_Data);
var_dump($rs->getproperty());


?>
