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

class GAIGenericDataAdapter extends GAIBaseDataAdapter
{
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
