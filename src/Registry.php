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

namespace ScalarObjects;

/**
 * Maps scalar types to their handler classes.
 *
 * Registration is ordinary userland code and takes effect immediately: the compile-time
 * rewrite only injects box() calls, the handler lookup always happens at call time.
 */
final class Registry
{
    /**
     * Keyed by ScalarType backing value, which equals get_debug_type() output
     *
     * @var array<string, class-string<TypeHandler<mixed>>>
     */
    private static array $handlers = [];

    private function __construct() {}

    /**
     * Registers (or replaces) the handler class for a scalar type
     *
     * @param class-string $handlerClass
     */
    public static function register(ScalarType $type, string $handlerClass): void
    {
        if (!\is_subclass_of($handlerClass, TypeHandler::class)) {
            throw new \InvalidArgumentException(\sprintf(
                'Handler class %s must extend %s',
                $handlerClass,
                TypeHandler::class,
            ));
        }

        self::$handlers[$type->value] = $handlerClass;
    }

    /**
     * Removes the handler for a type: such receivers fall back to the native engine Error
     */
    public static function unregister(ScalarType $type): void
    {
        unset(self::$handlers[$type->value]);
    }

    /**
     * Returns the registered handler class for a type, if any
     *
     * @return class-string<TypeHandler<mixed>>|null
     */
    public static function lookup(ScalarType $type): ?string
    {
        return self::$handlers[$type->value] ?? null;
    }

    /**
     * Hot-path lookup used by box() on every rewritten method call
     *
     * @internal
     *
     * @return class-string<TypeHandler<mixed>>|null
     */
    public static function handlerForValue(mixed $value): ?string
    {
        return self::$handlers[\get_debug_type($value)] ?? null;
    }
}
