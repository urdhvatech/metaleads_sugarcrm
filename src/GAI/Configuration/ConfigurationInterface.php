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

namespace Sugarcrm\Sugarcrm\GAI\Configuration;

interface ConfigurationInterface
{
    /**
     * Returns the url for the gai service.
     *
     * @return string
     */
    public function getServiceURL(): string;

    /**
     * Returns the tenant from config.
     *
     * @return string
     */
    public function getTenant(): ?string;

    /**
     * Returns the headers for the gai service.
     * @return array
     */
    public function getHeaders(): array;
}
