--TEST--
A method call on an unregistered type raises the native catchable engine Error
--INI--
ffi.enable=1
opcache.jit=off
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

echo include __DIR__ . '/../fixtures/nullError.php';
?>
--EXPECT--
Error: Call to a member function foo() on null
