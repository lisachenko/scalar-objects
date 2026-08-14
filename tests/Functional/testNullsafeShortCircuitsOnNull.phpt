--TEST--
Nullsafe calls on null short-circuit exactly like native "?->"
--INI--
ffi.enable=1
opcache.jit=off
error_reporting=E_ALL & ~E_DEPRECATED
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

echo include __DIR__ . '/../fixtures/nullsafe.php';
?>
--EXPECT--
skipped
