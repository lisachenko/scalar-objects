--TEST--
Spread and named arguments pass through the rewrite unchanged
--INI--
ffi.enable=1
opcache.jit=off
error_reporting=E_ALL & ~E_DEPRECATED
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

var_dump(include __DIR__ . '/../fixtures/argumentsPassthrough.php');
?>
--EXPECT--
array(2) {
  [0]=>
  string(3) "a+b"
  [1]=>
  string(3) "a+b"
}
