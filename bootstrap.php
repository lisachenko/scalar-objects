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

use Lisachenko\ScalarObjects\AstRewriter;
use Lisachenko\ScalarObjects\Handler\ArrayHandler;
use Lisachenko\ScalarObjects\Handler\BoolHandler;
use Lisachenko\ScalarObjects\Handler\FloatHandler;
use Lisachenko\ScalarObjects\Handler\IntHandler;
use Lisachenko\ScalarObjects\Handler\StringHandler;
use Lisachenko\ScalarObjects\Registry;
use Lisachenko\ScalarObjects\ScalarType;
use ZEngine\Core;

// We can not be sure that the Z-Engine library was already initialized by another package, so probe the engine state
// instead of the class existence: Core::$executor is a typed static property that is assigned only by Core::init(),
// therefore an uninitialized property means that nobody has booted the engine yet.
if (!isset(Core::$executor)) {
    Core::init();
}

// Shipped defaults. NullHandler is deliberately NOT registered: a `->` call on null keeps
// its native catchable Error unless the application opts in.
Registry::register(ScalarType::String, StringHandler::class);
Registry::register(ScalarType::Int, IntHandler::class);
Registry::register(ScalarType::Float, FloatHandler::class);
Registry::register(ScalarType::Bool, BoolHandler::class);
Registry::register(ScalarType::ArrayType, ArrayHandler::class);

// From here on every newly compiled file outside vendor/ gets the box() rewrite.
// Code compiled earlier (including opcache-warm op_arrays) is never touched.
AstRewriter::install();
