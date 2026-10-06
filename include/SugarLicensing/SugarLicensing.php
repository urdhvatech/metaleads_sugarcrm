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

use Psr\Log\LoggerAwareTrait;
use Sugarcrm\Sugarcrm\Security\HttpClient\ExternalResourceClient;
use Sugarcrm\Sugarcrm\Security\HttpClient\RequestException;
use Sugarcrm\Sugarcrm\Logger\Factory as LoggerFactory;

class SugarLicensing
{
    use LoggerAwareTrait;

    protected $server = 'https://authenticate.sugarcrm.com';

    /**
     * Make a request to the end point on the licensing server
     *
     * @param $endpoint
     * @param $payload
     * @param bool $doDecode
     * @param int $timeout
     * @return array|false
     */
    public function request($endpoint, $payload, $doDecode = true, $timeout = 30)
    {
        // make sure that the first char is a "/"
        if (substr($endpoint, 0, 1) != '/') {
            $endpoint = '/' . $endpoint;
        }

        $endpoint = $this->getServerName() . $endpoint;
        $client = new ExternalResourceClient($timeout);
        if (!empty($payload) && is_array($payload)) {
            $payload = json_encode($payload);
        }

        try {
            $response = !empty($payload)
                ? $client->post($endpoint, $payload)->getBody()->getContents()
                : $client->get($endpoint)->getBody()->getContents();
        } catch (\Exception $e) {
            $this->setLogger(LoggerFactory::getLogger('default'));
            $this->logger->error('Sugar Licensing encountered an exception: ' . $e->getMessage(), ['exception' => $e]);
            return false;
        }

        if ($doDecode && $response != false) {
            return json_decode($response, true);
        }

        return $response;
    }

    /**
     * get Sugar licensing server name
     */
    protected function getServerName()
    {
        global $sugar_config;
        if (isset($sugar_config['license_server'])) {
            return rtrim(trim($sugar_config['license_server']), '/');
        }

        return $this->server;
    }
}
