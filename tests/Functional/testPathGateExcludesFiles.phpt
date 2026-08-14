--TEST--
includeOnly() gates the rewrite: non-matching files keep native scalar-call errors
--INI--
ffi.enable=1
opcache.jit=off
error_reporting=E_ALL & ~E_DEPRECATED
--FILE--
<?php
declare(strict_types=1);

use Lisachenko\ScalarObjects\AstRewriter;

include __DIR__ . '/../../vendor/autoload.php';

AstRewriter::includeOnly('/path/that/matches/nothing');
echo include __DIR__ . '/../fixtures/gateExcluded.php';
echo "\n";

AstRewriter::includeOnly();
var_dump(include __DIR__ . '/../fixtures/stringLength.php');
?>
--EXPECT--
Error: Call to a member function length() on string
int(5)
