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

namespace Sugarcrm\Sugarcrm\modules\Reports\Exporters;

/**
 * Interface ReportStreamableExporterInterface
 * Exporters implementing this interface can stream data row-by-row
 * to avoid memory issues with large datasets.
 */
interface ReportStreamableExporterInterface extends ReportExporterInterface
{
    /**
     * Returns a generator that yields CSV content in chunks.
     * Each chunk should be a complete CSV row or set of rows.
     *
     * @return \Generator
     */
    public function exportStream(): \Generator;
}
