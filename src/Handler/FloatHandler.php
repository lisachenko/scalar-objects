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
 * Default handler for float receivers
 *
 * @extends TypeHandler<float>
 */
final class FloatHandler extends TypeHandler
{
    public function abs(): float
    {
        return \abs($this->value);
    }

    public function floor(): float
    {
        return \floor($this->value);
    }

    public function ceil(): float
    {
        return \ceil($this->value);
    }

    public function round(int $precision = 0): float
    {
        return \round($this->value, $precision);
    }

    public function toInt(): int
    {
        return (int) $this->value;
    }

    public function toString(): string
    {
        return (string) $this->value;
    }
}
