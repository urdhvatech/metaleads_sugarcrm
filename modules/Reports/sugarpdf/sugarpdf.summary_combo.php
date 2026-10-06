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

class ReportsSugarpdfSummary_combo extends ReportsSugarpdfReports
{
    public function display()
    {
        global $app_list_strings, $locale;

        $this->chart();

        $this->AddPage();

        //disable paging so we get all results in one pass
        $this->bean->enable_paging = false;
        $cols = safeCount($this->bean->report_def['display_columns']);
        $this->bean->run_summary_combo_query();

        $header_row = $this->bean->get_summary_header_row();
        $columns_row = $this->bean->get_header_row();

        // build options for the writeHTMLTable from options for the writeCellTable
        $options = ['header' => [
            'tr' => [
                'bgcolor' => $this->options['header']['fill'],
                'color' => $this->options['header']['textColor']],
            'td' => [],
        ],
            'evencolor' => $this->options['evencolor'],
            'oddcolor' => $this->options['oddcolor'],
        ];

        while (($row = $this->bean->get_summary_next_row()) != 0) {
            $row['cells'] = ReportUtils::formatRowData($this->bean, $row['cells']);
            // summary columns
            $item = [];
            $count = 0;

            for ($j = 0; $j < sizeof($row['cells']); $j++) {
                if ($j > safeCount($header_row) - 1) {
                    $label = $header_row[safeCount($header_row) - 1];
                } else {
                    $label = $header_row[$j];
                }
                if (preg_match('/type.*=.*checkbox/Uis', $row['cells'][$j])) { // parse out checkboxes
                    if (preg_match('/checked/i', $row['cells'][$j])) {
                        $row['cells'][$j] = $app_list_strings['dom_switch_bool']['on'];
                    } else {
                        $row['cells'][$j] = $app_list_strings['dom_switch_bool']['off'];
                    }
                }
                $value = $row['cells'][$j];
                $item[$count][$label] = $value;
            }

            foreach ($item as $i) {
                $text = '';
                foreach ($i as $l => $j) {
                    if ($text) {
                        $text .= ', ';
                    }
                    $text .= $l . ' = ' . $j;
                }
                $this->Write(1, $text);
                $this->Ln1();
            }

            // display columns
            $item = [];
            $count = 0;

            for ($i = 0; $i < $this->bean->current_summary_row_count; $i++) {
                if (($column_row = $this->bean->get_next_row('result', 'display_columns', false, true)) != 0) {
                    $column_row['cells'] = ReportUtils::formatRowData($this->bean, $column_row['cells']);
                    for ($j = 0; $j < sizeof($columns_row); $j++) {
                        $label = $columns_row[$j];
                        $value = $column_row['cells'][$j];

                        // Get field name from report definition and apply special formatting for SummarizationGai fields
                        $fieldName = SummarizationGaiFormatter::getFieldNameByIndex($this->bean->report_def, $j);
                        $fieldModule = SummarizationGaiFormatter::getFieldModuleByIndex($this->bean->report_def, $i);

                        // Apply special formatting for SummarizationGai fields (decode HTML entities first)
                        if (SummarizationGaiFormatter::isSummarizationGaiField($fieldName, $fieldModule)) {
                            // decode before formatting so JSON isn't HTML-escaped
                            $decoded = html_entity_decode($value, ENT_QUOTES|ENT_HTML5, 'UTF-8');
                            $value  = SummarizationGaiFormatter::formatField($decoded, $fieldName);
                        }

                        $item[$count][$label] = $value;
                    }
                    $count++;
                } else {
                    break;
                }
            }

            $this->writeHTMLTable($item, false, $options);
            $this->Ln1();
        }

        $this->bean->clear_results();

        if ($this->bean->has_summary_columns()) {
            $this->bean->run_total_query();
            $total_row = $this->bean->get_summary_total_row();
            $item = [];
            $count = 0;

            for ($j = 0; $j < sizeof($header_row); $j++) {
                $label = $header_row[$j];
                $value = $total_row['cells'][$j];
                $item[$count][$label] = $value;
            }

            $this->writeHTMLTable($item, false, $options);
        }
    }
}
