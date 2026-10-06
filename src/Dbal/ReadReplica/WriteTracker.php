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

namespace Sugarcrm\Sugarcrm\Dbal\ReadReplica;

/**
 * Tracks database write operations within request lifecycle to prevent
 * reading from replica after writes in the same request
 */
class WriteTracker
{
    private static bool $hasWriteOccurred = false;

    /**
     * Mark that a write has occurred in this request
     */
    public static function trackWrite(): void
    {
        self::$hasWriteOccurred = true;
    }

    /**
     * Check if it's safe to use read replica
     *
     * @return bool True if safe to use replica, False if must use primary
     */
    public static function isSafeForReplica(): bool
    {
        global $sugar_config;

        // Feature disabled globally
        if (($sugar_config['use_readonly_for_selects'] ?? true) === false) {
            return false;
        }

        // If any write occurred in this request, use primary
        if (self::$hasWriteOccurred) {
            return false;
        }

        return true;
    }

    /**
     * Reset state (for testing purposes)
     */
    public static function reset(): void
    {
        self::$hasWriteOccurred = false;
    }
}
