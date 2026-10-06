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
use Sugarcrm\Sugarcrm\Reports\Utils\ReportUtils;
use Sugarcrm\Sugarcrm\Reports\ReportFormatterFactory;
use Sugarcrm\Sugarcrm\GAI\Utils\SummarizationGaiFormatter;

class ReportsSugarpdfListview extends ReportsSugarpdfReports
{
    public function display()
    {
        $this->bean->run_query();

        $this->AddPage();

        $item = [];
        $header_row = $this->bean->get_header_row('display_columns', false, false, true);
        $count = 0;

        while ($row = $this->bean->get_next_row('result', 'display_columns', false, true)) {
            for ($i = 0; $i < sizeof($header_row); $i++) {
                $label = $header_row[$i];
                $value = '';
                if (isset($row['cells'][$i])) {
                    if (ReportUtils::isDatetime($row['cells'][$i])) {
                        $reportFormatter = ReportFormatterFactory::getFormatter('datetime');
                        $value = $reportFormatter->format($row['cells'][$i]);
                    } else {
                        $value = $row['cells'][$i];
                    }

                    // Get field name and module from report definition and apply special formatting for SummarizationGai fields
                    $fieldName = SummarizationGaiFormatter::getFieldNameByIndex($this->bean->report_def, $i);
                    $fieldModule = SummarizationGaiFormatter::getFieldModuleByIndex($this->bean->report_def, $i);

                    // Apply special formatting for SummarizationGai fields (decode HTML entities first)
                    if (SummarizationGaiFormatter::isSummarizationGaiField($fieldName, $fieldModule)) {
                        // decode before formatting so JSON isn’t HTML-escaped
                        $decoded = html_entity_decode($value, ENT_QUOTES|ENT_HTML5, 'UTF-8');
                        $value  = SummarizationGaiFormatter::formatField($decoded, $fieldName);
                    }
                }
                $item[$count][$label] = $value;
            }
            $count++;
        }

        $this->writeCellTable($item, $this->options);
    }
}
