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

namespace Sugarcrm\Sugarcrm\HistoricallyDelta\Client;

use SugarApiException;
use Random\RandomException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Sugarcrm\Sugarcrm\Security\HttpClient\ExternalResourceClient;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Client\TokenGenerator;
use Sugarcrm\Sugarcrm\Security\HttpClient\RequestException;
use Sugarcrm\Sugarcrm\IdentityProvider\Authentication\Config;

class Client
{
    private int $requestTimeout;
    private float $maxRetries;
    protected ExternalResourceClient $client;

    /**
     * Client constructor.
     *
     * @param int $requestTimeout
     * @param float $maxRetries
     *
     * @return void
     */
    public function __construct(int $requestTimeout, float $maxRetries)
    {
        $this->maxRetries = $maxRetries;
        $this->requestTimeout = $requestTimeout;

        $this->client = $this->getExternalResourceClient();
    }

    /**
     * Return ExternalResourceClient object
     */
    protected function getExternalResourceClient(): ExternalResourceClient
    {
        $client = new ExternalResourceClient($this->requestTimeout);
        $client->setTimeout($this->requestTimeout);
        $client->setMaxRetries($this->maxRetries);

        return $client;
    }

    /**
     * Generate access token
     */
    protected function getAccessToken()
    {
        $tokenGenerator = new TokenGenerator();

        return $tokenGenerator->createToken();
    }

    /**
     * Retrieve delta data
     *
     * @param string $url
     * @param array $data
     *
     * @return ResponseInterface
     * @throws SugarApiException
     * @throws InvalidArgumentException
     * @throws RequestException
     * @throws RandomException
     */
    public function retrieveDelta(string $url, array $data): ResponseInterface
    {
        $accessToken = $this->getAccessToken();

        if (!is_array($accessToken) || empty($accessToken['access_token'])) {
            throw new InvalidArgumentException('Access token is empty');
        }

        $headers = $this->buildHeaders($accessToken['access_token']);

        $response = $this->client->post(
            $url,
            json_encode($data),
            $headers
        );

        return $response;
    }

    /**
     * @return string
     */
    public function getDeltaUrl()
    {
        $idpConfig = new Config(\SugarConfig::getInstance());
        $idmModeConfig = $idpConfig->getIDMModeConfig();

        if (!$idpConfig->isIDMModeEnabled()) {
            throw new \SugarApiExceptionError('Historically Delta: unable to retrieve delta, IDP is not enabled');
        }

        $configurator = new \Configurator();
        $configurator->loadConfig();

        $isCatalogEnabled = $configurator->config['catalog_enabled'];
        $catalogUrlFromCfg = $configurator->config['catalog_url'];
        $isCatalogConfigValid = isset($isCatalogEnabled) && $isCatalogEnabled;

        if ($isCatalogConfigValid && isset($catalogUrlFromCfg) && !empty($idmModeConfig['tid'])) {
            $catalogUrl = $catalogUrlFromCfg;
        } else {
            $catalogUrl = $idpConfig->getCatalogURL();

            if (empty($catalogUrl)) {
                throw new \SugarApiExceptionError('Historically Delta: unable to retrieve delta, catalog URL is not set');
            }
        }

        if (!is_string($catalogUrl) || trim($catalogUrl) === '') {
            throw new \SugarApiExceptionError('Historically Delta: unable to resolve catalog URL');
        }

        $catalogUrl = rtrim($catalogUrl ?? '', '/') . '/catalog?isAuthorized=true';

        $response = $this->client->get($catalogUrl);

        if ($response->getStatusCode() !== 200) {
            throw new \SugarApiExceptionError('Historically Delta: unable to retrieve delta, catalog URL is not reachable');
        }

        $data = json_decode($response->getBody(), true);

        $url = '';

        if (is_array($data) && isset($data['apps']) && is_array($data['apps'])) {
            foreach ($data['apps'] as $app) {
                if (isset($app['title'], $app['src']) && $app['title'] === 'Sugar Discover') {
                    $src = $app['src'];
                    if (str_ends_with($src, '/manifest')) {
                        $url = substr($src, 0, -strlen('/manifest'));
                    }
                    break;
                }
            }
        }

        if (empty($url)) {
            throw new \SugarApiExceptionError('Historically Delta: unable to retrieve delta, URL is not set');
        }

        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME) ?? 'https';

        $url = "$scheme://$host/api/deltas";

        return $url;
    }

    /**
     * Build the headers for the request
     *
     * @param string $accessToken
     * @return array
     */
    private function buildHeaders(string $accessToken): array
    {
        return [
            'X-Idm-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ];
    }
}
