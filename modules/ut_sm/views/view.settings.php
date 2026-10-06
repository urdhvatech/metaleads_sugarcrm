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
/**
 * Main Meta Lead Ads OAuth / Connected Accounts settings view.
 *
 * Loaded via: index.php?module=ut_sm&action=settings
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/MVC/View/SugarView.php';
require_once 'modules/Administration/Administration.php';
require_once 'modules/ut_sm/services/TokenService.php';
require_once 'modules/ut_sm/services/GraphClient.php';
require_once 'modules/ut_sm/services/AccountConfigService.php';
require_once 'modules/ut_sm/services/ReconciliationService.php';

/**
 * OAuth app configuration, authorization, reconciliation, and connected accounts.
 *
 * ViewFactory resolves: ut_sm + settings → Ut_smViewSettings
 */
class Ut_smViewSettings extends SugarView
{
    /** @var array */
    public $options = array(
        'show_header' => true,
        'show_title' => true,
        'show_subpanels' => false,
        'show_search' => false,
        'show_footer' => true,
        'show_javascript' => true,
        'view_print' => false,
    );

    /**
     * @param bool $browserTitle
     * @return array
     */
    protected function _getModuleTitleParams($browserTitle = false)
    {
        global $mod_strings;

        return array(
            "<a href='index.php?module=Administration&action=index'>"
                . translate('LBL_MODULE_NAME', 'Administration') . '</a>',
            !empty($mod_strings['LBL_UT_SM_OAUTH_PAGE_TITLE'])
                ? $mod_strings['LBL_UT_SM_OAUTH_PAGE_TITLE']
                : 'Meta Leads',
        );
    }

    public function preDisplay()
    {
        global $current_user;

        if (empty($current_user) || !is_admin($current_user)) {
            sugar_die(translate('LBL_UT_SM_ADMIN_ACCESS_REQUIRED', 'ut_sm'));
        }

        require_once 'modules/ut_sm/services/LicenseService.php';
        UTSMLicenseService::redirectToLicenseIfInvalid();
    }

    public function display()
    {
        global $mod_strings, $current_user;

        if (empty($current_user) || !is_admin($current_user)) {
            sugar_die(translate('LBL_UT_SM_ADMIN_ACCESS_REQUIRED', 'ut_sm'));
        }

        $mod_strings = return_module_language($GLOBALS['current_language'], 'ut_sm');

        $admin = new Administration();
        $admin->retrieveSettings('ut_sm');

        $appId = isset($admin->settings['ut_sm_app_id']) ? $admin->settings['ut_sm_app_id'] : '';
        $appSecret = isset($admin->settings['ut_sm_app_secret']) ? $admin->settings['ut_sm_app_secret'] : '';
        $savedRedirectUri = isset($admin->settings['ut_sm_oauth_redirect_uri']) ? $admin->settings['ut_sm_oauth_redirect_uri'] : '';
        $accessToken = isset($admin->settings['ut_sm_access_token']) ? $admin->settings['ut_sm_access_token'] : '';
        $tokenExpiresAt = isset($admin->settings['ut_sm_token_expires_at']) ? $admin->settings['ut_sm_token_expires_at'] : '';
        $callbackUrl = isset($admin->settings['ut_sm_callback_url']) ? $admin->settings['ut_sm_callback_url'] : '';
        $verifyToken = isset($admin->settings['ut_sm_verify_token']) ? $admin->settings['ut_sm_verify_token'] : '';
        $reconciliationHours = isset($admin->settings['ut_sm_reconciliation_hours'])
            ? (int) $admin->settings['ut_sm_reconciliation_hours']
            : UTSMReconciliationService::DEFAULT_LOOKBACK_HOURS;
        if ($reconciliationHours < 1) {
            $reconciliationHours = UTSMReconciliationService::DEFAULT_LOOKBACK_HOURS;
        }

        $defaultRedirectUri = rtrim($GLOBALS['sugar_config']['site_url'], '/') . '/index.php?entryPoint=ut_sm_oauth_handler';
        $redirectUri = !empty($savedRedirectUri) ? $savedRedirectUri : $defaultRedirectUri;

        if (empty($_SESSION['ut_sm_form_token'])) {
            $_SESSION['ut_sm_form_token'] = bin2hex(
                function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16)
            );
        }
        $formToken = $_SESSION['ut_sm_form_token'];

        $configService = new UTSMAccountConfigService();
        $connectedAccounts = $configService->listConnectedAccounts();
        $hasConnectedAccounts = !empty($connectedAccounts);

        $oauthUrl = '';
        if (!empty($appId) && !empty($redirectUri)) {
            $oauthState = bin2hex(
                function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16)
            );
            $_SESSION['ut_sm_oauth_state'] = $oauthState;
            $params = array(
                'client_id' => $appId,
                'redirect_uri' => $redirectUri,
                'scope' => 'pages_show_list,leads_retrieval,pages_read_engagement,pages_manage_metadata,pages_manage_ads,business_management,ads_management,ads_read',
                'response_type' => 'code',
                'state' => $oauthState,
            );
            // Keep dialog version aligned with UTSMGraphClient::API_VERSION
            $oauthUrl = 'https://www.facebook.com/' . UTSMGraphClient::API_VERSION . '/dialog/oauth?' . http_build_query($params);
        }

        $tokenService = new UTSMTokenService();
        $tokenService->ensureScheduler();

        $reconciliationService = new UTSMReconciliationService();
        $reconciliationService->ensureScheduler();
        $reconciliationSummary = $reconciliationService->getLastRunSummary();

        $isConnected = !empty($accessToken);
        $tokenExpiryDisplay = !empty($tokenExpiresAt) ? $tokenExpiresAt . ' UTC' : '';
        $tokenNeedsRefresh = $isConnected && $tokenService->needsRefresh();

        $errorMessage = !empty($_REQUEST['oauth_error']) ? (string) $_REQUEST['oauth_error'] : '';
        $successMessage = !empty($_REQUEST['oauth_success']) ? (string) $_REQUEST['oauth_success'] : '';

        if (empty($this->ss)) {
            $this->ss = new Sugar_Smarty();
        }

        $this->ss->assign('CURRENT_APP_ID', $appId);
        $this->ss->assign('CURRENT_APP_SECRET', $appSecret);
        $this->ss->assign('CURRENT_REDIRECT_URI', $redirectUri);
        $this->ss->assign('CURRENT_CALLBACK_URL', $callbackUrl);
        $this->ss->assign('CURRENT_VERIFY_TOKEN', $verifyToken);
        $this->ss->assign('RECONCILIATION_HOURS', $reconciliationHours);
        $this->ss->assign('RECONCILIATION_SUMMARY', $reconciliationSummary);
        $this->ss->assign('HAS_RECONCILIATION_RUN', !empty($reconciliationSummary['last_run']));
        $this->ss->assign('HAS_RECONCILIATION_ERROR', !empty($reconciliationSummary['last_error']));
        $this->ss->assign('FORM_TOKEN', $formToken);
        $this->ss->assign('HAS_APP_CREDENTIALS', !empty($appId) && !empty($appSecret));
        $this->ss->assign('HAS_TOKEN', !empty($accessToken));
        $this->ss->assign('IS_APP_CONNECTED', $isConnected);
        $this->ss->assign('TOKEN_EXPIRES_AT', $tokenExpiryDisplay);
        $this->ss->assign('HAS_TOKEN_EXPIRY', !empty($tokenExpiryDisplay));
        $this->ss->assign('TOKEN_NEEDS_REFRESH', $tokenNeedsRefresh);
        $this->ss->assign('CONNECTED_ACCOUNTS', $connectedAccounts);
        $this->ss->assign('HAS_CONNECTED_ACCOUNTS', $hasConnectedAccounts);
        $this->ss->assign('HAS_OAUTH_URL', !empty($oauthUrl));
        $this->ss->assign('OAUTH_URL', $oauthUrl);
        $this->ss->assign('HAS_ERROR', $errorMessage !== '');
        $this->ss->assign('ERROR_MESSAGE', $errorMessage);
        $this->ss->assign('HAS_SUCCESS', $successMessage !== '');
        $this->ss->assign('SUCCESS_MESSAGE', $successMessage);
        $this->ss->assign('MOD', $mod_strings);

        $this->ss->display('modules/ut_sm/tpls/oauth_settings.tpl');
    }
}
