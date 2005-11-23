<?php
require 'RedBack.php';

$rb = new RB_RedBack;
$rb->__setDebug();

if (!$rb->open('rangi:8401', 'EXMOD:Employee', 'rbadmin', 'redback')) {
  echo implode("\n", $rb->__getError()) ."\n";
}

var_dump($rb->__Debug_Data);
?>
