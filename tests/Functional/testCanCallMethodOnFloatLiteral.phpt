--TEST--
A method can be called on a float literal
--INI--
ffi.enable=1
opcache.jit=off
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

var_dump(include __DIR__ . '/../fixtures/floatMethods.php');
?>
--EXPECT--
float(3)
