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
 * Boxes a scalar receiver into its registered handler object.
 *
 * The AST rewriter injects this call around the receiver of every method call in gated
 * files, so it runs on the hot path of ordinary object calls too: objects pass through
 * untouched, and values of unregistered types pass through as well so the VM raises its
 * native, catchable "Call to a member function ... on ..." Error.
 */
function box(mixed $value): mixed
{
    if (\is_object($value)) {
        return $value;
    }

    $handlerClass = Registry::handlerForValue($value);
    if ($handlerClass === null) {
        return $value;
    }

    return new $handlerClass($value);
}

/**
 * Nullsafe flavour of box(): null short-circuits exactly like a native "?->" receiver,
 * regardless of whether a Null handler is registered for plain "->" calls.
 */
function boxNullsafe(mixed $value): mixed
{
    return $value === null ? null : box($value);
}
