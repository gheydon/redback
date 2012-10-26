<?php

function __autoload($class) {
  // convert namespace to full file path
  $class = '../' . str_replace('\\', '/', $class) . '.php';
  require_once($class);
}