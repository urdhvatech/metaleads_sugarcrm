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

class ReportDatetimeFormatter extends ReportDateFormatter
{
    public $type = 'datetime';

    /**
     * {@inheritDoc}
     */
    public function format($value)
    {
        global $timedate;

        $formattedValue = $this->getDBDatetimeFromUserInput($value, $this->type);
        $formattedDisplayValue = $timedate->to_display_date_time($formattedValue, true, false);

        return $formattedDisplayValue;
    }
}
