--TEST--
A user-defined handler class replaces the shipped one at call time
--INI--
ffi.enable=1
opcache.jit=off
error_reporting=E_ALL & ~E_DEPRECATED
--FILE--
<?php
declare(strict_types=1);

use ScalarObjects\Registry;
use ScalarObjects\ScalarType;
use ScalarObjects\TypeHandler;

include __DIR__ . '/../../vendor/autoload.php';

final class ShoutHandler extends TypeHandler
{
    public function shout(): string
    {
        return strtoupper((string) $this->value) . '!';
    }
}

Registry::register(ScalarType::String, ShoutHandler::class);

echo include __DIR__ . '/../fixtures/customHandler.php';
?>
--EXPECT--
BEEP!
