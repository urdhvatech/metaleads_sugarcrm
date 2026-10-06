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

namespace Sugarcrm\Sugarcrm\GAI\Adapter\Adapters\Data;

use Sugarcrm\Sugarcrm\GAI\Adapter\Adapters\GAIBaseDataAdapter;
use Sugarcrm\Sugarcrm\GAI\Helper;

class GAIDataIngestAdapter extends GAIBaseDataAdapter
{
    /**
     * Build and return the data for ingestion
     */
    public function getIngestData(): array
    {
        $tenantId = $this->config->getTenant();

        $this->payload['tenantId'] = $tenantId;
        $this->payload['usecaseType'] = $this->options['usecaseType'];
        $this->payload['objectType'] = $this->options['objectType'];
        $this->payload['objectId'] = $this->options['objectId'];
        $this->payload['compressedData'] = Helper::compressData($this->options['data']);

        return $this->payload;
    }

    /**
     * Build batch inference data
     */
    public function batchInference(): array
    {
        $tenantId = $this->config->getTenant();

        $this->payload['tenantId'] = $tenantId;
        $this->payload['usecaseType'] = $this->options['usecaseType'];
        $this->payload['objectType'] = $this->options['objectType'];
        $this->payload['objectId'] = $this->options['objectId'];
        $this->payload['objectName'] = $this->options['objectName'];

        $this->payload['contextData'] = [
            'language' => $this->options['language'],
        ];

        if (isset($this->options['translate'])) {
            $this->payload['contextData']['translate'] = $this->options['translate'];
        }

        return $this->payload;
    }

    /**
     * Retrieves data from the GAI service for the inference process
     * This method is not implemented in this adapter
     */
    public function getInferenceData(): array
    {
        return [
            'error' => true,
            'message' => 'LBL_NOT_IMPLEMENTED',
        ];
    }

    /**
     * Retrieves data from the GAI service for the retrieval process
     */
    public function getRetrieveData(): array
    {
        $tenantId = $this->config->getTenant();

        $this->payload['tenantId'] = $tenantId;
        $this->payload['usecaseType'] = $this->options['usecaseType'];
        $this->payload['evalId'] = $this->options['evalId'];

        return $this->payload;
    }
}
