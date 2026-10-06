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

require_once 'vendor/smarty/smarty/libs/plugins/modifier.date_format.php';

/**
 * Modified date_format function to handle dates that are not in the ISO format
 * @param string $string The date string to format
 * @param string $format The format to use for the date
 * @param string $default_date The default date to use if the string is empty
 * @param string $formatter The formatter to use (auto, db, user)
 * @return string The formatted date string
 */
function smarty_modifier_sugar_date_format($string, $format = null, $default_date = '', $formatter = 'auto')
{
    global $timedate, $current_user;

    if (!strtotime($string)) {
        // If the string is not in a valid date format, try to parse it
        $userDateFormat = $timedate->get_date_format($current_user);
        $date = DateTime::createFromFormat($userDateFormat, $string);
        if ($date instanceof DateTime) {
            $string = $timedate->asIso($date, $current_user);
        }
    }

    // Delegate to base modifier
    return smarty_modifier_date_format($string, $format, $default_date, $formatter);
}
