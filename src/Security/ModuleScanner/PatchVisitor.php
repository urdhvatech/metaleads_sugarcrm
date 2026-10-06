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

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class PatchVisitor extends NodeVisitorAbstract
{
    private bool $patched = false;

    public function isPatched(): bool
    {
        return $this->patched;
    }

    /**
     * @param Node $node
     * @return Node|void|null
     * @see \PhpParser\NodeVisitor::leaveNode()
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Expr\FuncCall) {
            $funcName = $node->name instanceof Node\Name ? $node->name->toString() : $node->name;
            if ($funcName === 'unserialize') {
                $node->name = new Node\Name('\Sugarcrm\Sugarcrm\Security\MLP\Alternatives\unserialize');
                $this->patched = true;
                return $node;
            }
        }
    }
}
