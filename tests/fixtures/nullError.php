<?php

declare(strict_types=1);

$null = null;
try {
    $null->foo();

    return 'not thrown';
} catch (\Error $error) {
    return get_class($error) . ': ' . $error->getMessage();
}
