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

namespace Sugarcrm\Sugarcrm\GAI\Client\Http;

use GuzzleHttp;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\ResponseInterface;
use Sugarcrm\Sugarcrm\GAI\Client\Client as ClientInterface;
use Sugarcrm\Sugarcrm\GAI\Adapter\AdapterFactory;
use Sugarcrm\Sugarcrm\GAI\Configuration\ConfigurationOnPremAdapter;

class Client implements ClientInterface
{
    protected $config;

    /**
     * Send requests through this HTTP client.
     *
     * @var GuzzleClient
     */
    private $client;

    /**
     * The URL to the GAI api.
     *
     * @var string
     */
    private $url;

    /**
    * Constructor for the GAI Client.
    *
    * @param string $url The URL to the GAI API.
    * @param int $maxRetries The maximum number of retries for failed requests.
    * @param bool $testingAccessToken If true, uses the ConfigurationOnPremAdapter for testing the access token.
    */
    public function __construct(string $url, int $maxRetries = 3, $testingAccessToken = false)
    {
        if ($testingAccessToken) {
            $this->config = new ConfigurationOnPremAdapter(\SugarConfig::getInstance()) ;
        } else {
            $this->config = AdapterFactory::getConfigAdapterInstance(\SugarConfig::getInstance());
        }

        $retry = new RetryMiddleware($maxRetries);

        $stack = HandlerStack::create(GuzzleHttp\choose_handler());
        $stack->push($retry, 'retry');

        $this->client = new GuzzleClient(['handler' => $stack]);
        $this->url = $url;
    }

    /**
     * Calls the GAI api.
     *
     * @param string $method HTTP Method
     * @param array $options The data to send to the api.
     *
     * @return ResponseInterface
     */
    public function call(string $method, string $endpoint, array $options, string $type = 'json'): ResponseInterface
    {
        $url = rtrim($this->url, '/') . '/';
        $endpoint = ltrim($endpoint, '/');
        $fullUrl = $url . $endpoint;

        $header = $this->config->getHeaders();
        return $this->client->request($method, $fullUrl, [$type => $options, 'headers' => $header]);
    }

    /**
     * Calls the GAI api for access token.
     *
     * @param string $method HTTP Method
     * @param string $endpoint The endpoint to call.
     * @param array $options The data to send to the api.
     * @param string $type The type of request (json or query).
     * @param array $headers The headers to send with the request.
     *
     * @return ResponseInterface
     */
    public function callAccesToken(string $method, string $endpoint, array $options, string $type = 'json', array $headers = []): ResponseInterface
    {
        $url = rtrim($this->url, '/') . '/';
        $endpoint = ltrim($endpoint, '/');
        $fullUrl = $url . $endpoint;

        $requestOptions = [
            $type => $options,
        ];

        if (!empty($headers)) {
            $requestOptions['headers'] = $headers;
        }

        return $this->client->request($method, $fullUrl, $requestOptions);
    }
}
