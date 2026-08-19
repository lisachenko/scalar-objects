--TEST--
Array operations chain through raw-array returns
--INI--
ffi.enable=1
opcache.jit=off
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

var_dump(include __DIR__ . '/../fixtures/arrayChain.php');
?>
--EXPECT--
int(12)
