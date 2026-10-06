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

namespace Sugarcrm\Sugarcrm\InboundEmail\Polyfill;

/**
 * Tokenizes IMAP search criteria strings, respecting quoted strings and dates.
 *
 * Handles complex queries like:
 * - SUBJECT "bounce notification"
 * - SINCE "1-Jan-2024" BEFORE "31-Dec-2024"
 * - OR FROM "user1@example.com" FROM "user2@example.com"
 */
class SearchCriteriaTokenizer
{
    /**
     * Tokenize IMAP search criteria string.
     *
     * @param string $criteria Raw search criteria
     * @return array Tokenized array preserving quoted strings
     */
    public static function tokenize(string $criteria): array
    {
        $criteria = trim($criteria);

        if (empty($criteria)) {
            return [];
        }

        $tokens = [];
        $length = strlen($criteria);
        $i = 0;

        while ($i < $length) {
            // Skip whitespace
            while ($i < $length && ctype_space($criteria[$i])) {
                $i++;
            }

            if ($i >= $length) {
                break;
            }

            // Check for quoted string
            if ($criteria[$i] === '"') {
                $token = self::extractQuotedString($criteria, $i);
                $tokens[] = $token;
            } else {
                // Extract unquoted token
                $token = self::extractUnquotedToken($criteria, $i);
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    /**
     * Extract a quoted string from criteria.
     *
     * @param string $criteria The criteria string
     * @param int &$position Current position (will be updated)
     * @return string Extracted string without quotes
     */
    private static function extractQuotedString(string $criteria, int &$position): string
    {
        $start = $position;
        $position++; // Skip opening quote

        $result = '';
        $length = strlen($criteria);

        while ($position < $length) {
            $char = $criteria[$position];

            // Handle escaped quotes
            if ($char === '\\' && $position + 1 < $length && $criteria[$position + 1] === '"') {
                $result .= '"';
                $position += 2;
                continue;
            }

            // End of quoted string
            if ($char === '"') {
                $position++; // Skip closing quote
                break;
            }

            $result .= $char;
            $position++;
        }

        return $result;
    }

    /**
     * Extract an unquoted token from criteria.
     *
     * @param string $criteria The criteria string
     * @param int &$position Current position (will be updated)
     * @return string Extracted token
     */
    private static function extractUnquotedToken(string $criteria, int &$position): string
    {
        $start = $position;
        $length = strlen($criteria);

        while ($position < $length && !ctype_space($criteria[$position])) {
            $position++;
        }

        return substr($criteria, $start, $position - $start);
    }
}
