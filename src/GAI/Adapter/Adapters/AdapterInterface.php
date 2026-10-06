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

namespace Sugarcrm\Sugarcrm\GAI\Adapter\Adapters;

/**
 * The interface for the data adapters
 * @package Sugarcrm\Sugarcrm\DocumentMerge\Client\Adapter\Adapters
 */
interface AdapterInterface
{
    /**
     * here we build the payload data for inference
     * @return array
     */
    public function getInferenceData(): array;

    /**
     * here we build the payload data for retreive
     * @return array
     */
    public function getRetrieveData(): array;


    /**
     * here we build the payload data for config
     * @return array
     */
    public function getConfigData(): array;

    /**
     * here we build the payload data for token usage
     * @return array
     */
    public function getTokenUsageData(): array;
}
