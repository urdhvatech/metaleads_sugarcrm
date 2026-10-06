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

namespace Sugarcrm\Sugarcrm\Reports\Formatters;

class ReportDateFormatter extends ReportFormatter
{
    public $type = 'date';

    /**
     * Get db date time from user input
     *
     * @param string $value
     * @param string $type
     * @param bool $add
     *
     * @return string
     */
    public function getDBDatetimeFromUserInput($value, $add = false)
    {
        global $current_user, $timedate;

        if (empty($value)) {
            return $value;
        }

        $gmtTimezone = new \DateTimeZone('GMT');

        // Check if $value is already in a known format
        $knownDateTimeFormat = \SugarDateTime::createFromFormat('Y-m-d H:i:s', $value, $gmtTimezone);
        if (!$knownDateTimeFormat) {
            // Convert $value to a standard format compatible with \SugarDateTime
            $dateTimeFormat = $timedate->get_date_time_format();
            $value = \SugarDateTime::createFromFormat($dateTimeFormat, $value, $gmtTimezone)->format('Y-m-d H:i:s');
        }

        $targetTzOffset = $timedate->getUserUTCOffset($current_user, new \DateTime($value));

        $targetDateTime = \SugarDateTime::createFromFormat('Y-m-d H:i:s', $value, $gmtTimezone);
        $dateInterval = new \DateInterval('PT' . abs($targetTzOffset) . 'M');

        if ($targetTzOffset < 0) {
            $dateInterval->invert = 1;
        }

        if ($add) {
            $targetDateTime->add($dateInterval);
        } else {
            $targetDateTime->sub($dateInterval);
        }

        $targetDateTime = $timedate->asDbType($targetDateTime, $this->type);

        return $targetDateTime;
    }
}
