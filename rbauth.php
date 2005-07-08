<?php
require 'redback.php';

$rb = new redback;
$rb->__Set_Debug();

$rb->open('rangi:8401', 'EXMOD:Employee', 'rbadmin', 'xxx');

var_dump($rb->__Debug_Data);
?>
