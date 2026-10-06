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

class GAIInternalizationAdapter extends GAIBaseDataAdapter
{
    /**
     * Retrieves data from the GAI service for the inference process of a translation
     */
    public function getInferenceData(): array
    {
        $tenantId = $this->config->getTenant();

        $this->payload['tenantId'] = $tenantId;
        $this->payload['usecaseType'] = $this->options['usecaseType'];
        $this->payload['evalId'] = $this->options['originalEvalId'];
        $this->payload['contextData'] = [
            'parentObjectType' => $this->options['parentObjectType'],
            'language' => $this->options['language'],
        ];

        return $this->payload;
    }

    /**
     * Retrieves data from the GAI service for the retrieval process of a translation
     * This method is not implemented in this adapter
     */
    public function getRetrieveData(): array
    {
        return [
            'error' => true,
            'message' => 'LBL_NOT_IMPLEMENTED',
        ];
    }
}
