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

namespace Sugarcrm\Sugarcrm\Reports;

use Sugarcrm\Sugarcrm\Reports\Formatters\ReportDatetimeFormatter;
use Sugarcrm\Sugarcrm\Reports\Formatters\ReportDatetimeComboFormatter;
use Sugarcrm\Sugarcrm\Reports\Formatters\ReportDateFormatter;
use Sugarcrm\Sugarcrm\Reports\Formatters\ReportFormatter;

class ReportFormatterFactory
{
    /*
    * Get formatter for the given type
    *
    * @param string $type
    *
    * @return ReportFormatter
    */
    public static function getFormatter($type)
    {
        switch ($type) {
            case 'datetime':
                return new ReportDatetimeFormatter();
            case 'datetimecombo':
                return new ReportDatetimeComboFormatter();
            case 'date':
                return new ReportDateFormatter();
            default:
                return new ReportFormatter();
        }
    }
}
