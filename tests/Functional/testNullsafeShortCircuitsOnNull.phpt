--TEST--
Nullsafe calls on null short-circuit exactly like native "?->"
--INI--
ffi.enable=1
opcache.jit=off
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

echo include __DIR__ . '/../fixtures/nullsafe.php';
?>
--EXPECT--
skipped
