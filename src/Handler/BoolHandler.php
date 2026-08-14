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
 * Default handler for bool receivers
 *
 * @extends TypeHandler<bool>
 */
final class BoolHandler extends TypeHandler
{
    public function not(): bool
    {
        return !$this->value;
    }

    public function toInt(): int
    {
        return (int) $this->value;
    }

    public function toString(): string
    {
        return $this->value ? 'true' : 'false';
    }
}
