# 🎁 Scalar Objects

### Methods on strings, ints, floats, bools and arrays. In pure PHP.

```php
"hello"->length();                          // 5
"  hello  "->trim()->upper();               // "HELLO"
(42)->toFloat();                            // 42.0
[1, 2, 3]->map(fn (int $x): int => $x * 2); // [2, 4, 6]
[1, 2, 3, 4]->filter(fn ($x) => $x % 2 === 0)->sum(); // 6
"hello"->length(...);                       // even first-class callables work
```

For over a decade, "methods on scalars" has been one of PHP's most-wished-for
features. [nikic/scalar_objects](https://github.com/nikic/scalar_objects) proved it
was possible — with a C extension you had to compile, ship, and rebuild for every
PHP version. This package delivers the same idea as a **`composer require`**:
no C toolchain, no custom PHP build, no opcode handlers. Just PHP convincing
its own compiler to do the work.

## ✨ Why this approach is interesting

The trick is *when* the magic happens. Instead of intercepting every method call at
runtime, this package teaches the **compiler** a new trick, once per file, through
[z-engine](https://github.com/lisachenko/z-engine) — the library that opens the
Zend Engine to userland PHP via FFI. At compile time, a
[`zend_ast_process` hook](https://github.com/lisachenko/z-engine#abstract-syntax-tree)
rewrites every method-call node in the syntax tree:

```
expr->m(args)     ==>   \Lisachenko\ScalarObjects\box(expr)->m(args)
expr?->m(args)    ==>   \Lisachenko\ScalarObjects\boxNullsafe(expr)?->m(args)
```

`box()` is ordinary PHP: objects pass through untouched, scalars get wrapped in the
handler registered for their type, and dispatch on the wrapper is plain VM dispatch
with warm inline caches. That one design decision buys properties a runtime hook
could never offer:

| | C extension / opcode hooks | **AST rewrite (this package)** |
|---|---|---|
| Install | compile against each PHP build | `composer require` |
| Call-time cost | C handler on **every** `INIT_METHOD_CALL` | one PHP function call (~0.25 µs), zero FFI |
| Undefined method on `"str"` | engine-level error paths | **catchable** `BadMethodCallException` |
| Call on an unregistered type | engine-level error paths | the VM's own **catchable** `Error` |
| Custom handlers | rebuild the extension | `Registry::register()` at runtime |
| Coverage | per-opcode special cases | literals, variables, chains, `?->`, dynamic names, spread, named args, `(...)` — uniformly, because it is just syntax |

All the FFI work happens once per file at compile time; the code your application
actually executes is code the PHP VM was built to run fast.

Curious how deep the engine access goes? z-engine's
[tour of the API](https://github.com/lisachenko/z-engine#a-tour-of-the-api) covers
the AST hooks used here, plus operator overloading, runtime class specialization,
and more — this package is one worked example of what that toolbox makes possible.

## 📜 The contract

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

## 🧰 Registering handlers

Defaults ship for `string`, `int`, `float`, `bool` and `array` and are registered by
the bootstrap. `null` is deliberately left out — opt in if you want it:

```php
use Lisachenko\ScalarObjects\Handler\NullHandler;
use Lisachenko\ScalarObjects\Registry;
use Lisachenko\ScalarObjects\ScalarType;

Registry::register(ScalarType::Null, NullHandler::class);

(null)->coalesce('fallback'); // "fallback"
```

The type system is **yours to shape**: custom handlers extend
`Lisachenko\ScalarObjects\TypeHandler` and replace the shipped ones at call time —
registration is ordinary runtime code, no recompilation involved:

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

## 🚪 Gating the rewrite

Every method call in a rewritten file — object receivers included — pays the small
`box()` detour. The rewriter never touches `vendor/` or its own sources; for
production keep the gate tight:

```php
use Lisachenko\ScalarObjects\AstRewriter;

AstRewriter::includeOnly(__DIR__ . '/app/');   // rewrite only your application paths
AstRewriter::exclude(__DIR__ . '/generated/'); // and skip these even inside them
```

## ⏱ The compile-order rule

Only code compiled **after** the hook is installed gets the rewrite. Composer's
`files` autoload installs it before your application code compiles, which covers the
common case; under opcache remember that op_arrays compiled before the hook existed
(or before a gate change) are served as-is until invalidated — use `opcache.preload`
or invalidate after deploys.

## 📋 Requirements

- PHP 8.4 or 8.5 (NTS), with the minor and the z-engine line moving in lockstep —
  Composer resolves `8.4.x-dev || 8.5.x-dev` to the branch matching your PHP
- `ffi.enable=1` (cannot be enabled at runtime)
- `opcache.jit=off` — the JIT rewrites the very engine internals z-engine hooks

Because z-engine ships as development branches, your root `composer.json` needs the
same stability pair this package uses:

```json
{
    "require": {
        "lisachenko/scalar-objects": "dev-main"
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

## 🧪 Testing

```bash
composer test       # PHPUnit 12 driving .phpt files in tests/Functional/
composer phpstan    # static analysis, level max
composer cs:check   # coding standards, PER-CS2.0
```

For a one-off run without touching `php.ini`:

```bash
php -d ffi.enable=1 -d opcache.jit=off vendor/bin/phpunit
```

## 📚 See also

- [z-engine](https://github.com/lisachenko/z-engine) — the FFI bridge into the Zend
  Engine this package is built on, including the
  [AST access & rewriting API](https://github.com/lisachenko/z-engine#abstract-syntax-tree)
- [nikic/scalar_objects](https://github.com/nikic/scalar_objects) — the C extension
  that pioneered the idea and whose semantics (immutability, per-type handlers) this
  package mirrors
- [native-php-matrix](https://github.com/lisachenko/native-php-matrix) — a sibling
  z-engine consumer: real operator overloading for a userland `Matrix` class

## License

MIT
