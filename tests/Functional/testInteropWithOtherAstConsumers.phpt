--TEST--
A second zend_ast_process consumer chains through proceed() and the rewrite still works
--INI--
ffi.enable=1
opcache.jit=off
error_reporting=E_ALL & ~E_DEPRECATED
--FILE--
<?php
declare(strict_types=1);

use ZEngine\Core;
use ZEngine\System\Hook\AstProcessHook;

include __DIR__ . '/../../vendor/autoload.php';

$fired  = 0;
$second = Core::setASTProcessHandler(function (AstProcessHook $hook) use (&$fired): void {
    $fired++;
    if ($hook->hasOriginalHandler()) {
        $hook->proceed();
    }
});

$result = include __DIR__ . '/../fixtures/interop.php';
$second->uninstall();

var_dump($fired > 0, $result);
?>
--EXPECT--
bool(true)
int(5)
