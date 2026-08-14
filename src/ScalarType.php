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

namespace Lisachenko\ScalarObjects;

/**
 * Every non-object receiver type a handler class can be registered for.
 *
 * The backing values match get_debug_type() output, which is what the box() hot path
 * uses to find a handler without any switch on the value itself.
 */
enum ScalarType: string
{
    case String    = 'string';
    case Int       = 'int';
    case Float     = 'float';
    case Bool      = 'bool';
    case ArrayType = 'array';
    case Null      = 'null';

    /**
     * Resolves the scalar type of a value, or null for objects and other unboxable values
     */
    public static function tryFromValue(mixed $value): ?self
    {
        return self::tryFrom(\get_debug_type($value));
    }
}
