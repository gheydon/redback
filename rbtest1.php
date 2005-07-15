<?php
require 'redback.php';

$rb = new redback;
$rb->__setDebug();
$rb->__setMonitor();

$rb->open('http://192.168.211.2:80/cgi-bin/rgw/rbexamples', 'EXMOD:Employee');
$rb->EmpId = '1001';
$rb->ReadData();

var_dump($rb->FirstName);
var_dump($rb->Interests);

var_dump($rb->__Debug_Data);
?>
