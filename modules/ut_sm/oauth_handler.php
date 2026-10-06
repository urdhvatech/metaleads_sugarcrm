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
    define('sugarEntry', true);
    require_once 'include/entryPoint.php';
}

require_once 'modules/Administration/Administration.php';
require_once 'modules/ut_sm/services/TokenService.php';
require_once 'modules/ut_sm/services/ReconciliationService.php';

global $mod_strings;
$mod_strings = return_module_language($GLOBALS['current_language'], 'ut_sm');

function utSmLbl($key, $fallback = '')
{
    global $mod_strings;
    if (isset($mod_strings[$key]) && $mod_strings[$key] !== '') {
        return $mod_strings[$key];
    }
    return $fallback;
}

global $current_user;
if (empty($current_user) || !$current_user->is_admin) {
    sugar_die(utSmLbl('LBL_UT_SM_ADMIN_ACCESS_REQUIRED', 'Admin access required'));
}

require_once 'modules/ut_sm/services/LicenseService.php';
if (!UTSMLicenseService::isValid()) {
    SugarApplication::redirect('index.php?module=ut_sm&action=license');
    sugar_cleanup(true);
}

function utSmRedirectWithMessage($type, $message)
{
    $param = $type === 'error' ? 'oauth_error' : 'oauth_success';
    SugarApplication::redirect('index.php?module=ut_sm&action=settings&' . $param . '=' . urlencode($message));
}

function utSmValidateFormToken()
{
    $posted = isset($_POST['ut_sm_form_token']) ? (string) $_POST['ut_sm_form_token'] : '';
    $session = isset($_SESSION['ut_sm_form_token']) ? (string) $_SESSION['ut_sm_form_token'] : '';
    if ($session === '' || $posted === '' || !hash_equals($session, $posted)) {
        utSmRedirectWithMessage('error', utSmLbl('LBL_UT_SM_CSRF_INVALID', 'Invalid form token. Please try again.'));
    }
}

$admin = new Administration();
$admin->retrieveSettings('ut_sm');
$tokenService = new UTSMTokenService();

$appId = isset($admin->settings['ut_sm_app_id']) ? $admin->settings['ut_sm_app_id'] : '';
$appSecret = isset($admin->settings['ut_sm_app_secret']) ? $admin->settings['ut_sm_app_secret'] : '';
$savedRedirectUri = isset($admin->settings['ut_sm_oauth_redirect_uri']) ? $admin->settings['ut_sm_oauth_redirect_uri'] : '';
$redirectUri = !empty($savedRedirectUri) ? $savedRedirectUri : (rtrim($GLOBALS['sugar_config']['site_url'], '/') . '/index.php?entryPoint=ut_sm_oauth_handler');

if (!empty($_REQUEST['action_param']) && $_REQUEST['action_param'] === 'disconnect') {
    $tokenService->disconnect();
    utSmRedirectWithMessage('success', utSmLbl('LBL_UT_SM_APP_DISCONNECTED_SUCCESS', 'App disconnected successfully.'));
}

if (!empty($_REQUEST['action_param']) && $_REQUEST['action_param'] === 'refresh_tokens') {
    $refresh = $tokenService->refreshIfNeeded(true);
    if (!$refresh['ok']) {
        utSmRedirectWithMessage('error', utSmLbl('LBL_UT_SM_TOKEN_REFRESH_FAILED', 'Token refresh failed:') . ' ' . $refresh['error']);
    }
    $count = isset($refresh['count']) ? (int) $refresh['count'] : 0;
    utSmRedirectWithMessage(
        'success',
        utSmLbl('LBL_UT_SM_TOKEN_REFRESH_SUCCESS', 'Tokens refreshed and pages re-synced:') . ' ' . $count . '.'
    );
}

if (!empty($_REQUEST['action_param']) && $_REQUEST['action_param'] === 'reconcile_now') {
    $reconciliation = new UTSMReconciliationService();
    $result = $reconciliation->run();
    if (!$result['ok']) {
        if (!empty($result['error']) && $result['error'] === 'License is not valid') {
            SugarApplication::redirect('index.php?module=ut_sm&action=license');
            sugar_cleanup(true);
        }
        utSmRedirectWithMessage(
            'error',
            utSmLbl('LBL_UT_SM_RECONCILIATION_FAILED', 'Reconciliation failed:')
            . ' ' . (!empty($result['error']) ? $result['error'] : 'unknown')
        );
    }
    $stats = isset($result['stats']) ? $result['stats'] : array();
    $msg = utSmLbl('LBL_UT_SM_RECONCILIATION_SUCCESS', 'Reconciliation complete.')
        . ' ' . utSmLbl('LBL_UT_SM_RECONCILIATION_CHECKED', 'Checked:') . ' ' . (isset($stats['checked']) ? (int) $stats['checked'] : 0)
        . ', ' . utSmLbl('LBL_UT_SM_RECONCILIATION_IMPORTED', 'Imported:') . ' ' . (isset($stats['imported']) ? (int) $stats['imported'] : 0)
        . ', ' . utSmLbl('LBL_UT_SM_RECONCILIATION_DUPLICATES', 'Duplicates:') . ' ' . (isset($stats['duplicate']) ? (int) $stats['duplicate'] : 0)
        . ', ' . utSmLbl('LBL_UT_SM_RECONCILIATION_FAILED_COUNT', 'Failed:') . ' ' . (isset($stats['failed']) ? (int) $stats['failed'] : 0);
    utSmRedirectWithMessage('success', $msg);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_settings'])) {
    utSmValidateFormToken();

    $postedAppId = isset($_POST['app_id']) ? trim($_POST['app_id']) : '';
    $postedAppSecret = isset($_POST['app_secret']) ? trim($_POST['app_secret']) : '';
    $postedRedirectUri = isset($_POST['oauth_redirect_uri']) ? trim($_POST['oauth_redirect_uri']) : '';
    $postedCallbackUrl = isset($_POST['callback_url']) ? trim($_POST['callback_url']) : '';
    $postedVerifyToken = isset($_POST['verify_token']) ? trim($_POST['verify_token']) : '';
    $postedReconciliationHours = isset($_POST['reconciliation_hours']) ? (int) $_POST['reconciliation_hours'] : 24;

    if (empty($postedAppId) || empty($postedAppSecret) || empty($postedRedirectUri) || empty($postedCallbackUrl) || empty($postedVerifyToken)) {
        utSmRedirectWithMessage('error', utSmLbl('LBL_UT_SM_REQUIRED_FIELDS_MISSING', 'App ID, App Secret, Redirect URI, Callback URL and Verify Token are required.'));
    }

    $isValidRedirect = (bool) filter_var($postedRedirectUri, FILTER_VALIDATE_URL);
    $isValidCallback = (bool) filter_var($postedCallbackUrl, FILTER_VALIDATE_URL);
    $isValidVerifyToken = (bool) preg_match('/^[A-Za-z0-9._-]{3,255}$/', $postedVerifyToken);
    if (!$isValidRedirect || !$isValidCallback || !$isValidVerifyToken) {
        utSmRedirectWithMessage('error', utSmLbl('LBL_UT_SM_CALLBACK_OR_VERIFY_INVALID', "The callback URL or verify token couldn't be validated. Please verify the provided information or try again later."));
    }

    $admin->saveSetting('ut_sm', 'app_id', $postedAppId);
    $admin->saveSetting('ut_sm', 'app_secret', $postedAppSecret);
    $admin->saveSetting('ut_sm', 'oauth_redirect_uri', $postedRedirectUri);
    $admin->saveSetting('ut_sm', 'callback_url', $postedCallbackUrl);
    $admin->saveSetting('ut_sm', 'verify_token', $postedVerifyToken);
    if ($postedReconciliationHours < 1) {
        $postedReconciliationHours = 24;
    }
    if ($postedReconciliationHours > 168) {
        $postedReconciliationHours = 168;
    }
    $admin->saveSetting('ut_sm', 'reconciliation_hours', (string) $postedReconciliationHours);

    $tokenService->ensureScheduler();
    $reconciliationService = new UTSMReconciliationService();
    $reconciliationService->ensureScheduler();

    utSmRedirectWithMessage('success', utSmLbl('LBL_UT_SM_OAUTH_SETTINGS_SAVED', 'OAuth settings saved successfully.'));
}

if (!empty($_REQUEST['error_message']) || !empty($_REQUEST['error_description']) || !empty($_REQUEST['error'])) {
    $error = !empty($_REQUEST['error_message'])
        ? $_REQUEST['error_message']
        : (!empty($_REQUEST['error_description']) ? $_REQUEST['error_description'] : $_REQUEST['error']);
    utSmRedirectWithMessage('error', $error);
}

$code = isset($_REQUEST['code']) ? trim($_REQUEST['code']) : '';
if (empty($code)) {
    utSmRedirectWithMessage('error', utSmLbl('LBL_UT_SM_OAUTH_CODE_MISSING', 'OAuth code was not received.'));
}

// Validate OAuth state
$state = isset($_REQUEST['state']) ? (string) $_REQUEST['state'] : '';
$sessionState = isset($_SESSION['ut_sm_oauth_state']) ? (string) $_SESSION['ut_sm_oauth_state'] : '';
unset($_SESSION['ut_sm_oauth_state']);
if ($sessionState === '' || $state === '' || !hash_equals($sessionState, $state)) {
    utSmRedirectWithMessage('error', utSmLbl('LBL_UT_SM_OAUTH_STATE_INVALID', 'Invalid OAuth state. Please authorize again.'));
}

if (empty($appId) || empty($appSecret) || empty($redirectUri)) {
    utSmRedirectWithMessage('error', utSmLbl('LBL_UT_SM_OAUTH_CONFIG_MISSING', 'Missing OAuth app configuration. Save settings first.'));
}

$result = $tokenService->completeOAuth($code, $redirectUri);
if (!$result['ok']) {
    utSmRedirectWithMessage(
        'error',
        utSmLbl('LBL_UT_SM_TOKEN_EXCHANGE_FAILED', 'Token exchange failed.') . ' ' . $result['error']
    );
}

$count = isset($result['count']) ? (int) $result['count'] : 0;
utSmRedirectWithMessage(
    'success',
    utSmLbl('LBL_UT_SM_OAUTH_COMPLETED_PAGES_SYNCED', 'OAuth authorization completed. Connected pages synced:') . ' ' . $count . '.'
);
