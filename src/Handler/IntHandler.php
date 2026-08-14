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

namespace Lisachenko\ScalarObjects\Handler;

use Lisachenko\ScalarObjects\TypeHandler;

/**
 * Default handler for int receivers.
 *
 * Note the receiver syntax: `1->m()` is a PHP parse error, integer literals need
 * parentheses - `(42)->toFloat()`.
 *
 * @extends TypeHandler<int>
 */
final class IntHandler extends TypeHandler
{
    public function abs(): int
    {
        return \abs($this->value);
    }

    public function clamp(int $min, int $max): int
    {
        return \max($min, \min($max, $this->value));
    }

    public function isEven(): bool
    {
        return $this->value % 2 === 0;
    }

    public function isOdd(): bool
    {
        return $this->value % 2 !== 0;
    }

    public function toFloat(): float
    {
        return (float) $this->value;
    }

    public function toString(): string
    {
        return (string) $this->value;
    }
}
