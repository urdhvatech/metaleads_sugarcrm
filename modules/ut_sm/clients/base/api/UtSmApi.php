<?php
/**
 * This file is part of the "Meta Leads" package.
 *
 * @package Meta Leads
 * @author Urdhva Tech <contact@urdhva-tech.com>
 * @link https://www.urdhva-tech.com
 * @copyright Urdhva Tech
 * @license As specified in the License Agreement supplied with this package.
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

/**
 * Sidecar admin API for Meta Leads settings, connected accounts, and license.
 */
class UtSmApi extends SugarApi
{
    /**
     * {@inheritDoc}
     */
    public function registerApiRest()
    {
        return array(
            'getSettings' => array(
                'reqType' => 'GET',
                'path' => array('ut_sm', 'settings'),
                'pathVars' => array('module', ''),
                'method' => 'getSettings',
                'shortHelp' => 'Returns Meta Leads OAuth and connected-account settings',
                'minVersion' => '11.0',
            ),
            'saveSettings' => array(
                'reqType' => 'POST',
                'path' => array('ut_sm', 'settings'),
                'pathVars' => array('module', ''),
                'method' => 'saveSettings',
                'shortHelp' => 'Saves Meta Leads OAuth and webhook settings',
                'minVersion' => '11.0',
            ),
            'refreshTokens' => array(
                'reqType' => 'POST',
                'path' => array('ut_sm', 'refreshTokens'),
                'pathVars' => array('module', ''),
                'method' => 'refreshTokens',
                'shortHelp' => 'Refreshes the Meta user token and re-syncs pages',
                'minVersion' => '11.0',
            ),
            'disconnect' => array(
                'reqType' => 'POST',
                'path' => array('ut_sm', 'disconnect'),
                'pathVars' => array('module', ''),
                'method' => 'disconnect',
                'shortHelp' => 'Disconnects the Meta app and clears stored tokens',
                'minVersion' => '11.0',
            ),
            'reconcile' => array(
                'reqType' => 'POST',
                'path' => array('ut_sm', 'reconcile'),
                'pathVars' => array('module', ''),
                'method' => 'reconcile',
                'shortHelp' => 'Runs Meta lead reconciliation immediately',
                'minVersion' => '11.0',
            ),
            'getAccountSettings' => array(
                'reqType' => 'GET',
                'path' => array('ut_sm', 'account', '?'),
                'pathVars' => array('module', '', 'id'),
                'method' => 'getAccountSettings',
                'shortHelp' => 'Returns assignment, forms, and field mapping for a connected account',
                'minVersion' => '11.0',
            ),
            'saveAccountSettings' => array(
                'reqType' => 'PUT',
                'path' => array('ut_sm', 'account', '?'),
                'pathVars' => array('module', '', 'id'),
                'method' => 'saveAccountSettings',
                'shortHelp' => 'Saves assignment, enabled forms, and field mapping',
                'minVersion' => '11.0',
            ),
            'refreshAccountForms' => array(
                'reqType' => 'POST',
                'path' => array('ut_sm', 'account', '?', 'refreshForms'),
                'pathVars' => array('module', '', 'id', ''),
                'method' => 'refreshAccountForms',
                'shortHelp' => 'Reloads Meta lead forms for a connected account',
                'minVersion' => '11.0',
            ),
            'getLicense' => array(
                'reqType' => 'GET',
                'path' => array('ut_sm', 'license'),
                'pathVars' => array('module', ''),
                'method' => 'getLicense',
                'shortHelp' => 'Returns the Meta Leads license screen state',
                'minVersion' => '11.0',
            ),
            'validateLicense' => array(
                'reqType' => 'POST',
                'path' => array('ut_sm', 'license', 'validate'),
                'pathVars' => array('module', '', ''),
                'method' => 'validateLicense',
                'shortHelp' => 'Validates a Meta Leads license key',
                'minVersion' => '11.0',
            ),
            'changeLicenseUsers' => array(
                'reqType' => 'POST',
                'path' => array('ut_sm', 'license', 'change'),
                'pathVars' => array('module', '', ''),
                'method' => 'changeLicenseUsers',
                'shortHelp' => 'Changes the licensed user count for Meta Leads',
                'minVersion' => '11.0',
            ),
            'saveLicensedUsers' => array(
                'reqType' => 'POST',
                'path' => array('ut_sm', 'license', 'users'),
                'pathVars' => array('module', '', ''),
                'method' => 'saveLicensedUsers',
                'shortHelp' => 'Saves which users are licensed for Meta Leads',
                'minVersion' => '11.0',
            ),
        );
    }

    /**
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function getSettings(ServiceBase $api, array $args)
    {
        $this->ensureAdmin();
        $this->ensureLicensed();

        return $this->buildSettingsPayload();
    }

    /**
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function saveSettings(ServiceBase $api, array $args)
    {
        $this->ensureAdmin();
        $this->ensureLicensed();

        $postedAppId = isset($args['app_id']) ? trim((string) $args['app_id']) : '';
        $postedAppSecret = isset($args['app_secret']) ? trim((string) $args['app_secret']) : '';
        $postedRedirectUri = isset($args['oauth_redirect_uri']) ? trim((string) $args['oauth_redirect_uri']) : '';
        $postedCallbackUrl = isset($args['callback_url']) ? trim((string) $args['callback_url']) : '';
        $postedVerifyToken = isset($args['verify_token']) ? trim((string) $args['verify_token']) : '';
        $postedReconciliationHours = isset($args['reconciliation_hours']) ? (int) $args['reconciliation_hours'] : 24;

        if ($postedAppId === '' || $postedAppSecret === '' || $postedRedirectUri === '' || $postedCallbackUrl === '' || $postedVerifyToken === '') {
            throw new SugarApiExceptionInvalidParameter($this->lbl(
                'LBL_UT_SM_REQUIRED_FIELDS_MISSING',
                'App ID, App Secret, Redirect URI, Callback URL and Verify Token are required.'
            ));
        }
        
        $isValidRedirect = (bool) filter_var($postedRedirectUri, FILTER_VALIDATE_URL);
        $isValidCallback = (bool) filter_var($postedCallbackUrl, FILTER_VALIDATE_URL);
        $isValidVerifyToken = (bool) preg_match('/^[A-Za-z0-9._-]{3,255}$/', $postedVerifyToken);
        if (!$isValidRedirect || !$isValidCallback || !$isValidVerifyToken) {
            throw new SugarApiExceptionInvalidParameter($this->lbl(
                'LBL_UT_SM_CALLBACK_OR_VERIFY_INVALID',
                "The callback URL or verify token couldn't be validated. Please verify the provided information or try again later."
            ));
        }

        if ($postedReconciliationHours < 1) {
            $postedReconciliationHours = 24;
        }
        if ($postedReconciliationHours > 168) {
            $postedReconciliationHours = 168;
        }

        require_once 'modules/Administration/Administration.php';
        require_once 'modules/ut_sm/services/TokenService.php';
        require_once 'modules/ut_sm/services/ReconciliationService.php';

        $admin = new Administration();
        $admin->saveSetting('ut_sm', 'app_id', $postedAppId);
        $admin->saveSetting('ut_sm', 'app_secret', $postedAppSecret);
        $admin->saveSetting('ut_sm', 'oauth_redirect_uri', $postedRedirectUri);
        $admin->saveSetting('ut_sm', 'callback_url', $postedCallbackUrl);
        $admin->saveSetting('ut_sm', 'verify_token', $postedVerifyToken);
        $admin->saveSetting('ut_sm', 'reconciliation_hours', (string) $postedReconciliationHours);

        $tokenService = new UTSMTokenService();
        $tokenService->ensureScheduler();
        $reconciliationService = new UTSMReconciliationService();
        $reconciliationService->ensureScheduler();

        $payload = $this->buildSettingsPayload();
        $payload['message'] = $this->lbl('LBL_UT_SM_OAUTH_SETTINGS_SAVED', 'OAuth settings saved successfully.');

        return $payload;
    }

    /**
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function refreshTokens(ServiceBase $api, array $args)
    {
        $this->ensureAdmin();
        $this->ensureLicensed();

        require_once 'modules/ut_sm/services/TokenService.php';
        $tokenService = new UTSMTokenService();
        $refresh = $tokenService->refreshIfNeeded(true);
        if (empty($refresh['ok'])) {
            throw new SugarApiExceptionError(
                $this->lbl('LBL_UT_SM_TOKEN_REFRESH_FAILED', 'Token refresh failed:') . ' '
                . (!empty($refresh['error']) ? $refresh['error'] : '')
            );
        }

        $count = isset($refresh['count']) ? (int) $refresh['count'] : 0;
        $payload = $this->buildSettingsPayload();
        $payload['message'] = $this->lbl('LBL_UT_SM_TOKEN_REFRESH_SUCCESS', 'Tokens refreshed and pages re-synced:') . ' ' . $count . '.';

        return $payload;
    }

    /**
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function disconnect(ServiceBase $api, array $args)
    {
        $this->ensureAdmin();
        $this->ensureLicensed();

        require_once 'modules/ut_sm/services/TokenService.php';
        $tokenService = new UTSMTokenService();
        $tokenService->disconnect();

        $payload = $this->buildSettingsPayload();
        $payload['message'] = $this->lbl('LBL_UT_SM_APP_DISCONNECTED_SUCCESS', 'App disconnected successfully.');

        return $payload;
    }

    /**
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function reconcile(ServiceBase $api, array $args)
    {
        $this->ensureAdmin();
        $this->ensureLicensed();

        require_once 'modules/ut_sm/services/ReconciliationService.php';
        $reconciliation = new UTSMReconciliationService();
        $result = $reconciliation->run();
        if (empty($result['ok'])) {
            if (!empty($result['error']) && $result['error'] === 'License is not valid') {
                throw new SugarApiExceptionNotAuthorized('LICENSE_REQUIRED');
            }
            throw new SugarApiExceptionError(
                $this->lbl('LBL_UT_SM_RECONCILIATION_FAILED', 'Reconciliation failed:') . ' '
                . (!empty($result['error']) ? $result['error'] : 'unknown')
            );
        }

        $stats = isset($result['stats']) ? $result['stats'] : array();
        $payload = $this->buildSettingsPayload();
        $payload['message'] = $this->lbl('LBL_UT_SM_RECONCILIATION_SUCCESS', 'Reconciliation complete.')
            . ' ' . $this->lbl('LBL_UT_SM_RECONCILIATION_CHECKED', 'Checked:') . ' ' . (isset($stats['checked']) ? (int) $stats['checked'] : 0)
            . ', ' . $this->lbl('LBL_UT_SM_RECONCILIATION_IMPORTED', 'Imported:') . ' ' . (isset($stats['imported']) ? (int) $stats['imported'] : 0)
            . ', ' . $this->lbl('LBL_UT_SM_RECONCILIATION_DUPLICATES', 'Duplicates:') . ' ' . (isset($stats['duplicate']) ? (int) $stats['duplicate'] : 0)
            . ', ' . $this->lbl('LBL_UT_SM_RECONCILIATION_FAILED_COUNT', 'Failed:') . ' ' . (isset($stats['failed']) ? (int) $stats['failed'] : 0);

        return $payload;
    }

    /**
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function getAccountSettings(ServiceBase $api, array $args)
    {
        $this->ensureAdmin();
        $this->ensureLicensed();

        $accountId = isset($args['id']) ? trim((string) $args['id']) : '';
        return $this->buildAccountPayload($accountId);
    }

    /**
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function saveAccountSettings(ServiceBase $api, array $args)
    {
        $this->ensureAdmin();
        $this->ensureLicensed();

        $accountId = isset($args['id']) ? trim((string) $args['id']) : '';
        require_once 'modules/ut_sm/services/AccountConfigService.php';
        $configService = new UTSMAccountConfigService();
        $save = $configService->saveConfiguration($accountId, array(
            'assignment_type' => isset($args['assignment_type']) ? $args['assignment_type'] : 'keep_empty',
            'assignment_user_id' => isset($args['assignment_user_id']) ? $args['assignment_user_id'] : '',
            'assignment_group_id' => isset($args['assignment_group_id']) ? $args['assignment_group_id'] : '',
            'rr_user_ids' => isset($args['rr_user_ids']) && is_array($args['rr_user_ids']) ? $args['rr_user_ids'] : array(),
            'enabled_form_ids' => isset($args['enabled_form_ids']) && is_array($args['enabled_form_ids']) ? $args['enabled_form_ids'] : array(),
            'field_map' => isset($args['field_map']) && is_array($args['field_map']) ? $args['field_map'] : array(),
        ));

        if (empty($save['ok'])) {
            throw new SugarApiExceptionInvalidParameter(
                !empty($save['error']) ? $save['error'] : $this->lbl('LBL_UT_SM_CONFIG_SAVE_FAILED', 'Could not save configuration.')
            );
        }

        $payload = $this->buildAccountPayload($accountId);
        $payload['message'] = $this->lbl('LBL_UT_SM_CONFIG_SAVED', 'Lead settings saved successfully.');

        return $payload;
    }

    /**
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function refreshAccountForms(ServiceBase $api, array $args)
    {
        $this->ensureAdmin();
        $this->ensureLicensed();

        $accountId = isset($args['id']) ? trim((string) $args['id']) : '';
        require_once 'modules/ut_sm/services/AccountConfigService.php';
        $configService = new UTSMAccountConfigService();
        $account = $configService->getAccountById($accountId);
        if (empty($account)) {
            throw new SugarApiExceptionNotFound($this->lbl('LBL_UT_SM_ACCOUNT_NOT_FOUND', 'Connected account not found.'));
        }

        $refresh = $configService->refreshForms($account);
        if (empty($refresh['ok'])) {
            throw new SugarApiExceptionError(
                $this->lbl('LBL_UT_SM_FORMS_REFRESH_FAILED', 'Could not refresh forms:') . ' '
                . (!empty($refresh['error']) ? $refresh['error'] : '')
            );
        }

        $count = isset($refresh['count']) ? (int) $refresh['count'] : 0;
        $payload = $this->buildAccountPayload($accountId);
        $payload['message'] = $this->lbl('LBL_UT_SM_FORMS_REFRESH_SUCCESS', 'Forms refreshed:') . ' ' . $count;

        return $payload;
    }

    /**
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function getLicense(ServiceBase $api, array $args)
    {
        $this->ensureAdmin();

        return $this->buildLicensePayload();
    }

    /**
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function validateLicense(ServiceBase $api, array $args)
    {
        $this->ensureAdmin();
        $this->ensureCurl();

        $key = isset($args['key']) ? trim((string) $args['key']) : '';
        if ($key === '') {
            throw new SugarApiExceptionMissingParameter('Key is required.');
        }
        
        require_once 'modules/ut_sm/license/SugarAILicense.php';
        require 'modules/ut_sm/license/config.php';
        require_once 'modules/Administration/Administration.php';

        $validated = UT_SM_SugarAILicense::doValidate('ut_sm', $key);
        $administration = new Administration();
        $administration->saveSetting(
            'SugarOutfitters',
            $outfitters_config['shortname'],
            base64_encode(serialize(array(
                'last_ran' => time(),
                'last_result' => $validated,
            )))
        );

        if (empty($validated['success'])) {
            $message = 'Invalid key';
            if (!empty($validated['result']) && is_string($validated['result'])) {
                $message = $validated['result'];
            } elseif (!empty($validated['result']['message'])) {
                $message = $validated['result']['message'];
            }
            throw new SugarApiExceptionInvalidParameter($message);
        }

        $administration->saveSetting('SugarOutfitters', 'lic_' . $outfitters_config['shortname'], $key);

        $payload = $this->buildLicensePayload();
        $result = isset($validated['result']) && is_array($validated['result']) ? $validated['result'] : array();
        $payload['validated'] = !empty($result['validated']);
        $payload['validated_users'] = !empty($result['validated_users']);
        $payload['licensed_user_count'] = isset($result['licensed_user_count']) ? $result['licensed_user_count'] : '';
        $payload['message'] = 'License validated successfully.';

        return $payload;
    }

    /**
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function changeLicenseUsers(ServiceBase $api, array $args)
    {
        $this->ensureAdmin();
        $this->ensureCurl();

        $key = isset($args['key']) ? trim((string) $args['key']) : '';
        $userCount = isset($args['user_count']) ? (int) $args['user_count'] : 0;
        if ($key === '') {
            throw new SugarApiExceptionMissingParameter('Key is required.');
        }
        if ($userCount < 1) {
            throw new SugarApiExceptionMissingParameter('User count is required.');
        }

        require_once 'modules/ut_sm/license/SugarAILicense.php';
        require 'modules/ut_sm/license/config.php';
        require_once 'modules/Administration/Administration.php';

        $response = SugarOutfitters_API::call('ut_sm', 'key/change', array(
            'key' => $key,
            'user_count' => $userCount,
        ));

        if (empty($response['success'])) {
            $message = 'Unexpected data returned from the server.';
            if (!empty($response['result']) && is_string($response['result'])) {
                $message = $response['result'];
            }
            throw new SugarApiExceptionInvalidParameter($message);
        }

        $administration = new Administration();
        $administration->saveSetting('SugarOutfitters', 'lic_' . $outfitters_config['shortname'], $key);

        $payload = $this->buildLicensePayload();
        $result = isset($response['result']) && is_array($response['result']) ? $response['result'] : array();
        $payload['licensed_user_count'] = isset($result['licensed_user_count']) ? $result['licensed_user_count'] : $userCount;
        $payload['validated'] = true;
        $payload['validated_users'] = true;
        $payload['message'] = 'License updated.';

        return $payload;
    }

    /**
     * @param ServiceBase $api
     * @param array $args
     * @return array
     */
    public function saveLicensedUsers(ServiceBase $api, array $args)
    {
        $this->ensureAdmin();

        $licensedUsers = isset($args['licensed_users']) && is_array($args['licensed_users']) ? $args['licensed_users'] : array();
        if (empty($licensedUsers)) {
            throw new SugarApiExceptionInvalidParameter('No additional licenses were set to be added.');
        }

        require_once 'modules/ut_sm/license/SugarAILicense.php';
        require 'modules/ut_sm/license/config.php';

        $response = UT_SM_SugarAILicense::doValidate('ut_sm');
        if (empty($response['success']) || empty($response['result']['validated'])) {
            throw new SugarApiExceptionInvalidParameter('The license key could not validate. Please check the key and re-validate.');
        }

        if (!empty($outfitters_config['validate_users'])) {
            if (empty($response['result']['validated_users'])) {
                throw new SugarApiExceptionInvalidParameter('Insuffient number of user licenses. Please add additional user licenses and try again.');
            }
        }

        $db = DBManagerFactory::getInstance();
        if (!$db->tableExists('so_users')) {
            throw new SugarApiExceptionError('Licensed user storage is not available.');
        }

        $fieldDefs = array(
            'id' => array('name' => 'id', 'type' => 'id', 'required' => true),
            'deleted' => array('name' => 'deleted', 'type' => 'bool'),
            'shortname' => array('name' => 'shortname', 'type' => 'varchar', 'len' => 255),
            'user_id' => array('name' => 'user_id', 'type' => 'id'),
        );

        $db->query(
            "DELETE FROM so_users WHERE shortname = '" . $db->quote($outfitters_config['shortname']) . "'",
            true,
            'Unable to reset licensed users'
        );
        foreach ($licensedUsers as $licensedUser) {
            $licensedUser = trim((string) $licensedUser);
            if ($licensedUser === '') {
                continue;
            }
            $db->insertParams('so_users', $fieldDefs, array(
                'id' => create_guid(),
                'shortname' => $outfitters_config['shortname'],
                'user_id' => $licensedUser,
                'deleted' => 0,
            ));
        }

        $payload = $this->buildLicensePayload();
        $payload['message'] = 'Users saved successfully.';

        return $payload;
    }

    /**
     * @return array
     */
    protected function buildSettingsPayload()
    {
        require_once 'modules/Administration/Administration.php';
        require_once 'modules/ut_sm/services/TokenService.php';
        require_once 'modules/ut_sm/services/AccountConfigService.php';
        require_once 'modules/ut_sm/services/ReconciliationService.php';

        $admin = new Administration();
        $admin->retrieveSettings('ut_sm');

        $appId = isset($admin->settings['ut_sm_app_id']) ? (string) $admin->settings['ut_sm_app_id'] : '';
        $appSecret = isset($admin->settings['ut_sm_app_secret']) ? (string) $admin->settings['ut_sm_app_secret'] : '';
        $savedRedirectUri = isset($admin->settings['ut_sm_oauth_redirect_uri']) ? (string) $admin->settings['ut_sm_oauth_redirect_uri'] : '';
        $accessToken = isset($admin->settings['ut_sm_access_token']) ? (string) $admin->settings['ut_sm_access_token'] : '';
        $tokenExpiresAt = isset($admin->settings['ut_sm_token_expires_at']) ? (string) $admin->settings['ut_sm_token_expires_at'] : '';
        $callbackUrl = isset($admin->settings['ut_sm_callback_url']) ? (string) $admin->settings['ut_sm_callback_url'] : '';
        $verifyToken = isset($admin->settings['ut_sm_verify_token']) ? (string) $admin->settings['ut_sm_verify_token'] : '';
        $reconciliationHours = isset($admin->settings['ut_sm_reconciliation_hours'])
            ? (int) $admin->settings['ut_sm_reconciliation_hours']
            : UTSMReconciliationService::DEFAULT_LOOKBACK_HOURS;
        if ($reconciliationHours < 1) {
            $reconciliationHours = UTSMReconciliationService::DEFAULT_LOOKBACK_HOURS;
        }

        $defaultRedirectUri = rtrim($GLOBALS['sugar_config']['site_url'], '/') . '/index.php?entryPoint=ut_sm_oauth_handler';
        $redirectUri = $savedRedirectUri !== '' ? $savedRedirectUri : $defaultRedirectUri;

        $tokenService = new UTSMTokenService();
        $tokenService->ensureScheduler();
        $reconciliationService = new UTSMReconciliationService();
        $reconciliationService->ensureScheduler();
        $reconciliation = $reconciliationService->getLastRunSummary();

        $configService = new UTSMAccountConfigService();
        $connectedAccounts = $configService->listConnectedAccounts();
        foreach ($connectedAccounts as &$account) {
            $account['is_instagram'] = (!empty($account['account_type']) && $account['account_type'] === 'instagram');
        }
        unset($account);

        $oauthUrl = '';
        if ($appId !== '' && $redirectUri !== '') {
            // The OAuth state has to be stored on the browser session. REST
            // requests use the OAuth token as their session id, so the state
            // is created when this entry point starts authorization.
            $oauthUrl = rtrim($GLOBALS['sugar_config']['site_url'], '/')
                . '/index.php?entryPoint=ut_sm_oauth_handler&action_param=authorize';
        }

        $isConnected = $accessToken !== '';

        return array(
            'app_id' => $appId,
            'app_secret' => $appSecret,
            'oauth_redirect_uri' => $redirectUri,
            'callback_url' => $callbackUrl,
            'verify_token' => $verifyToken,
            'reconciliation_hours' => $reconciliationHours,
            'reconciliation' => $reconciliation,
            'has_app_credentials' => ($appId !== '' && $appSecret !== ''),
            'is_connected' => $isConnected,
            'token_expires_at' => $tokenExpiresAt !== '' ? $tokenExpiresAt . ' UTC' : '',
            'token_needs_refresh' => $isConnected && $tokenService->needsRefresh(),
            'oauth_url' => $oauthUrl,
            'connected_accounts' => $connectedAccounts,
        );
    }

    /**
     * @param string $accountId
     * @return array
     */
    protected function buildAccountPayload($accountId)
    {
        require_once 'modules/ut_sm/services/AccountConfigService.php';
        $configService = new UTSMAccountConfigService();
        $account = $configService->getAccountById($accountId);
        if (empty($account)) {
            throw new SugarApiExceptionNotFound($this->lbl('LBL_UT_SM_ACCOUNT_NOT_FOUND', 'Connected account not found.'));
        }

        $assignmentType = !empty($account['assignment_type']) ? $account['assignment_type'] : 'keep_empty';
        $rrUserIds = $configService->getAssignmentService()->decodeUserIds(
            !empty($account['assignment_rr_user_ids']) ? $account['assignment_rr_user_ids'] : ''
        );
        $rrSelected = array();
        foreach ($rrUserIds as $uid) {
            $rrSelected[(string) $uid] = true;
        }

        $savedUserId = !empty($account['assignment_user_id']) ? (string) from_html($account['assignment_user_id']) : '';
        $users = get_user_array(false, 'Active');
        $userOptions = array();
        if (is_array($users)) {
            foreach ($users as $uid => $uname) {
                $uid = (string) $uid;
                if ($uid === '') {
                    continue;
                }
                $userOptions[] = array(
                    'id' => $uid,
                    'name' => $uname,
                    'rr_checked' => !empty($rrSelected[$uid]),
                    'selected' => ($savedUserId !== '' && $savedUserId === $uid),
                );
            }
        }

        $savedGroupId = !empty($account['assignment_group_id']) ? (string) from_html($account['assignment_group_id']) : '';
        $securityGroups = $this->listSecurityGroups($savedGroupId);

        $crmFieldOptions = $configService->getFieldMappingService()->getCrmFieldOptions();
        $formsWithMappings = $configService->getFormsWithMappings($account);
        foreach ($formsWithMappings as &$formRow) {
            if (empty($formRow['mappings']) || !is_array($formRow['mappings'])) {
                $formRow['mappings'] = array();
                continue;
            }
            foreach ($formRow['mappings'] as &$mapRow) {
                $selected = isset($mapRow['crm_field']) ? (string) $mapRow['crm_field'] : '';
                $opts = array();
                foreach ($crmFieldOptions as $opt) {
                    $opts[] = array(
                        'value' => $opt['value'],
                        'label' => $opt['label'],
                        'selected' => ((string) $opt['value'] === $selected),
                    );
                }
                $mapRow['crm_options'] = $opts;
            }
            unset($mapRow);
        }
        unset($formRow);

        $isInstagram = (!empty($account['account_type']) && $account['account_type'] === 'instagram');

        return array(
            'id' => $accountId,
            'page_name' => from_html($account['page_name']),
            'account_type' => !empty($account['account_type']) ? $account['account_type'] : 'facebook',
            'is_instagram' => $isInstagram,
            'assignment_type' => $assignmentType,
            'assignment_user_id' => $savedUserId,
            'assignment_group_id' => $savedGroupId,
            'users' => $userOptions,
            'security_groups' => $securityGroups,
            'forms' => $configService->getFormsForAccount($accountId),
            'forms_with_mappings' => $formsWithMappings,
        );
    }

    /**
     * @param string $savedGroupId
     * @return array
     */
    protected function listSecurityGroups($savedGroupId)
    {
        $groups = array();
        $db = DBManagerFactory::getInstance();
        if (!$db->tableExists('securitygroups')) {
            return $groups;
        }

        $sgRes = $db->query('SELECT id, name FROM securitygroups WHERE deleted = 0 ORDER BY name ASC');
        while ($sgRow = $db->fetchByAssoc($sgRes)) {
            $groupId = (string) from_html($sgRow['id']);
            $groups[] = array(
                'id' => $groupId,
                'name' => from_html($sgRow['name']),
                'selected' => ($savedGroupId !== '' && $savedGroupId === $groupId),
            );
        }

        return $groups;
    }

    /**
     * @return array
     */
    protected function buildLicensePayload()
    {
        require_once 'modules/ut_sm/license/SugarAILicense.php';
        require 'modules/ut_sm/license/config.php';
        require_once 'modules/ut_sm/services/LicenseService.php';

        $validateUsers = !empty($outfitters_config['validate_users']);
        $manageUsers = !empty($outfitters_config['manage_licensed_users']);
        $currentUsers = 0;
        if ($validateUsers || $manageUsers) {
            $activeUsers = get_user_array(false, 'Active', '', false, '', ' AND portal_only=0 AND is_group=0');
            $currentUsers = is_array($activeUsers) ? count($activeUsers) : 0;
        }

        $licensedUserIds = array();
        $db = DBManagerFactory::getInstance();
        if ($manageUsers && $db->tableExists('so_users')) {
            $result = $db->query(
                "SELECT user_id FROM so_users WHERE shortname = '" . $db->quote($outfitters_config['shortname']) . "'"
            );
            while ($row = $db->fetchByAssoc($result)) {
                if (!empty($row['user_id'])) {
                    $licensedUserIds[] = (string) $row['user_id'];
                }
            }
        }

        $allUsers = array();
        if ($manageUsers) {
            $userRows = get_user_array(false, 'Active', '', false, '', ' AND is_group=0');
            if (is_array($userRows)) {
                foreach ($userRows as $uid => $uname) {
                    $uid = (string) $uid;
                    if ($uid === '') {
                        continue;
                    }
                    $allUsers[] = array(
                        'id' => $uid,
                        'name' => $uname,
                        'licensed' => in_array($uid, $licensedUserIds, true),
                    );
                }
            }
        }

        $key = UT_SM_SugarAILicense::getKey('ut_sm');

        return array(
            'license_key' => $key ? (string) $key : '',
            'is_valid' => UTSMLicenseService::isValid(),
            'validate_users' => $validateUsers,
            'manage_licensed_users' => $manageUsers,
            'current_users' => $currentUsers,
            'licensed_user_ids' => $licensedUserIds,
            'users' => $allUsers,
            'continue_route' => 'ut_sm/settings',
            'strings' => $this->loadLicenseStrings(),
        );
    }

    /**
     * @return array
     */
    protected function loadLicenseStrings()
    {
        global $sugar_config;

        $currentLanguage = !empty($GLOBALS['current_language']) ? $GLOBALS['current_language'] : 'en_us';
        $defaultLanguage = !empty($sugar_config['default_language']) ? $sugar_config['default_language'] : 'en_us';
        $langs = array();
        if ($currentLanguage !== 'en_us') {
            $langs[] = 'en_us';
        }
        if ($defaultLanguage !== 'en_us' && $currentLanguage !== $defaultLanguage) {
            $langs[] = $defaultLanguage;
        }
        $langs[] = $currentLanguage;

        $licenseStrings = array();
        foreach ($langs as $lang) {
            $license_strings = array();
            $file = 'modules/ut_sm/license/language/' . $lang . '.lang.php';
            if (is_file($file)) {
                include $file;
            }
            if (!empty($license_strings) && is_array($license_strings)) {
                $licenseStrings = array_merge($licenseStrings, $license_strings);
            }
        }

        return $licenseStrings;
    }

    protected function ensureAdmin()
    {
        global $current_user;
        if (empty($current_user) || !is_admin($current_user)) {
            throw new SugarApiExceptionNotAuthorized($this->lbl('LBL_UT_SM_ADMIN_ACCESS_REQUIRED', 'Admin access required'));
        }
    }

    protected function ensureLicensed()
    {
        require_once 'modules/ut_sm/services/LicenseService.php';
        if (!UTSMLicenseService::isValid()) {
            throw new SugarApiExceptionNotAuthorized('LICENSE_REQUIRED');
        }
    }

    protected function ensureCurl()
    {
        if (!function_exists('curl_init')) {
            $message = translate('ERR_ENABLE_CURL', 'Administration');
            if ($message === 'ERR_ENABLE_CURL' || $message === '') {
                $message = 'cURL is required to validate the license.';
            }
            throw new SugarApiExceptionError($message);
        }
    }

    /**
     * @param string $key
     * @param string $fallback
     * @return string
     */
    protected function lbl($key, $fallback)
    {
        $label = translate($key, 'ut_sm');
        if (!is_string($label) || $label === '' || $label === $key) {
            return $fallback;
        }

        return $label;
    }
}
