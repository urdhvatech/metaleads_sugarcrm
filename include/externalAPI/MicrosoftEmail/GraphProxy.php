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

use Microsoft\Graph\Core\NationalCloud;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Graph\Core\Authentication\GraphPhpLeagueAuthenticationProvider;
use SugarCRM\ExternalAPI\MicrosoftEmail\CustomGraphPhpLeagueAccessTokenProvider;

/**
 * A proxy class for the Microsoft Graph API with token persistence
 */
class GraphProxy extends GraphServiceClient
{
    private $authURL;
    private ?string $eapmId;
    private ?ExtAPIMicrosoftEmail $apiInstance;

    public function __construct(string $refreshToken, array $config, ?string $eapmId = null, ?ExtAPIMicrosoftEmail $apiInstance = null)
    {
        // Determine the correct tenant ID based on configuration
        $tenantId = 'common'; // Default to multi-tenant

        // Check if single tenant mode is enabled and properly configured
        $singleTenantEnabled = !empty($config['properties']['oauth2_single_tenant_enabled']);
        if ($singleTenantEnabled) {
            $configuredTenantId = trim($config['properties']['oauth2_single_tenant_id'] ?? '');
            if (!empty($configuredTenantId)) {
                $tenantId = $configuredTenantId;
            } else {
                // Log warning if single tenant is enabled but tenant ID is invalid
                LoggerManager::getLogger()->error(
                    'Single tenant mode enabled but tenant ID is invalid or empty: ' .
                    ($configuredTenantId ?: 'empty') . '. Falling back to multi-tenant mode.'
                );
            }
        }

        $this->eapmId = $eapmId;
        $this->apiInstance = $apiInstance;

        // Use the proven OnBehalfOfContextUsingRefreshToken for authentication
        $tokenRequestContext = new OnBehalfOfContextUsingRefreshToken(
            tenantId: $tenantId,
            clientId: $config['properties']['oauth2_client_id'] ?? '',
            clientSecret: $config['properties']['oauth2_client_secret'] ?? '',
            assertion: $refreshToken
        );

        // Create request adapter with custom access token provider for token capture
        if ($this->eapmId && $this->apiInstance) {
            // Create custom access token provider with callback and error handling
            $customAccessTokenProvider = new CustomGraphPhpLeagueAccessTokenProvider(
                $tokenRequestContext,
                [],
                NationalCloud::GLOBAL,
                null,
                null,
                [$this, 'handleTokenRefresh'],
                $this->eapmId,
                $this->apiInstance
            );

            // Create authentication provider using our custom access token provider
            $authProvider = GraphPhpLeagueAuthenticationProvider::createWithAccessTokenProvider($customAccessTokenProvider);

            // Create standard request adapter with custom auth provider
            $requestAdapter = new \Microsoft\Graph\GraphRequestAdapter($authProvider);
            $requestAdapter->setBaseUrl(NationalCloud::GLOBAL . '/v1.0');

            // Use the custom request adapter
            parent::__construct($tokenRequestContext, [], NationalCloud::GLOBAL, $requestAdapter);
        } else {
            // No token persistence needed, use default client
            parent::__construct($tokenRequestContext);
        }
    }

    /**
     * Handle token refresh events from the SDK
     */
    public function handleTokenRefresh(array $tokenData): void
    {
        if (!$this->eapmId || !$this->apiInstance) {
            return;
        }

        try {
            if (!empty($tokenData['refresh_token'])) {
                $this->apiInstance->saveRefreshTokenForEAPM($this->eapmId, $tokenData['refresh_token']);
            }

            // Also save access token and expiry
            if (!empty($tokenData['access_token'])) {
                $this->apiInstance->saveAccessTokenForEAPM($this->eapmId, $tokenData['access_token'], $tokenData['expires_at'] ?? null);
            }
        } catch (Exception $e) {
            $GLOBALS['log']->error("GraphProxy: Failed to save refreshed tokens for EAPM ID {$this->eapmId}: " . $e->getMessage());
        }
    }



    /**
     * Sets the authorization URL to be used by the front-end for authorizing
     * a user with Microsoft servers
     *
     * @param string $url the authorization URL to set
     */
    public function setAuthURL(string $url)
    {
        $this->authURL = $url;
    }

    /**
     * Returns the authorization URL to be used by the front-end for authorizing
     * a user with Microsoft servers. Named 'createAuthURL' rather than
     * 'getAuthURL' to keep consistency with other external API clients
     *
     * @return string|null the authorization URL
     */
    public function createAuthURL()
    {
        return $this->authURL;
    }
}
