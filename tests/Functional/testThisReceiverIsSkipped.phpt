--TEST--
$this receivers are not wrapped and scalar returns of own methods still box
--INI--
ffi.enable=1
opcache.jit=off
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

var_dump(include __DIR__ . '/../fixtures/thisReceiver.php');
?>
--EXPECT--
string(2) "OK"
