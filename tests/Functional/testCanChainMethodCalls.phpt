--TEST--
Method calls on scalars can be chained
--INI--
ffi.enable=1
opcache.jit=off
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

var_dump(include __DIR__ . '/../fixtures/chaining.php');
?>
--EXPECT--
string(5) "HELLO"
