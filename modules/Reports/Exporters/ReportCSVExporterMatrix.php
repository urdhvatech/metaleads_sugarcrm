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
declare(strict_types=1);

namespace Sugarcrm\Sugarcrm\modules\Reports\Exporters;

use Sugarcrm\Sugarcrm\Reports\Utils\ReportUtils;

/**
 * Class ReportCSVExporterMatrix
 * @package Sugarcrm\Sugarcrm\modules\Reports\Exporters
 */
abstract class ReportCSVExporterMatrix extends ReportCSVExporterBase
{
    /**
     * The detail information for headers in this report, specifically for matrix
     * @var array
     */
    protected $detailHeaders;

    /**
     * The headers of data going to display in a matrix inner table, specifically for matrix
     * @var array
     */
    protected $displayHeaders;

    /**
     * The main data structure under the hood for export summation with detail and matrix, specifically
     * for fast data retrieving and grouping hierarchy
     * @var array
     */
    protected $trie;

    /**
     * The headers that will be shown in a column of the matrix table, specifically for matrix
     * @var array
     */
    protected $columnHeaders;

    /**
     * The headers that will be shown in a row of the matrix table, specifically for matrix
     * @var array
     */
    protected $rowHeaders;

    /**
     * The headers in correct order
     * @var array
     */
    protected $headersInCorrectOrder = [];

    /**
     * Get the correct type for matrix export based on the layout options
     * @param \Report $reporter
     * @return string
     */
    public static function getSubTypeExporter(\Report $reporter): string
    {
        $layout = $reporter->report_def['layout_options'];
        if ($layout == '2x2') {
            return '1x1';
        } elseif ($layout == '1x2') {
            return '1x2';
        } else {
            return '2x1';
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function runQuery()
    {
        $this->reporter->run_summary_query();
        $this->reporter->fixGroupLabels();
        $this->detailHeaders = $this->reporter->getDataTypeForColumnsForMatrix();
        $this->displayHeaders = $this->matrixCleanUpHeaders();
        $this->trie = $this->matrixTrieBuilder();
        $this->setColumnAndRowHeaders();
    }

    /**
     * Makes a string containing $count of ,"", comma may be swapped with other user defined delimiter
     * @param int $count The amount of ,"" to generate
     * @return string A string containing $count of ,"", comma may be swapped with other user defined delimiter
     */
    protected function spacePadder(int $count): string
    {
        $output = '';
        for ($i = 0; $i < $count; $i++) {
            $output .= $this->getDelimiter(false) . '""';
        }
        return $output;
    }

    /**
     * Handles different situations of Grand Total variables
     * @param string The header of $data
     * @param $data The data for $displayHeader, unformated
     * @param $count The count for this specifically this piece of data
     * @param $current The currently held total
     * @param bool $no_data_check Whether the $current should avoid interact with empty string in $data
     * @return int|string int When $data and $current are no empty string, or whenever $noDataCheck is
     *      set to true. string when at least $data or $current is empty string, and $noDataCheck is set to false.
     */
    protected function groupFunctionHandler(
        string $displayHeader,
        $data,
        $count,
        $current,
        bool   $noDataCheck = true
    ) {

        if ($noDataCheck && ($count == null || $count == 0)) {
            return $current;
        }
        $funcName = $this->detailHeaders[$displayHeader]['group_function'];
        switch ($funcName) {
            case 'avg':
                return $current + $data * $count;
            case 'max':
                return $current > $data ? $current : $data;
            case 'min':
                return $current < $data ? $current : $data;
            case 'sum':
            case 'count':
                return $current + $data;
            default:
                throw new \Exception('Unsupported function: ' . $funcName);
        }
    }

    /**
     * Gives the formatted number according to whether the $number needs to add currency symbol
     * @param string $displayHeader The header of this $number
     * @param $number The data to display
     * @return string The formatted number with currency symbol when needed, otherwise the string
     *                of $number
     */
    protected function currencyFormatter(string $displayHeader, $number)
    {
        if ($this->detailHeaders[$displayHeader]['type'] == 'currency') {
            return \SugarCurrency::formatAmount(
                $number,
                $GLOBALS['current_user']->getPreference('currency')
            );
        }
        return strval($number);
    }

    /**
     * Clean up the mixed in grouping headers
     * @return array returns an array of headers that will display as neither row or column headers
     */
    protected function matrixCleanUpHeaders()
    {
        $output = [];
        $groupings = $this->reporter->report_def['group_defs'];
        $headers = $this->reporter->get_summary_header_row(true);
        foreach ($headers as $header) {
            $toDisplay = true;
            foreach ($groupings as $grouping) {
                if ($grouping['label'] == $header) {
                    $toDisplay = false;
                    break;
                }
            }
            if ($toDisplay) {
                $output[] = $header;
            }
        }
        return $output;
    }

    /**
     * Builds the trie for matrix
     * @requires $reporter has to finish running report query
     * @return array A Trie for making matrix csv, in the following format
     *               array(
     *                  group1 => array(
     *                       data_in_group1 => array(
     *                          array(rest_of_data1),
     *                          array(rest_of_data2), ...
     *                       )
     *                  ),
     *               )
     * @modifies $reporter
     * @effects Reads all the summary row data in $reporter.
     */
    protected function matrixTrieBuilder()
    {
        $output = [];
        $grouping = $this->reporter->report_def['group_defs'];
        $headers = $this->reporter->get_summary_header_row(true);
        while (($row = $this->reporter->get_summary_next_row(true)) != 0) {
            $row['cells'] = ReportUtils::formatRowData($this->reporter, $row['cells']);
            $data = $this->makeDetailData($row, $headers);
            $walker = &$output;
            // make the trie for the grouping part
            for ($i = 0; $i < safeCount($grouping); $i++) {
                if (!isset($walker[$grouping[$i]['label']])) {
                    // if the label is not yet there
                    $walker[$grouping[$i]['label']] = [];
                }
                $walker = &$walker[$grouping[$i]['label']];
                if (!isset($walker[$data['cells'][$grouping[$i]['label']]])) {
                    if (!isset($this->headersInCorrectOrder[$i])) {
                        $this->headersInCorrectOrder[$i] = [];
                    }

                    if (!in_array($data['cells'][$grouping[$i]['label']], $this->headersInCorrectOrder[$i])) {
                        $this->headersInCorrectOrder[$i][] = $data['cells'][$grouping[$i]['label']];
                    }

                    $walker[$data['cells'][$grouping[$i]['label']]] = [];
                }
                $walker = &$walker[$data['cells'][$grouping[$i]['label']]];
                // unset the data that are grouping to ensure only grouping is in trie path
                unset($data['cells'][$grouping[$i]['label']]);
            }
            $remainingData = [
                'cells' => [],
                'count' => $data['count'],
            ];
            // append the reset of the data to the end of this trie route
            for ($i = 0; $i < safeCount($headers); $i++) {
                if (isset($data['cells'][$headers[$i]])) {
                    $remainingData['cells'][$headers[$i]] = $data['cells'][$headers[$i]];
                }
            }
            $remainingData['Count'] = $data['count'];
            $walker[] = $remainingData;
        }

        return $output;
    }

    protected function setColumnAndRowHeaders(): void
    {
        $layout = $this->getLayoutOptions();

        if ($layout[0] === '1' && $layout[1] === '1') {
            $this->rowHeaders = $this->headersInCorrectOrder[0];
            $this->columnHeaders = $this->headersInCorrectOrder[1];
        }

        if ($layout[0] === '1' && $layout[1] === '2') {
            $this->rowHeaders = $this->headersInCorrectOrder[0];
            $this->columnHeaders = [];
            $this->columnHeaders[0] = array_fill_keys($this->headersInCorrectOrder[1], true);
            $this->columnHeaders[1] = array_fill_keys($this->headersInCorrectOrder[2], true);
        }

        if ($layout[0] === '2' && $layout[1] === '1') {
            $this->rowHeaders = [];
            $this->rowHeaders[0] = array_fill_keys($this->headersInCorrectOrder[0], true);
            $this->rowHeaders[1] = array_fill_keys($this->headersInCorrectOrder[1], true);
            $this->columnHeaders = $this->headersInCorrectOrder[2];
        }
    }

    /**
     * @return array
     */
    protected function getLayoutOptions(): array
    {
        return explode(
            'x',
            $this->reporter->report_def['layout_options'] == '2x2' ? '1x1' : $this->reporter->report_def['layout_options']
        );
    }

    protected function uniteCounts(array $data): array
    {
        $result = $data[0];

        for ($i = 1; $i < safeCount($data); $i++) {
            if (!isset($data[$i]) || !isset($data[$i]['cells']) || !isset($data[$i]['count'])) {
                continue;
            }
            $result['cells']['Count'] += (int)$data[$i]['cells']['Count'];
            $result['count'] += $data[$i]['count'];
            $result['Count'] += $data[$i]['Count'];
        }

        return $result;
    }
}
