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
 * Default handler for string receivers. Byte semantics throughout (strlen, strrev),
 * exactly like the underlying standard library functions.
 *
 * @extends TypeHandler<string>
 */
final class StringHandler extends TypeHandler
{
    public function length(): int
    {
        return \strlen($this->value);
    }

    public function upper(): string
    {
        return \strtoupper($this->value);
    }

    public function lower(): string
    {
        return \strtolower($this->value);
    }

    public function trim(string $characters = " \t\n\r\0\x0B"): string
    {
        return \trim($this->value, $characters);
    }

    /**
     * @param non-empty-string $separator
     *
     * @return list<string>
     */
    public function split(string $separator): array
    {
        return \explode($separator, $this->value);
    }

    public function contains(string $needle): bool
    {
        return \str_contains($this->value, $needle);
    }

    public function startsWith(string $needle): bool
    {
        return \str_starts_with($this->value, $needle);
    }

    public function endsWith(string $needle): bool
    {
        return \str_ends_with($this->value, $needle);
    }

    public function replace(string $search, string $replace): string
    {
        return \str_replace($search, $replace, $this->value);
    }

    public function repeat(int $times): string
    {
        return \str_repeat($this->value, $times);
    }

    public function reverse(): string
    {
        return \strrev($this->value);
    }

    public function toInt(): int
    {
        return (int) $this->value;
    }

    public function toFloat(): float
    {
        return (float) $this->value;
    }
}
