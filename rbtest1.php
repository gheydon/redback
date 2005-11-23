<?php
require 'RedBack.php';

$rb = new DB_RedBack;
//$rb->__setDebug();
//$rb->__setMonitor();

$rb->open('rbexamples', 'EXMOD:Employee');
$rb->EmpId = '1001';
$rb->ReadData();

var_dump($rb->FirstName);
var_dump($rb->Interests);

var_dump($rb->__getStats());
?>
