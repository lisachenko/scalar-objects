--TEST--
Registering the shipped NullHandler enables methods on null receivers
--INI--
ffi.enable=1
opcache.jit=off
--FILE--
<?php
declare(strict_types=1);

use Lisachenko\ScalarObjects\Handler\NullHandler;
use Lisachenko\ScalarObjects\Registry;
use Lisachenko\ScalarObjects\ScalarType;

include __DIR__ . '/../../vendor/autoload.php';

Registry::register(ScalarType::Null, NullHandler::class);

echo include __DIR__ . '/../fixtures/nullHandlerOptIn.php';
?>
--EXPECT--
fallback
