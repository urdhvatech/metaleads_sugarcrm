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

namespace Sugarcrm\Sugarcrm\DocumentMerge;

/**
 * Utility class for document merge operations
 */
class DocumentMergeUtils
{
    /**
     * Sanitize the filename to make sure it is valid
     *
     * @param string $fileName
     * @return string
     */
    public static function sanitizeFileName(string $fileName): string
    {
        // Remove unwanted characters if filename starts with them
        // Check longer patterns first to avoid partial matches
        $unwantedStartChars = ['..', '~', '$', '`', ';', '.'];
        $removed = true;
        while ($removed) {
            $removed = false;
            foreach ($unwantedStartChars as $char) {
                if (strpos($fileName, $char) === 0) {
                    $fileName = substr($fileName, strlen($char));
                    $removed = true;
                    break;
                }
            }
        }

        // Replace invalid characters for cloud storage and security
        $invalidChars = ['/', '\\', ':', '*', '?', '"', '<', '>', '|', '#', '%'];
        $fileName = str_replace($invalidChars, '-', $fileName);

        // Remove multiple consecutive dashes
        $fileName = preg_replace('/-+/', '-', $fileName);

        // Trim dashes and whitespace
        $fileName = trim($fileName, '- ');

        // Additional safety check - ensure we have a valid filename
        if (empty($fileName)) {
            return 'document';
        }

        // Limit length to prevent filesystem issues
        $maxNumberOfCharacters = 239;
        if (strlen($fileName) > $maxNumberOfCharacters) {
            $fileName = substr($fileName, 0, $maxNumberOfCharacters - 1);
            $fileName = trim($fileName, '- ');
        }

        return $fileName;
    }
}
