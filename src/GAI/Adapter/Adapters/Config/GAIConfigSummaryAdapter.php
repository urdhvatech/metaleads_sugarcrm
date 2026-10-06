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

namespace Sugarcrm\Sugarcrm\GAI\Adapter\Adapters\Config;

use Sugarcrm\Sugarcrm\GAI\Adapter\Adapters\GAIBaseConfigAdapter;

class GAIConfigSummaryAdapter extends GAIBaseConfigAdapter
{
    /**
     * Build and return the configuration data
     *
     * @return array
     */
    public function getConfigData(): array
    {
        $this->payload = parent::getConfigData();

        return $this->payload;
    }
}
