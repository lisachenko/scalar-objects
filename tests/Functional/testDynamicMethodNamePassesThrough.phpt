--TEST--
Dynamic method names are dispatched on the boxed receiver
--INI--
ffi.enable=1
opcache.jit=off
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

var_dump(include __DIR__ . '/../fixtures/dynamicName.php');
?>
--EXPECT--
int(5)
