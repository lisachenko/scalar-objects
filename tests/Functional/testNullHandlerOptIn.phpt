--TEST--
Registering the shipped NullHandler enables methods on null receivers
--INI--
ffi.enable=1
opcache.jit=off
error_reporting=E_ALL & ~E_DEPRECATED
--FILE--
<?php
declare(strict_types=1);

use ScalarObjects\Handler\NullHandler;
use ScalarObjects\Registry;
use ScalarObjects\ScalarType;

include __DIR__ . '/../../vendor/autoload.php';

Registry::register(ScalarType::Null, NullHandler::class);

echo include __DIR__ . '/../fixtures/nullHandlerOptIn.php';
?>
--EXPECT--
fallback
