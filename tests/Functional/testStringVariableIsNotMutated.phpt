--TEST--
Calling a method on a string variable leaves the variable a string
--INI--
ffi.enable=1
opcache.jit=off
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

var_dump(include __DIR__ . '/../fixtures/stringImmutability.php');
?>
--EXPECT--
array(3) {
  [0]=>
  string(5) "HELLO"
  [1]=>
  string(5) "Hello"
  [2]=>
  bool(true)
}
