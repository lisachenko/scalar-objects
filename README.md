# Scalar Objects

Method calls on PHP scalars — `"hello"->length()`, `(42)->toFloat()`,
`[1, 2, 3]->map(...)` — with user-registerable handler classes per type, in the
spirit of [nikic/scalar_objects](https://github.com/nikic/scalar_objects), built on
[z-engine](https://github.com/lisachenko/z-engine) instead of a C extension.

```php
<?php

use Lisachenko\ScalarObjects\Registry;
use Lisachenko\ScalarObjects\ScalarType;

require 'vendor/autoload.php'; // installs the rewrite hook

// in any file compiled after that:
"hello"->length();                          // 5
"  hello  "->trim()->upper();               // "HELLO"
(42)->toFloat();                            // 42.0
[1, 2, 3]->map(fn (int $x): int => $x * 2); // [2, 4, 6]
[1, 2, 3, 4]->filter(fn ($x) => $x % 2 === 0)->sum(); // 6
"hello"->length(...);                       // first-class callable, works too
```

## How it works

No opcode hooks, no runtime FFI on the call path. When the package boots it installs
a `zend_ast_process` handler through z-engine, and from that moment every file the
engine compiles (outside `vendor/`) gets one compile-time rewrite:

```
expr->m(args)     ==>   \Lisachenko\ScalarObjects\box(expr)->m(args)
expr?->m(args)    ==>   \Lisachenko\ScalarObjects\boxNullsafe(expr)?->m(args)
```

`box()` is plain PHP: objects pass through untouched, scalars are wrapped in the
handler object registered for their type, and unregistered types pass through so the
VM raises its native — and, unlike anything an opcode handler could produce,
**catchable** — `Error: Call to a member function m() on string`. Dispatch on the
wrapper is ordinary VM dispatch; all FFI work happens once per file at compile time.

## The contract

- **Handlers return raw scalars, never wrappers.** Chaining works because the outer
  call site is rewritten too: `$s->upper()->trim()` compiles to
  `box(box($s)->upper())->trim()`.
- **Nothing is mutated.** `$s->upper()` leaves `$s` a string; `is_string($s)` stays
  true. Handler instances are short-lived immutable views over one value.
- **Errors are catchable.** An undefined method on a registered type throws
  `BadMethodCallException`; a call on an unregistered type raises the engine's own
  `Error`. Both are ordinary exceptions — no FFI callback is involved at call time.
- **`1->m()` is a parse error in PHP.** Integer and float literals need parentheses:
  `(42)->toFloat()`. String and array literals parse fine without them.
- **`?->` on null short-circuits** exactly like native nullsafe calls, whether or not
  a Null handler is registered.

## Registering handlers

Defaults ship for `string`, `int`, `float`, `bool` and `array` and are registered by
the bootstrap. `null` is deliberately left out — opt in if you want it:

```php
use Lisachenko\ScalarObjects\Handler\NullHandler;
use Lisachenko\ScalarObjects\Registry;
use Lisachenko\ScalarObjects\ScalarType;

Registry::register(ScalarType::Null, NullHandler::class);

(null)->coalesce('fallback'); // "fallback"
```

Custom handlers extend `Lisachenko\ScalarObjects\TypeHandler` and replace the shipped ones at
call time — registration is ordinary runtime code, no recompilation involved:

```php
use Lisachenko\ScalarObjects\TypeHandler;

final class ShoutHandler extends TypeHandler
{
    public function shout(): string
    {
        return strtoupper((string) $this->value) . '!';
    }
}

Registry::register(ScalarType::String, ShoutHandler::class);

"beep"->shout(); // "BEEP!"
```

## Gating the rewrite

Every method call in a rewritten file — object receivers included — pays a small
`box()` detour (about a quarter of a microsecond per call). The rewriter never
touches `vendor/` or its own sources; for production keep the gate tight:

```php
use Lisachenko\ScalarObjects\AstRewriter;

AstRewriter::includeOnly(__DIR__ . '/app/');   // rewrite only your application paths
AstRewriter::exclude(__DIR__ . '/generated/'); // and skip these even inside them
```

## The compile-order rule

Only code compiled **after** the hook is installed gets the rewrite. Composer's
`files` autoload installs it before your application code compiles, which covers the
common case; under opcache remember that op_arrays compiled before the hook existed
(or before a gate change) are served as-is until invalidated — use `opcache.preload`
or invalidate after deploys.

## Requirements

- PHP 8.4 or 8.5 (NTS), with the minor and the z-engine line moving in lockstep —
  Composer resolves `8.4.x-dev || 8.5.x-dev` to the branch matching your PHP
- `ffi.enable=1` (cannot be enabled at runtime)
- `opcache.jit=off` — the JIT rewrites the very engine internals z-engine hooks

Because z-engine ships as development branches, your root `composer.json` needs the
same stability pair this package uses:

```json
{
    "require": {
        "lisachenko/scalar-objects": "dev-master"
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

## Testing

```bash
composer test       # PHPUnit 12 driving .phpt files in tests/Functional/
composer phpstan    # static analysis, level max
composer cs:check   # coding standards, PER-CS2.0
```

For a one-off run without touching `php.ini`:

```bash
php -d ffi.enable=1 -d opcache.jit=off vendor/bin/phpunit
```

## License

MIT
