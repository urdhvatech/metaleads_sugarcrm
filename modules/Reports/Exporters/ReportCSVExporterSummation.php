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
 * Class ReportCSVExporterSummation
 * @package Sugarcrm\Sugarcrm\modules\Reports\Exporters
 */
class ReportCSVExporterSummation extends ReportCSVExporterBase implements ReportStreamableExporterInterface
{
    /**
     * {@inheritdoc}
     */
    protected function runQuery()
    {
        $this->reporter->run_summary_query();
        $this->reporter->run_total_query();
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

        $content = '"' . implode($delimiter, $this->reporter->get_summary_header_row());
        $content .= '"' . $lineEnd;
        yield $content;

        while (($row = $this->reporter->get_next_row('summary_result', 'summary_columns', false, true)) != 0) {
            $row['cells'] = ReportUtils::formatRowData($this->reporter, $row['cells']);
            $line = '"' . implode($delimiter, $row['cells']);
            $line .= '"' . $lineEnd;
            yield $line;
        }

        yield $this->getLineEnd(2);
        yield $this->getGrandTotal();
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
