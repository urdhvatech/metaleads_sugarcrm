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

use Administration;
use ConnectorUtils;
use Sugarcrm\Sugarcrm\Security\Crypto\AES256GCM;
use Sugarcrm\Sugarcrm\IdentityProvider\Authentication\Config as IdmConfig;
use Sugarcrm\IdentityProvider\Srn\Converter as SrnConverter;
use Sugarcrm\Sugarcrm\GAI\Client\Http\Client;
use Sugarcrm\Sugarcrm\GAI\Exception\BackendNotConfiguredException;
use Sugarcrm\Sugarcrm\GAI\Configuration\ConfigurationInterface;
use Sugarcrm\Sugarcrm\GAI\Helper;

class ConfigurationOnPremAdapter implements ConfigurationInterface
{
    private const GAI_SCOPE = 'https://apis.sugarcrm.com/auth/email-extract https://apis.sugarcrm.com/auth/gai hydra.keys.get';
    private const GAI_GRANT_TYPE = 'client_credentials';
    private const SOURCE_ID = 'ext_eapm_gai';

    /**
     * @var \SugarConfig
     */
    private $config;

    public function __construct(\SugarConfig $sugarConfig)
    {
        $this->config = $sugarConfig;
    }

    /**
     * Returns the URL to GAI service for the given region.
     *
     * @return string
     */
    public function getServiceURL(): string
    {
        $gaiConfig = $this->config->get('gai_service');
        $region = $this->getRegion();

        if ($region && !empty($gaiConfig['service_urls'][$region])) {
            return $gaiConfig['service_urls'][$region];
        } else {
            return $gaiConfig['service_urls']['default'] ?? '';
        }
    }

    /**
     * Gets aws region from idm config.
     *
     * @return string
     */
    protected function getRegion(): string
    {
        $region = 'default';
        $idmConfig = new IdmConfig($this->config);
        $modeConfig = $idmConfig->getIDMModeConfig();

        if (!empty($modeConfig['tid'])) {
            $tenantSrn = SrnConverter::fromString($modeConfig['tid']);

            if ($tenantSrn) {
                $region = $tenantSrn->getRegion();
            }
        }

        return $region;
    }

    /**
     * Returns the maximum number of retries for API calls.
     *
     * @return int
     */
    public function getMaxRetries(): int
    {
        return 1;
    }

    /**
     * Returns the headers for the GAI service.
     *
     * @return array The headers for the GAI service.
     */
    public function getHeaders(): array
    {
        $accessTokenInfo = $this->getAccessTokenInfo();

        if ($this->isAccessTokenExpired($accessTokenInfo)) {
            $options = $this->getGAIOauth2Config();
            $accessTokenInfo = $this->fetchAccessTokenInfo($options);
            $this->setAccessTokenInfo($accessTokenInfo);
        }

        return ['Authorization' => 'Bearer ' . $accessTokenInfo['access_token']];
    }

    /**
     * Returns the scope for the authentication.
     *
     * @return string
     */
    public function getScope(): string
    {
        return self::GAI_SCOPE;
    }

    /**
     * Returns the grant type for the authentication.
     *
     * @return string
     */
    public function getGrantType(): string
    {
        return self::GAI_GRANT_TYPE;
    }

    /**
     * Returns the access token information from the administration settings.
     *
     * @return array The access token information.
     */
    public function getAccessTokenInfo(): array
    {
        $key = $this->getTenant();
        if (empty($key)) {
            throw new BackendNotConfiguredException('Tenant ID is not set.');
        }
        $aes = new AES256GCM($key);

        $admin = Administration::getSettings();
        $encryptedToken = $admin->settings['gai_access_token'] ?? null;

        if (empty($encryptedToken)) {
            return [
                'access_token' => null,
                'expires_at' => 0,
            ];
        }

        $accessToken = $encryptedToken ? $aes->decrypt(base64_decode($encryptedToken)) : null;
        $expiresAt = (int)($admin->settings['gai_expires_at'] ?? 0);

        return [
            'access_token' => $accessToken,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Sets the access token information in the administration settings.
     *
     * @param array $accessTokenInfo The access token information to set.
     */
    public function setAccessTokenInfo(array $accessTokenInfo): void
    {
        $admin = Administration::getSettings();

        $key = $this->getTenant();
        if (empty($key)) {
            throw new BackendNotConfiguredException('Tenant ID is not set.');
        }

        $aes = new AES256GCM($key);
        $encryptedToken = base64_encode($aes->encrypt($accessTokenInfo['access_token']));

        $expiresAtUtc = time() + ($accessTokenInfo['expires_in'] ?? 3599);

        $admin->saveSetting('gai', 'access_token', $encryptedToken);
        $admin->saveSetting('gai', 'expires_at', $expiresAtUtc);
    }

    /**
     * Returns the OAuth2 configuration for GAI.
     *
     * @return array The OAuth2 configuration.
     * @throws \RuntimeException If the client ID or secret is not set.
     */
    public function getGAIOauth2Config(): array
    {
        $properties = Helper::getConnectorProperties(self::SOURCE_ID);

        $oauth2ClientId = $properties['oauth2_client_id'] ?? null;
        $oauth2ClientSecret = $properties['oauth2_client_secret'] ?? null;

        if (empty($oauth2ClientId) || empty($oauth2ClientSecret)) {
            throw new \BackendNotConfiguredException('OAuth2 client ID or secret is not set.');
        }

        return [
            'client_id' => $oauth2ClientId,
            'client_secret' => $oauth2ClientSecret,
            'grant_type' => self::GAI_GRANT_TYPE,
            'scope' => self::GAI_SCOPE,
        ];
    }

    /**
     * Checks if the access token is expired.
     *
     * @param array $accessTokenInfo The access token information.
     * @return bool True if the access token is expired, false otherwise.
     */
    public function isAccessTokenExpired(array $accessTokenInfo): bool
    {
        $currentTime = time();
        $isExpired = $accessTokenInfo['expires_at'] <= $currentTime;

        return $isTokenExpired = $isExpired || empty($accessTokenInfo['access_token']);
    }

    /**
     * Fetches the tenant ID.
     *
     * @return string|null The tenant ID.
     * @throws BackendNotConfiguredException If the tenant ID is not set.
     */
    public function getTenant(): string
    {
        $tenantSrn = null;
        $idmConfig = new IdmConfig(\SugarConfig::getInstance());

        if (!$idmConfig->isIDMModeEnabled()) {
            throw new BackendNotConfiguredException('This method works only in IDM mode');
        }

        $idmModeConfig = $idmConfig->getIDMModeConfig();

        if ($idmModeConfig && !empty($idmModeConfig['tid'])) {
            $tenantSrn = $idmModeConfig['tid'];
        }

        return $tenantSrn;
    }

    /**
     * Fetches the STS URL from the IDM config.
     *
     * @return string The STS URL.
     * @throws BackendNotConfiguredException If the STS URL is not set.
     */
    protected function getSTSUrl(): string
    {
        $idpConfig = new IdmConfig(\SugarConfig::getInstance());
        $idmModeConfig = $idpConfig->getIDMModeConfig();

        if (!$idpConfig->isIDMModeEnabled()) {
            throw new BackendNotConfiguredException('IDP is not enabled, cannot retrieve stsUrl');
        }

        if (empty($idmModeConfig['stsUrl'])) {
            throw new BackendNotConfiguredException('stsUrl is missing from IDM mode config');
        }

        return rtrim($idmModeConfig['stsUrl'], '/') . '/';
    }

    /**
     * Fetches the access token from the GAI API.
     *
     * @param array $options The options for the request.
     * @return array The response from the API.
     * @param bool $testingAccessToken If true, uses the ConfigurationOnPremAdapter for testing the access token.
     * @throws \RuntimeException If the access token is not found in the response.
     */
    public function fetchAccessTokenInfo($options, $testingAccessToken = false): array
    {
        $stsUrl = $this->getSTSUrl();

        $this->client = new Client($stsUrl, $this->getMaxRetries(), $testingAccessToken);
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];

        $response = $this->client->callAccesToken('POST', 'oauth2/token', $options, 'form_params', $headers);
        $responseBody = json_decode($response->getBody(), true);

        if (!isset($responseBody['access_token'])) {
            throw new \RuntimeException('Access token not found in response.');
        }

        return $responseBody;
    }
}
