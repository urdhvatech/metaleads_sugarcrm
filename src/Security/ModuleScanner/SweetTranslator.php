<?php

declare(strict_types=1);
/*
 * Your installation or use of this SugarCRM file is subject to the applicable
 * terms available at
 * http://support.sugarcrm.com/Resources/Master_Subscription_Agreements/.
 * If you do not agree to all of the applicable terms or do not have the
 * authority to bind the entity as an authorized representative, then do not
 * install or use this SugarCRM file.
 *
 * Copyright (C) SugarCRM Inc. All rights reserved.
 */

namespace Sugarcrm\Sugarcrm\Security\ModuleScanner;

use PhpParser\Lexer\Emulative;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

final class SweetTranslator
{
    /**
     * @param string $code
     * @return string|null
     */
    public static function translate(string $code): ?string
    {
        try {
            [$oldStmts, $tokens] = self::parse($code);
        } catch (\Throwable $error) {
            return null;
        }
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver(null, ['replaceNodes' => false]));
        $traverser->addVisitor(new TranslateVisitor());
        $newStmts = $traverser->traverse($oldStmts);
        $prettyPrinter = new Standard(['shortArraySyntax' => false]);
        return $prettyPrinter->printFormatPreserving($newStmts, $oldStmts, $tokens);
    }

    /**
     * @param string $code
     * @return string|null
     */
    public static function patch(string $code): ?string
    {
        try {
            [$oldStmts, $tokens] = self::parse($code);
        } catch (\Throwable $error) {
            return null;
        }
        $traverser = new NodeTraverser();
        $patchVisitor = new PatchVisitor();
        $traverser->addVisitor($patchVisitor);
        $newStmts = $traverser->traverse($oldStmts);
        if ($patchVisitor->isPatched()) {// Avoid pretty printing if no changes were made
            $prettyPrinter = new Standard();
            return $prettyPrinter->printFormatPreserving($newStmts, $oldStmts, $tokens);
        }
        return $code;
    }

    /**
     * Parse the given code and return the statements and tokens.
     * @param string $code
     * @return array
     */
    protected static function parse(string $code): array
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new CloningVisitor());
        $origStmts = $parser->parse($code);
        $tokens = $parser->getTokens();
        $oldStmts = $traverser->traverse($origStmts);
        return [$oldStmts, $tokens];
    }
}
