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

use ZEngine\AbstractSyntaxTree\ListNode;
use ZEngine\AbstractSyntaxTree\Node;
use ZEngine\AbstractSyntaxTree\NodeFactory;
use ZEngine\AbstractSyntaxTree\NodeInterface;
use ZEngine\AbstractSyntaxTree\NodeKind;
use ZEngine\AbstractSyntaxTree\ValueNode;
use ZEngine\Core;
use ZEngine\Reflection\ReflectionValue;
use ZEngine\System\Hook\AstProcessHook;

/**
 * Compile-time rewriter that makes method calls on scalars possible.
 *
 * In every gated compilation unit each method-call node is rewritten:
 *
 *     expr->m(args)     ==>   \Lisachenko\ScalarObjects\box(expr)->m(args)
 *     expr?->m(args)    ==>   \Lisachenko\ScalarObjects\boxNullsafe(expr)?->m(args)
 *
 * Method-name and argument children are untouched, so dynamic names, spread, named
 * arguments and first-class callables pass through unchanged. All FFI work happens
 * here, once per file at compile time - the call-time path is plain PHP.
 *
 * Compile-order rule: only code compiled AFTER install() is rewritten. Install from
 * bootstrap (composer "files" autoload) or opcache.preload, and remember that opcache
 * may serve op_arrays compiled before the hook existed until they are invalidated.
 */
final class AstRewriter
{
    /**
     * ZEND_NAME_FQ: the injected function name is fully qualified, no import resolution
     */
    private const int ZEND_NAME_FQ = 0;

    private static ?AstProcessHook $hook = null;

    /**
     * Reentrancy latch: a nested compilation started while the tree walk is running
     * (autoload triggered from another zend_ast_process consumer, for example) must
     * not re-enter the rewriter over engine state that is already being mutated.
     */
    private static bool $rewriting = false;

    /**
     * Path prefixes that are never rewritten (this package itself plus anything vendored)
     *
     * @var list<string>
     */
    private static array $excludedPrefixes = [];

    /**
     * When non-empty, ONLY files under these path prefixes are rewritten
     *
     * @var list<string>
     */
    private static array $includedPrefixes = [];

    private function __construct() {}

    /**
     * Installs the zend_ast_process hook (idempotent)
     */
    public static function install(): void
    {
        if (self::$hook !== null) {
            return;
        }

        // Everything the compile-time callback touches must already be loaded: triggering
        // the autoloader from inside zend_ast_process would recursively enter the compiler.
        \class_exists(NodeKind::class);
        \class_exists(NodeFactory::class);
        \class_exists(Node::class);
        \class_exists(ValueNode::class);
        \class_exists(ListNode::class);
        \class_exists(ReflectionValue::class);

        self::$excludedPrefixes[] = __DIR__ . \DIRECTORY_SEPARATOR;

        self::$hook = Core::setASTProcessHandler(self::process(...));
    }

    /**
     * Restores the previous zend_ast_process handler
     */
    public static function uninstall(): void
    {
        if (self::$hook === null) {
            return;
        }

        self::$hook->uninstall();
        self::$hook = null;
    }

    public static function isInstalled(): bool
    {
        return self::$hook !== null;
    }

    /**
     * Restricts rewriting to files under the given path prefixes.
     *
     * Calling with no arguments clears the restriction. Keeping the gate tight (your
     * application paths only) is the recommended production setup: every method call
     * in a gated file, object receivers included, pays the small box() detour.
     */
    public static function includeOnly(string ...$pathPrefixes): void
    {
        self::$includedPrefixes = \array_values($pathPrefixes);
    }

    /**
     * Adds path prefixes that must never be rewritten
     */
    public static function exclude(string ...$pathPrefixes): void
    {
        foreach ($pathPrefixes as $pathPrefix) {
            self::$excludedPrefixes[] = $pathPrefix;
        }
    }

    /**
     * The zend_ast_process callback. Runs inside an FFI callback: nothing may escape as
     * an exception (that would be an uncatchable fatal), and the previous handler must
     * always run so other AST consumers keep working.
     *
     * While CG(in_compilation) is set, the engine promotes every internally-raised
     * exception straight to a fatal error BEFORE any catch block runs - and z-engine's
     * Core::cast() uses a thrown-and-caught FFI\Exception as its array-decay probe, so
     * nearly every AST accessor would fatal here. The tree walk therefore runs through
     * Compiler::processInCompilationMode(false, ...), the bracket z-engine documents for
     * exactly this (see the AstProcessHook class docblock); it restores the previous mode
     * in a finally. The walk itself is pure data manipulation and never re-enters a
     * compiler code path that reads the flag. Both the bracket and the hook's
     * getFileName() are z-engine public API - this class touches no engine struct, by
     * rule.
     */
    private static function process(AstProcessHook $hook): void
    {
        try {
            if (!self::$rewriting && self::shouldRewrite($hook->getFileName())) {
                self::$rewriting = true;
                try {
                    Core::$compiler->processInCompilationMode(false, static fn() => self::rewriteTree($hook->getAST()));
                } finally {
                    self::$rewriting = false;
                }
            }
        } catch (\Throwable) {
            // Swallowed on purpose: an exception crossing the FFI callback boundary is an
            // uncatchable fatal. The unit compiles unrewritten, which degrades to the
            // native "call on scalar" Error at runtime instead of killing the process.
        }

        try {
            if ($hook->hasOriginalHandler()) {
                $hook->proceed();
            }
        } catch (\Throwable) {
            // Same discipline for whatever the chained handler does
        }
    }

    /**
     * Per-file gate, checked once per compilation unit
     */
    private static function shouldRewrite(string $fileName): bool
    {
        if ($fileName === '') {
            return false;
        }
        foreach (self::$excludedPrefixes as $excludedPrefix) {
            if (\str_starts_with($fileName, $excludedPrefix)) {
                return false;
            }
        }
        $vendorSegment = \DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR;
        if (\str_contains($fileName, $vendorSegment)) {
            return false;
        }
        if (self::$includedPrefixes !== []) {
            foreach (self::$includedPrefixes as $includedPrefix) {
                if (\str_starts_with($fileName, $includedPrefix)) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Post-order walk: children first, so a chained receiver is already rewritten when
     * the outer call wraps it - $s->upper()->trim() becomes box(box($s)->upper())->trim()
     */
    private static function rewriteTree(NodeInterface $node): void
    {
        $childrenCount = $node->getChildrenCount();
        for ($index = 0; $index < $childrenCount; $index++) {
            $child = $node->getChild($index);
            if ($child !== null) {
                self::rewriteTree($child);
            }
        }

        $kind = $node->getKind();
        if ($kind === NodeKind::AST_METHOD_CALL || $kind === NodeKind::AST_NULLSAFE_METHOD_CALL) {
            self::wrapReceiver($node, $kind === NodeKind::AST_NULLSAFE_METHOD_CALL);
        }
    }

    /**
     * Replaces the receiver child of a method-call node with box(receiver)
     */
    private static function wrapReceiver(NodeInterface $methodCall, bool $nullsafe): void
    {
        $receiver = $methodCall->getChild(0);
        if ($receiver === null || self::isThisVariable($receiver)) {
            // $this is never scalar, skip the detour entirely
            return;
        }

        // The name literal is interned, so the AST-owned zval needs no refcount handling
        $boxFunction = $nullsafe ? 'Lisachenko\ScalarObjects\boxNullsafe' : 'Lisachenko\ScalarObjects\box';

        $nameNode = new ValueNode($boxFunction, self::ZEND_NAME_FQ);
        $argList  = new ListNode(NodeKind::AST_ARG_LIST);
        $argList->append($receiver);
        $boxCall = new Node(NodeKind::AST_CALL, 0, $nameNode, $argList);

        $line = $methodCall->getLine();
        $argList->setLine($line);
        $boxCall->setLine($line);

        $methodCall->replaceChild(0, $boxCall);
    }

    /**
     * Detects a plain `$this` receiver: AST_VAR whose name child is the constant 'this'
     */
    private static function isThisVariable(NodeInterface $node): bool
    {
        if ($node->getKind() !== NodeKind::AST_VAR) {
            return false;
        }
        $nameChild = $node->getChild(0);
        if (!$nameChild instanceof ValueNode) {
            return false;
        }
        $nameChild->getValue()->getNativeValue($variableName);

        return $variableName === 'this';
    }
}
