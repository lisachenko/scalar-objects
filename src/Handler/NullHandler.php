<?php

/**
 * Scalar objects library
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
declare(strict_types=1);

namespace ScalarObjects\Handler;

use ScalarObjects\TypeHandler;

/**
 * Handler for null receivers.
 *
 * NOT registered by default: a plain `->` call on null keeps raising the native,
 * catchable engine Error unless an application opts in with
 * Registry::register(ScalarType::Null, NullHandler::class). Nullsafe `?->` calls
 * short-circuit before any handler lookup either way.
 *
 * @extends TypeHandler<null>
 */
final class NullHandler extends TypeHandler
{
    public function isNull(): bool
    {
        return true;
    }

    public function coalesce(mixed $default): mixed
    {
        return $default;
    }
}
