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

use Sugarcrm\Sugarcrm\GAI\Configuration\Configuration;
use Sugarcrm\Sugarcrm\GAI\Adapter\AdapterFactory;

/**
 * base class for data adapters
 * @package Sugarcrm\Sugarcrm\GAI\Client\Adapter\Adapters
 */
class GAIBaseConfigAdapter implements AdapterInterface
{
    protected $config;

    /**
     * This will be processed in order to be
     * returned in a data model accepted by the gai service
     * @var array
     */
    protected $options;

    protected $payload;

    /**
     * @constructor
     * @param array $options
     * @return void
     */
    public function __construct(array $options)
    {
        $this->config = AdapterFactory::getConfigAdapterInstance(\SugarConfig::getInstance());
        $this->options = $options;
        $this->payload = [];
    }

    /**
     * Build and return the data
     *
     * @return array
     */
    public function getConfigData(): array
    {
        $tenantId = $this->config->getTenant();

        $this->payload = [];
        $this->payload['tenantId'] = $tenantId;
        $this->payload['usecaseType'] = $this->options['usecaseType'];
        $this->payload['parentObjectType'] = $this->options['parentObjectType'];

        return $this->payload;
    }

    /**
     * Build and return the data
     *
     * @return array
     */
    public function getInferenceData(): array
    {
        return [
            'error' => true,
            'message' => 'LBL_NOT_IMPLEMENTED',
        ];
    }

    /**
     * Build and return the data
     *
     * @return array
     */
    public function getRetrieveData(): array
    {
        return [
            'error' => true,
            'message' => 'LBL_NOT_IMPLEMENTED',
        ];
    }

    /**
     * Build and return the data
     *
     * @return array
     */
    public function getTokenUsageData(): array
    {
        $tenantId = $this->config->getTenant();

        $this->payload = [];
        $this->payload['tenantId'] = $tenantId;

        return $this->payload;
    }
}
