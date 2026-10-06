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

use Sugarcrm\Sugarcrm\Reports\Types\Matrix;

class ReportsSugarpdfMatrix extends ReportsSugarpdfReports
{
    /**
     * Display the pdf report with chart and html table
     */
    public function display()
    {
        $this->chart();
        $this->addTable();
    }

    /**
    * Add html table to the PDF
    */
    protected function addTable()
    {
        $this->AddPage('L');

        $reportData = $this->getReportData();
        $header = $reportData['header'];
        $data = $reportData['data'];

        if (array_key_exists('grandTotalBottomFormatted', $reportData)) {
            $data['Total'] = $reportData['grandTotalBottomFormatted'];
        }

        $this->writeMatrixHtmlTable($header, $data, $this->bean->report_def['layout_options']);
    }

    /**
    * Return the matrix report data
    * @return array $data Data for the report
    */
    private function getReportData(): array
    {
        $reporter = $this->getReporter();
        $data = $reporter->getListData(true, true);
        return $data;
    }

    /**
     * Get reporter for the report
     * @return Matrix
     */
    private function getReporter(): Matrix
    {
        $reporter = new Matrix([
            'record' => $this->bean->saved_report_id,
            'type' => 'Matrix',
        ], true);
        $reportDef = $reporter->buildReportDef(json_encode($this->bean->report_def));
        $reporter->setReportDef($reportDef);
        return $reporter;
    }
}
