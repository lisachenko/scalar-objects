<?php

declare(strict_types=1);

try {
    return "gated"->length();
} catch (\Error $error) {
    return get_class($error) . ': ' . $error->getMessage();
}
