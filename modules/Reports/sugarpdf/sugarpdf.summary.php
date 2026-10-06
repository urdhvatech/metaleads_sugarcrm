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

class ReportsSugarpdfSummary extends ReportsSugarpdfReports
{
    public function display()
    {
        global $locale;

        //add chart
        $this->chart();

        //Create new page
        $this->AddPage();

        $this->bean->run_summary_query();
        $item = [];
        $header_row = $this->bean->get_summary_header_row();
        $count = 0;

        if (safeCount($this->bean->report_def['summary_columns']) == 0) {
            $item[$count][''] = '';
            $count++;
        }
        if (safeCount($this->bean->report_def['summary_columns']) > 0) {
            while ($row = $this->bean->get_summary_next_row()) {
                $row['cells'] = ReportUtils::formatRowData($this->bean, $row['cells']);
                for ($i = 0; $i < sizeof($header_row); $i++) {
                    $label = $header_row[$i];
                    $value = '';
                    if (isset($row['cells'][$i])) {
                        $value = $row['cells'][$i];
                    }
                    $item[$count][$label] = $value;
                }
                $count++;
            }
        }

        $this->writeCellTable($item, $this->options);
        $this->Ln1();

        $this->bean->clear_results();

        if ($this->bean->has_summary_columns()) {
            $this->bean->run_total_query();
        }

        $total_header_row = $this->bean->get_total_header_row();
        $total_row = $this->bean->get_summary_total_row();
        $item = [];
        $count = 0;

        for ($j = 0; $j < sizeof($total_header_row); $j++) {
            $label = $total_header_row[$j];
            $item[$count][$label] = $total_row['cells'][$j];
        }

        $this->writeCellTable($item, $this->options);
    }
}
