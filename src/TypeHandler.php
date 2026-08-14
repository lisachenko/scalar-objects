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
 * Base class for every scalar handler.
 *
 * A handler instance is a short-lived, immutable view over one scalar value: box()
 * creates it for a single method call and nothing holds on to it afterwards. Handler
 * methods return raw scalars, never wrappers - chaining works because the outer call
 * site is rewritten too.
 *
 * @template-covariant T
 */
abstract class TypeHandler
{
    /**
     * @param T $value The boxed scalar value
     */
    final public function __construct(protected readonly mixed $value) {}

    /**
     * Called for methods a handler does not define. Runs as ordinary VM code, so unlike
     * anything inside an FFI callback this exception IS catchable at the call site.
     *
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        throw new \BadMethodCallException(\sprintf(
            'Method %s() is not defined for values of type %s',
            $name,
            \get_debug_type($this->value),
        ));
    }
}
