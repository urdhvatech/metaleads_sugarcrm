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

namespace Sugarcrm\Sugarcrm\Reports\Utils;

use BeanFactory;
use Sugarcrm\Sugarcrm\Reports\ReportFormatterFactory;

class ReportUtils
{

    /**
     * Static cache for BeanFactory::retrieveBean calls.
     *
     * @var array
     */
    protected static $beanCache = [];

    /**
     * Static cache for \TimeDate::get_date_time_format calls
     * @var null | string
     */
    protected static $dateTimeFormatCache = null;

    /**
     * Format the row data
     *
     * @param \Report $report
     * @param array $rowData
     *
     * @return array
     */
    public static function formatRowData(\Report $report, array $rowData)
    {
        $formattedIndices = [];

        if (empty($report->report_def['group_defs'])) {
            return $rowData;
        }

        $reportType = $report->getReportType();

        $reportColumns = $reportType === 'detailed_summary' ?
            $report->report_def['display_columns'] : $report->report_def['summary_columns'];

        foreach ($reportColumns as $displayMeta) {
            $targetFieldName = $displayMeta['name'];
            $targetFieldTable = $displayMeta['table_key'];
            $targetFieldModule = false;

            foreach ($report->report_def['full_table_list'] as $tableKey => $tableMeta) {
                if ($tableKey === $targetFieldTable) {
                    $targetFieldModule = $tableMeta['module'];
                    break;
                }
            }

            if (isset(self::$beanCache[$targetFieldModule])) {
                $targetBean = self::$beanCache[$targetFieldModule];
            } else {
                $targetBean = static::getBean($targetFieldModule);
                if (!empty($targetBean)) {
                    self::$beanCache[$targetFieldModule] = $targetBean;
                }
            }

            if (empty($targetBean)) {
                continue;
            }

            $fieldMeta = $targetBean->getFieldDefinition($targetFieldName);

            if (!$fieldMeta || empty($fieldMeta['type'])) {
                continue;
            }

            $reportFormatter = ReportFormatterFactory::getFormatter($fieldMeta['type']);
            foreach ($rowData as $key => $value) {
                if (!in_array($key, $formattedIndices) && self::isDatetime($value)) {
                    $formattedValue = $reportFormatter->format($value);

                    if ($formattedValue !== $value) {
                        $rowData[$key] = $formattedValue;
                        $formattedIndices[] = $key;
                    }
                }
            }
        }

        return $rowData;
    }

    /**
     * Helper function for easier testing
     * @param string $module
     * @return \SugarBean|null
     */
    protected static function getBean(string $module)
    {
        return \BeanFactory::retrieveBean($module);
    }

    /**
     * Format the chart row data
     *
     * @param \Report $report
     * @param array $rowData
     *
     * @return array
     */
    public static function formatChartRowData(\Report $report, array $rowData)
    {
        if (empty($report->report_def['group_defs'])) {
            return $rowData;
        }

        foreach ($report->report_def['group_defs'] as $fieldIdx => $fieldMeta) {
            if (empty($fieldMeta['type'])) {
                continue;
            }

            $reportFormatter = ReportFormatterFactory::getFormatter($fieldMeta['type']);

            if (!empty($rowData[$fieldIdx]) && self::isDatetime($rowData[$fieldIdx]['val'])) {
                $rowData[$fieldIdx]['val'] = $reportFormatter->format($rowData[$fieldIdx]['val']);
            }
        }

        return $rowData;
    }

    /**
     * Check if a string is a datetime format.
     *
     * @param string $value The string to check.
     *
     * @return bool True if the string matches a datetime format, false otherwise.
     */
    public static function isDatetime($value)
    {
        global $timedate;

        // Return false if timedate is not available (e.g., in test environment)
        if ($timedate === null) {
            return false;
        }

        if (static::$dateTimeFormatCache === null) {
            static::$dateTimeFormatCache = $timedate->get_date_time_format();
        }

        $dateTime = \SugarDateTime::createFromFormat(static::$dateTimeFormatCache, $value);

        if ($dateTime && $dateTime->format(static::$dateTimeFormatCache) === $value) {
            return true;
        }

        return false;
    }
}
