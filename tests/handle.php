<?php
  require '../redback.php';

  $rb = new redback;
  $rb2 = new redback;
  $rb->__Set_Debug();
  $rb2->__Set_Debug();
  $rb->open('rangi:8401', 'EXMOD:Employee');
  $rb->EmpId = '1001';
  $rb->ReadData();


  $rb2->open('rangi:8401', $rb->RBOHandle);
  

  var_dump($rb2->__Debug_Data);
?>
