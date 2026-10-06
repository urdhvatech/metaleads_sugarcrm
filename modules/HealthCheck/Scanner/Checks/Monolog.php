<?php
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

namespace Sugarcrm\sugarcrm\modules\HealthCheck\Scanner\Checks;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;

class Monolog
{
    public function check(string $contents)
    {
        $parser = (new ParserFactory())->createForHostVersion();
        try {
            $stmts = $parser->parse($contents);
        } catch (\PhpParser\Error $error) {
            return [];
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor(new ParentConnectingVisitor());
        $upgradeVisitor = new MonologUpgradeVisitor();
        $traverser->addVisitor($upgradeVisitor);
        $traverser->traverse($stmts);

        return $upgradeVisitor->getIssues();
    }
}


class MonologUpgradeVisitor extends NodeVisitorAbstract
{
    private $issues = [];

    public function leaveNode(Node $node)
    {
        if ($node instanceof Class_) {
            $extends = $node->extends;
            $extendsMonologFormatter = $extends && preg_match('/^Monolog\\\Formatter/', $extends->toString());

            if ($extendsMonologFormatter) {
                // Iterate over the class methods
                foreach ($node->getMethods() as $method) {
                    // Check if the method is named "format"
                    if ($method->name->toString() === 'format') {
                        // Check the parameters of the "format" method
                        foreach ($method->params as $position => $param) {
                            if ($position !== 0) {
                                break;
                            }

                            $paramType = $param->type;
                            if ($paramType instanceof Name) {
                                $type = $paramType->toString();
                                // Check if the parameter type is "array" and flag it as an issue
                                if ($type === 'array') {
                                    $this->issues[] = new MonologUpgradeIssue('The format method should not have parameter of type array. It should be LogRecord type or array|LogRecord.', $param->getLine());
                                }
                            } elseif ($paramType instanceof Node\UnionType) {
                                $types = array_map(function ($type) {
                                    return $type->toString();
                                }, $paramType->types);
                                $expectedTypes = ['LogRecord', 'Monolog\LogRecord', '\Monolog\LogRecord', 'array'];
                                if (empty(array_intersect($expectedTypes, $types))) {
                                    $this->issues[] = new MonologUpgradeIssue('The format method should have parameter of type LogRecord or array|LogRecord.', $param->getLine());
                                }
                            } else {
                                $this->issues[] = new MonologUpgradeIssue('The format method should have parameter of type LogRecord or array|LogRecord.', $param->getLine());
                            }
                        }
                    }
                }
            }
        }
    }

    public function getIssues()
    {
        return $this->issues;
    }
}

class MonologUpgradeIssue
{
    private $message;
    private $line;

    public function __construct(string $message, int $line)
    {
        $this->message = $message;
        $this->line = $line;
    }

    public function __toString(): string
    {
        return $this->message . ' on line ' . $this->line;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getLine()
    {
        return $this->line;
    }
}
