<?php

declare(strict_types=1);

try {
    "hello"->nope();

    return 'not thrown';
} catch (\BadMethodCallException $exception) {
    return $exception->getMessage();
}
