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
use Sugarcrm\Sugarcrm\Reports\ReportFormatterFactory;
use Sugarcrm\Sugarcrm\GAI\Utils\SummarizationGaiFormatter;

/**
 * Class ReportCSVExporterRowsAndColumns
 * @package Sugarcrm\Sugarcrm\modules\Reports\Exporters
 */
class ReportCSVExporterRowsAndColumns extends ReportCSVExporterBase implements ReportStreamableExporterInterface
{
    /**
     * {@inheritdoc}
     */
    protected function runQuery()
    {
        $this->reporter->run_query();
    }

    /**
     * {@inheritdoc}
     */
    public function exportStream(): \Generator
    {
        $this->prepareExport();

        // Cache delimiter and line ending to avoid repeated method calls
        $delimiter = $this->getDelimiter();
        $lineEnd = $this->getLineEnd();

        $headerRow = $this->reporter->get_header_row('display_columns', false, true, false);

        $header = '"' . implode($delimiter, array_values($headerRow));
        $header .= '"' . $lineEnd;
        yield $header;

        while (($row = $this->reporter->get_next_row('result', 'display_columns', false, true)) !== 0) {
            $newArr = [];

            $cellCount = safeCount($row['cells']);
            for ($i = 0; $i < $cellCount; $i++) {
                if (ReportUtils::isDatetime($row['cells'][$i])) {
                    $reportFormatter = ReportFormatterFactory::getFormatter('datetime');
                    $rowVal = $reportFormatter->format($row['cells'][$i]);
                } else {
                    $rowVal = $row['cells'][$i];
                }

                // Get field name and module from report definition
                // and apply special formatting for SummarizationGai fields
                $fieldName = SummarizationGaiFormatter::getFieldNameByIndex($this->reporter->report_def, $i);
                $fieldModule = SummarizationGaiFormatter::getFieldModuleByIndex($this->reporter->report_def, $i);

                // Apply special formatting for SummarizationGai fields (decode HTML entities first)
                if (SummarizationGaiFormatter::isSummarizationGaiField($fieldName, $fieldModule)) {
                    // decode before formatting so JSON isn't HTML-escaped
                    $decoded = is_string($rowVal) ? htmlspecialchars_decode($rowVal, ENT_QUOTES) : $rowVal;
                    $rowVal  = SummarizationGaiFormatter::formatField($decoded, $fieldName);
                } else {
                    $rowVal = is_string($rowVal) ? htmlspecialchars_decode($rowVal, ENT_QUOTES) : $rowVal;
                }

                if (!is_string($rowVal)) {
                    $rowVal = strval($rowVal);
                }
                array_push($newArr, preg_replace('/"/', '""', $rowVal));
            }

            $line = '"';
            $line .= implode($delimiter, $newArr);
            $line .= '"' . $lineEnd;

            yield $line;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function export(): string
    {
        $content = '';
        foreach ($this->exportStream() as $chunk) {
            $content .= $chunk;
        }
        return $content;
    }
}
