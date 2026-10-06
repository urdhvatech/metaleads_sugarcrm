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
declare(strict_types=1);

namespace Sugarcrm\Sugarcrm\Reports\Utils;

/**
 * Utility class for generating unique identifiers in reports.
 * Handles duplicate group values by appending counters like "(1)", "(2)", etc.
 */
class UniqueIdentifierGenerator
{
    /**
     * Generates a unique identifier for a group value to handle duplicates.
     * When the same base identifier appears multiple times within the same scope,
     * it appends a counter suffix like "(1)", "(2)", etc.
     *
     * @param string $baseIdentifier The base identifier value
     * @param int $depth The current depth level in the tree
     * @param array $pathStack The parent path for scope isolation
     * @param array &$identifierCounters Reference to the counters array
     * @return string The unique identifier with suffix if needed
     */
    public static function generate(
        string $baseIdentifier,
        int $depth,
        array $pathStack,
        array &$identifierCounters
    ): string {
        // Create a unique scope key based on depth and parent path to avoid cross-contamination
        $scopeKey = $depth . '::' . implode('>', $pathStack);
        $counterKey = $scopeKey . '::' . $baseIdentifier;

        // Ensure uniqueness within this level
        if (!isset($identifierCounters[$counterKey])) {
            $identifierCounters[$counterKey] = 0;
            return $baseIdentifier;
        }

        $identifierCounters[$counterKey]++;
        return "{$baseIdentifier} ({$identifierCounters[$counterKey]})";
    }
}

