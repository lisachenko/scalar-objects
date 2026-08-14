--TEST--
An undefined method on a registered scalar type throws a catchable BadMethodCallException
--INI--
ffi.enable=1
opcache.jit=off
error_reporting=E_ALL & ~E_DEPRECATED
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

echo include __DIR__ . '/../fixtures/undefinedMethod.php';
?>
--EXPECT--
Method nope() is not defined for values of type string
