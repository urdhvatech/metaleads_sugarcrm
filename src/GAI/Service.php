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

namespace Sugarcrm\Sugarcrm\GAI;

use Psr\Http\Message\ResponseInterface;
use Sugarcrm\Sugarcrm\GAI\Adapter\AdapterFactory;
use Sugarcrm\Sugarcrm\GAI\Client\Http\Client;

class Service
{
    private $url;

    protected $config;
    protected $client;

    public function __construct()
    {
        $this->config = AdapterFactory::getConfigAdapterInstance(\SugarConfig::getInstance());
        $this->url = $this->config->getServiceURL();
        $this->client = new Client($this->url, $this->config->getMaxRetries());
    }

    public function inference(array $options): ResponseInterface
    {
        return $this->client->call('POST', 'inference', $options);
    }

    public function retrieve(array $options): ResponseInterface
    {
        return $this->client->call('GET', 'retrieve', $options, 'query');
    }

    public function sendDataIngest(array $options): ResponseInterface
    {
        return $this->client->call('POST', 'dataIngest', $options);
    }

    public function getConfig(array $options): ResponseInterface
    {
        return $this->client->call('GET', 'config', $options, 'query');
    }

    public function getConfigBulk(array $options): ResponseInterface
    {
        return $this->client->call('GET', 'config/v2', $options, 'query');
    }

    public function getTokenUsage(array $options): ResponseInterface
    {
        return $this->client->call('GET', 'tokens', $options, 'query');
    }

    public function sendBatchInference(array $options): ResponseInterface
    {
        return $this->client->call('POST', 'batch/inference', $options);
    }
}
