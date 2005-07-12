<?php
require 'redback.php';

$rb = new redback;
$rb->__Set_Debug();

if (!$rb->open('rangi:8401', 'EXMOD:Employee', 'rbadmin', 'redback')) {
  echo implode("\n", $rb->__getError()) ."\n";
}

var_dump($rb->__Debug_Data);
?>
