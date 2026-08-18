# Working on scalar-objects

This library makes method calls on PHP scalars possible — `"hello"->length()`,
`(42)->toFloat()`, `[1,2,3]->map(...)` — with user-registerable handler classes per
type. It does that with a **compile-time AST rewrite** installed through
[z-engine](https://github.com/lisachenko/z-engine)'s `zend_ast_process` hook: in
every gated compilation unit, `expr->m(args)` becomes
`\Lisachenko\ScalarObjects\box(expr)->m(args)` (and `expr?->m(...)` becomes
`\Lisachenko\ScalarObjects\boxNullsafe(expr)?->m(...)`). `box()` is plain PHP — objects pass
through, scalars get wrapped in their registered handler, unregistered types pass
through so the VM raises its native catchable Error. There is **zero FFI on the call
path**; all engine work happens once per file at compile time in `AstRewriter`.

## The one rule that is non-negotiable: PHP minor and z-engine line move together

`composer.json` requires **`php: ^8.4`**, and PHP 8.4 and 8.5 are supported **in
parallel** — each minor riding its own z-engine line. z-engine reads engine
structures by byte offset, and those offsets change on every PHP minor release.
Running against the wrong minor does not throw a nice exception; it reads and writes
the wrong memory.

That is why z-engine is required as **`~8.4.2 || ~8.5.0`**: Composer resolves the
stable release line matching the running PHP (`8.4.x` on PHP 8.4, `8.5.x` on PHP 8.5 —
each tag declares its own `~8.4.0`/`~8.5.0` platform requirement, so only one line can
ever satisfy a given runtime). `ZEngine\Core::init()` (called from `bootstrap.php`)
enforces the exact match and aborts with a clear message. **Never "fix" an
initialization failure by loosening the constraints past the minors z-engine has
definitions for, skipping `Core::init()`, or defeating the guard.** If the
environment's PHP does not satisfy `^8.4`, the environment is wrong — say so and
stop.

## The rewriter's engine contracts (hard-won, do not relearn them the crashing way)

- **Never touch z-engine internals or engine structs from this package — it is a
  rule.** No reflection into z-engine private properties, no `FFI\CData`, no
  `ZEngine\Generated\*` stubs, no reading raw struct fields. The consumer surface is
  the `AstProcessHook` object the callback receives, the public wrappers (`Node`,
  `ValueNode`, `ListNode`, `ReflectionValue`) and the public `Compiler` methods
  reached through `Core::$compiler`. When something is missing behind that line, the
  fix is a named public method **upstream in z-engine**, not a reach-through here —
  that is exactly how `AstProcessHook::getFileName()` and
  `Compiler::processInCompilationMode()` came to exist.
- **Exceptions raised while `CG(in_compilation)` is set become fatal errors before
  any `catch` runs.** This includes exceptions thrown *and caught inside* library
  code: z-engine's `Core::cast()` uses a thrown-and-caught `FFI\Exception` as its
  array-decay probe, so nearly every z-engine AST accessor would fatal if called
  naively inside the hook. `AstRewriter::process()` therefore runs the tree walk
  through `Core::$compiler->processInCompilationMode(false, \Closure)` — the bracket
  z-engine documents on the `AstProcessHook` class — which restores the previous
  mode in a `finally`. Any new code that runs inside the hook must stay inside that
  closure — and the **gate check runs before it**, which only works because
  `AstProcessHook::getFileName()` reads the compiled file name without any throwing
  code path.
- **Nothing may escape the hook as an exception** — that is an uncatchable
  "Throwing from FFI callbacks is not allowed" fatal. `process()` wraps everything
  in catch-all blocks and always chains `proceed()` when an original handler exists.
- **No autoloading inside the hook.** Triggering the autoloader from
  `zend_ast_process` recursively enters the compiler. `install()` preloads every
  class the callback touches; keep that list in sync when adding dependencies.
- **The injected function-name string must be interned.** `wrapReceiver()` passes a
  string literal to `ValueNode`, so the zval placed in the AST needs no refcount
  handling when `zend_ast_destroy` runs. Do not replace it with a computed string.
- **`$this` receivers are skipped** — never scalar, and skipping avoids the detour.
- **Known bounded leak:** dereferencing CData (e.g. `zend_ast **` elements) *inside
  an FFI callback* leaks ~14 bytes per dereference — an ext-ffi behaviour, verified
  absent outside callbacks. Cost is per compilation unit (once per file per
  process), roughly 0.5–1.5 KB for a small file. The call-time path allocates
  nothing extra (verified: 100k calls, no growth). Do not "fix" this by caching
  nodes across compilations — the AST arena dies with the compilation.
- **Compile-order coverage:** only op_arrays compiled after `install()` are
  rewritten. Opcache-warm scripts are immune until invalidated. That is a documented
  property, not a bug.

## Running tests

```bash
composer test
```

The suite is PHPUnit 12 driving `.phpt` files in `tests/Functional/`. Three INI
settings must hold in **both** the parent PHPUnit process and every `.phpt` child
process it spawns:

- `ffi.enable=1` — FFI cannot be turned on at runtime.
- `opcache.jit=off` — the JIT rewrites the very executor internals z-engine hooks.
- `error_reporting=E_ALL & ~E_DEPRECATED` — a guard against dependency deprecations
  leaking into captured output; PHPUnit's `.phpt` runner forces `display_errors=1`,
  so without the suppression a dependency deprecation would be prepended to every
  test's captured output and each `--EXPECT--` block would fail on noise that has
  nothing to do with this library. The stable z-engine releases (`~8.4.2 || ~8.5.0`)
  raise none — the suite passes with `error_reporting=E_ALL` forced on both minors —
  so the line is belt-and-braces rather than a requirement.

CI supplies the FFI and JIT pair as `ini-values` on the PHP setup step, and **every
`.phpt` file carries its own `--INI--` section** — all three lines — so the child
processes inherit nothing by luck.

For a local one-off run without touching `php.ini`:

```bash
php -d ffi.enable=1 -d opcache.jit=off vendor/bin/phpunit
```

Single test:

```bash
php -d ffi.enable=1 -d opcache.jit=off vendor/bin/phpunit tests/Functional/testCanCallMethodOnStringLiteral.phpt
```

> **A segfault or bus error is not a normal test failure.** It means a hook or an
> engine structure is being used incorrectly — an engine-level bug. Do not retry
> the run hoping it passes, do not mark the test skipped: report the crash with the
> exact command, PHP version, and the test that triggered it.

## Quality gates (all enforced in CI)

```bash
composer phpstan     # PHPStan at level max
composer cs:check    # coding standards, PER-CS2.0
composer cs:fix      # apply the fixes
```

PHPStan reads z-engine's generated struct stubs (`scanFiles` in
`phpstan.dist.neon`) so raw-struct reads in `AstRewriter` stay typed. Keep the
handler generics honest: `TypeHandler` is `@template-covariant T`, each shipped
handler pins its `T` (`@extends TypeHandler<string>` etc.), and handler methods
return **raw scalars, never wrappers** — the chain works because the outer call site
is rewritten too, not because anything returns `$this`.

## Anatomy of a `.phpt` test — the fixture discipline

Each file covers exactly one behaviour and is named `test<WhatItDoes>.phpt`. The
non-obvious part: **a `.phpt` `--FILE--` body is compiled BEFORE the autoloader
runs**, so scalar-method-call syntax placed there would never be rewritten. All code
that needs the rewrite lives in `tests/fixtures/*.php` and is `include`d after the
autoloader:

```
--TEST--
A method can be called on a string literal
--INI--
ffi.enable=1
opcache.jit=off
error_reporting=E_ALL & ~E_DEPRECATED
--FILE--
<?php
declare(strict_types=1);

include __DIR__ . '/../../vendor/autoload.php';

var_dump(include __DIR__ . '/../fixtures/stringLength.php');
?>
--EXPECT--
int(5)
```

Rules for a new test:

- `--INI--` is **mandatory, all three lines**.
- Scalar-call syntax goes in a **fixture returning a value**; the test body includes
  the autoloader first, then the fixture, and `var_dump`s/`echo`s the result.
- Fixtures must not be referenced by more than one test that changes global state
  (each file compiles once per process — a gate change after compilation does not
  un-rewrite it).
- PHPStan does not analyse `tests/` on purpose: fixtures contain method calls on
  scalars, which are type errors to any static analyser.
- Prefer `--EXPECT--` (exact match); error-message tests `echo` the caught message
  instead of `var_dump`ing it so no string lengths need maintaining.
- One behaviour per file. Failure cases get their own files.

## Repository map

```
src/AstRewriter.php       the compile-time rewrite: hook install, per-file gate,
                          tree walk, box() call injection — the only FFI in the repo
src/functions.php         box() / boxNullsafe(), the call-time hot path (plain PHP)
src/Registry.php          ScalarType => handler class map, register/unregister/lookup
src/ScalarType.php        enum of boxable types, backed by get_debug_type() strings
src/TypeHandler.php       abstract handler base: final ctor + catchable __call
src/Handler/*.php         shipped defaults (String, Int, Float, Bool, Array, Null)
bootstrap.php             Core::init() probe, default registrations, AstRewriter::install();
                          runs automatically via Composer's "files" autoload — order matters
tests/Functional/*.phpt   the functional suite, one behaviour per file
tests/fixtures/*.php      the code that actually uses scalar-call syntax (see above)
phpunit.xml.dist          PHPUnit 12 config (suite points at tests/, suffix .phpt)
phpstan.dist.neon         static analysis config, level max + z-engine struct stubs
.php-cs-fixer.dist.php    coding standards config (PER-CS2.0)
.github/workflows/ci.yml  jobs: tests, static-analysis, coding-standards — PHP 8.4 and 8.5
```

## Semantics that tests pin down (keep them true)

- `NullHandler` ships but is **not registered by default**: `->` on null keeps the
  native catchable Error unless the application opts in. `?->` on null
  short-circuits before any handler lookup either way (`boxNullsafe`).
- Undefined method on a registered type → `BadMethodCallException` from
  `TypeHandler::__call` (ordinary VM code, catchable).
- Call on an unregistered type → the value passes through `box()` and the VM raises
  its native catchable `Error`.
- Dynamic names, spread, named arguments, first-class callables: the rewrite touches
  only the receiver child, everything else passes through untouched.
- Object receivers in gated files go through `box()` and come out identical.

## Conventions

[Conventional Commits](https://www.conventionalcommits.org/):

```
feat(rewriter): skip receivers that are property hooks on $this
feat(handlers): add pad()/padLeft() to StringHandler
fix(bootstrap): register defaults before installing the hook
test(tests): cover gate reconfiguration after compilation
ci: run the suite on PHP 8.4 and 8.5 with ffi.enable=1
docs: document the opcache invalidation caveat
```

Scopes in use: `rewriter`, `handlers`, `registry`, `bootstrap`, `tests`, `ci`,
`docs`.

Code style is **PER-CS2.0**, applied by php-cs-fixer. Run `composer cs:fix` before
proposing a change rather than hand-formatting.

## Dependency policy

- `lisachenko/z-engine` is required as **`~8.4.2 || ~8.5.0`** — one **stable** release
  line per supported PHP minor, resolved by Composer to match the running PHP. The
  tilde is deliberate: it admits patch releases within a line (`8.4.3`, `8.5.1`) but
  never the next minor line, which would be built for a PHP this package does not
  claim to support.
- The root `composer.json` no longer carries `"minimum-stability": "dev"` /
  `"prefer-stable": true` — nothing in `require` is a development branch any more.
- PHP stays at `^8.4`, in lockstep with the set of z-engine lines this package
  tracks: a new PHP minor is added here only together with the z-engine line built
  for it, and never one without the other.
