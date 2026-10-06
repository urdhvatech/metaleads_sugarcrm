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

use SugarOAuth2Server;

class TokenGenerator
{
    /**
     * The client id for the sudo token
     *
     * @var string
     */
    public const CLIENT_ID = 'sugar';

    /**
     * The platform for the sudo token
     *
     * @var string
     */
    public const PLATFORM = 'historicallydelta';

    /**
     * Build and return a token
     *
     * @return array
     */
    public function createToken(): array
    {
        global $current_user;

        try {
            // we need the current session_id to revert back to
            $sessionId = session_id();

            // Auth uses the REMOTE_ADDR later in the token generate process.
            // If it is not set, we will assign it to '' to prevent an error.
            if (!isset($_SERVER['REMOTE_ADDR'])) {
                $_SERVER['REMOTE_ADDR'] = '';
            }

            /**
             * Get a sudo token to be used on the CXP server or other external services
             * This changes the current session
             */
            $oauthServer = SugarOAuth2Server::getOAuth2Server();
            $sudoToken = $oauthServer->getSudoToken($current_user->user_name, self::CLIENT_ID, self::PLATFORM);

            // close the current sudo token
            session_write_close();

            // revert to the old session
            session_id($sessionId);
            session_start();

            if (!is_array($sudoToken)) {
                throw new \SugarApiException("Unable to create a Sugar token for Historically Delta process, sessionID: {$sessionId}");
            }

            return $sudoToken;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            throw new \SugarApiException(
                "Unable to create a Sugar token for Historically Delta process, sessionID: {$errorMessage}"
            );
        }
    }
}
