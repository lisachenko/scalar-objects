--TEST--
Ordinary object method calls in rewritten files pass through box() unchanged
--INI--
ffi.enable=1
opcache.jit=off
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

var_dump(include __DIR__ . '/../fixtures/objectCall.php');
?>
--EXPECT--
int(3)
