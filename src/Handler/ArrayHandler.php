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
 * Default handler for array receivers.
 *
 * Key semantics follow the underlying standard library functions: map() and filter()
 * preserve keys, keys()/values() reindex.
 *
 * @extends TypeHandler<array<array-key, mixed>>
 */
final class ArrayHandler extends TypeHandler
{
    public function count(): int
    {
        return \count($this->value);
    }

    /**
     * @param callable(mixed): mixed $callback
     *
     * @return array<array-key, mixed>
     */
    public function map(callable $callback): array
    {
        return \array_map($callback, $this->value);
    }

    /**
     * Without a callback, falsy elements are removed - exactly like array_filter()
     *
     * @param (callable(mixed): bool)|null $callback
     *
     * @return array<array-key, mixed>
     */
    public function filter(?callable $callback = null): array
    {
        if ($callback === null) {
            return \array_filter($this->value);
        }

        return \array_filter($this->value, $callback);
    }

    /**
     * @param callable(mixed, mixed): mixed $callback
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return \array_reduce($this->value, $callback, $initial);
    }

    /**
     * @return list<array-key>
     */
    public function keys(): array
    {
        return \array_keys($this->value);
    }

    /**
     * @return list<mixed>
     */
    public function values(): array
    {
        return \array_values($this->value);
    }

    public function first(): mixed
    {
        $key = \array_key_first($this->value);

        return $key === null ? null : $this->value[$key];
    }

    public function last(): mixed
    {
        $key = \array_key_last($this->value);

        return $key === null ? null : $this->value[$key];
    }

    public function contains(mixed $element): bool
    {
        return \in_array($element, $this->value, true);
    }

    public function join(string $separator = ''): string
    {
        /** @var array<array-key, string|\Stringable|int|float|bool|null> $joinable */
        $joinable = $this->value;

        return \implode($separator, $joinable);
    }

    public function sum(): int|float
    {
        /** @var array<array-key, int|float> $summable */
        $summable = $this->value;

        return \array_sum($summable);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function reverse(bool $preserveKeys = false): array
    {
        return \array_reverse($this->value, $preserveKeys);
    }
}
