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

namespace SugarCRM\ExternalAPI\MicrosoftEmail;

use Http\Promise\Promise;
use Microsoft\Graph\Core\Authentication\GraphPhpLeagueAccessTokenProvider;
use Microsoft\Kiota\Authentication\Oauth\TokenRequestContext;
use Microsoft\Kiota\Authentication\Cache\AccessTokenCache;
use League\OAuth2\Client\Provider\AbstractProvider;

/**
 * Custom GraphPhpLeagueAccessTokenProvider that captures and persists refresh tokens
 */
class CustomGraphPhpLeagueAccessTokenProvider extends GraphPhpLeagueAccessTokenProvider
{
    /**
     * @var callable|null Callback to handle token refresh events
     */
    private $tokenRefreshCallback;

    /**
     * @var string|null EAPM ID for token cleanup on errors
     */
    private $eapmId;

    /**
     * @var object|null API instance for token cleanup on errors
     */
    private $apiInstance;

    /**
     * @param TokenRequestContext $tokenRequestContext
     * @param array<string> $scopes
     * @param string $nationalCloud
     * @param AccessTokenCache|null $accessTokenCache
     * @param AbstractProvider|null $oauthProvider
     * @param callable|null $tokenRefreshCallback Callback to handle refresh token updates
     * @param string|null $eapmId EAPM ID for error handling
     * @param object|null $apiInstance API instance for error handling
     */
    public function __construct(
        TokenRequestContext $tokenRequestContext,
        array $scopes = [],
        string $nationalCloud = 'https://graph.microsoft.com',
        ?AccessTokenCache $accessTokenCache = null,
        ?AbstractProvider $oauthProvider = null,
        ?callable $tokenRefreshCallback = null,
        ?string $eapmId = null,
        ?object $apiInstance = null
    ) {
        parent::__construct($tokenRequestContext, $scopes, $nationalCloud, $accessTokenCache, $oauthProvider);
        $this->tokenRefreshCallback = $tokenRefreshCallback;
        $this->eapmId = $eapmId;
        $this->apiInstance = $apiInstance;
    }

    /**
     * Override getAuthorizationTokenAsync to capture refresh tokens
     *
     * @param string $url
     * @param array $additionalAuthenticationContext
     * @return Promise
     */
    public function getAuthorizationTokenAsync(string $url, array $additionalAuthenticationContext = []): Promise
    {
        // Call parent method to get the promise
        $promise = parent::getAuthorizationTokenAsync($url, $additionalAuthenticationContext);

        // If we have a callback, attach handlers to the promise for both success and error cases
        if ($this->tokenRefreshCallback) {
            $promise = $promise->then(function ($result) {
                try {
                    // Get the token request context to access cache - it's in the parent class
                    $reflection = new \ReflectionClass('Microsoft\Kiota\Authentication\PhpLeagueAccessTokenProvider');
                    $tokenRequestContextProperty = $reflection->getProperty('tokenRequestContext');
                    $tokenRequestContextProperty->setAccessible(true);
                    $tokenRequestContext = $tokenRequestContextProperty->getValue($this);

                    // Get the access token cache
                    $accessTokenCache = $this->getAccessTokenCache();

                    // Try to get cached token after promise resolution
                    if ($tokenRequestContext->getCacheKey()) {
                        $cachedToken = $accessTokenCache->getAccessToken($tokenRequestContext->getCacheKey());

                        if ($cachedToken && $cachedToken->getRefreshToken()) {
                            // Prepare token data for callback
                            $expires = $cachedToken->getExpires();
                            $expiresAt = null;

                            if ($expires) {
                                if (is_int($expires)) {
                                    // If expires is already a timestamp
                                    $expiresAt = $expires;
                                } elseif ($expires instanceof \DateTime) {
                                    // If expires is a DateTime object
                                    $expiresAt = $expires->getTimestamp();
                                }
                            }

                            $tokenData = [
                                'access_token' => $cachedToken->getToken(),
                                'refresh_token' => $cachedToken->getRefreshToken(),
                                'expires_at' => $expiresAt,
                                'token_type' => 'Bearer',
                            ];

                            // Call the callback
                            call_user_func($this->tokenRefreshCallback, $tokenData);
                        }
                    }
                } catch (\Exception $e) {
                    // Silently handle errors in token capture - don't break normal flow
                }

                // Return the original result to maintain promise chain
                return $result;
            }, function ($error) {
                // Handle authentication errors
                $this->handleAuthenticationError($error);

                // Re-throw the error to maintain promise chain
                throw $error;
            });
        }

        return $promise;
    }

    /**
     * Handle authentication errors, particularly invalid_grant errors
     *
     * @param \Exception $error The authentication error
     */
    private function handleAuthenticationError(\Exception $error): void
    {
        try {
            $errorMessage = $error->getMessage();
            $GLOBALS['log']->warn("CustomGraphPhpLeagueAccessTokenProvider: Authentication error - {$errorMessage}");

            // Check if this is an invalid_grant error (expired/invalid refresh token)
            if (
                strpos($errorMessage, 'invalid_grant') !== false ||
                strpos($errorMessage, 'Invalid request') !== false ||
                strpos($errorMessage, 'AADSTS70008') !== false || // Expired refresh token
                strpos($errorMessage, 'AADSTS50173') !== false
            ) { // Fresh sign-in required
                $GLOBALS['log']->info(
                    "CustomGraphPhpLeagueAccessTokenProvider: Detected invalid/expired refresh token, clearing " .
                    "stored tokens"
                );

                // Clear the invalid tokens from SugarCRM
                $this->clearInvalidTokens();
            }
        } catch (\Exception $e) {
            // Don't let error handling break the main flow
            $GLOBALS['log']->error(
                "CustomGraphPhpLeagueAccessTokenProvider: Error in handleAuthenticationError - {$e->getMessage()}"
            );
        }
    }

    /**
     * Clear invalid tokens from SugarCRM EAPM
     */
    private function clearInvalidTokens(): void
    {
        if (!$this->eapmId || !$this->apiInstance) {
            $GLOBALS['log']->warning(
                "CustomGraphPhpLeagueAccessTokenProvider: Cannot clear tokens - missing eapmId or apiInstance"
            );
            return;
        }

        try {
            // Get the EAPM bean
            $eapmBean = $this->apiInstance->getEAPMBeanById($this->eapmId);

            if (!empty($eapmBean->id)) {
                // ignore non-Microsoft EAPM records to avoid unintended side effects
                if ($eapmBean->application !== 'Microsoft') {
                    return;
                }

                $tokenData = json_decode($eapmBean->api_data, true) ?? [];

                // Mark tokens as invalid but keep the structure for re-authorization
                $tokenData['refresh_token'] = null;
                $tokenData['access_token'] = null;
                $tokenData['expires_at'] = null;
                $tokenData['token_invalidated_at'] = time();
                $tokenData['requires_reauthorization'] = true;

                $eapmBean->api_data = json_encode($tokenData);
                $eapmBean->validated = false; // Mark as requiring re-validation
                $eapmBean->save();

                $GLOBALS['log']->info(
                    "CustomGraphPhpLeagueAccessTokenProvider: Successfully cleared invalid tokens for " .
                    "EAPM ID: {$this->eapmId}"
                );
            } else {
                $GLOBALS['log']->warning(
                    "CustomGraphPhpLeagueAccessTokenProvider: EAPM bean not found for ID: {$this->eapmId}"
                );
            }
        } catch (\Exception $e) {
            $GLOBALS['log']->error(
                "CustomGraphPhpLeagueAccessTokenProvider: Failed to clear invalid tokens for EAPM ID " .
                "{$this->eapmId}: " . $e->getMessage()
            );
        }
    }
}
