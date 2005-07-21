<?php
require 'redback.php';

$rb = new redback;
$rb->__setDebug();
$rb->__setMonitor();

$rb->open('rangi:8401', 'EXMOD:Employee');
$rb->EmpId = '1001';
$rb->ReadData();

var_dump($rb->FirstName);
var_dump($rb->Interests);

var_dump($rb->__getStats());
?>
