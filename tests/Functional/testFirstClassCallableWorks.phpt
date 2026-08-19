--TEST--
First-class callable syntax works on a scalar receiver
--INI--
ffi.enable=1
opcache.jit=off
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

var_dump(include __DIR__ . '/../fixtures/firstClassCallable.php');
?>
--EXPECT--
int(5)
