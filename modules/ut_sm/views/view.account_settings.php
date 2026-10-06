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
 * Per-account Lead Settings view (assignment, forms, field mapping).
 *
 * Loaded via: index.php?module=ut_sm&action=account_settings&record={account_id}
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/MVC/View/SugarView.php';
require_once 'modules/ut_sm/services/AccountConfigService.php';

/**
 * Account configuration view for a connected Meta page/account.
 *
 * ViewFactory resolves: ut_sm + account_settings → Ut_smViewAccount_settings
 */
class Ut_smViewAccount_settings extends SugarView
{
    /** @var array View chrome options for admin config screens */
    public $options = array(
        'show_header' => true,
        'show_title' => true,
        'show_subpanels' => false,
        'show_search' => false,
        'show_footer' => true,
        'show_javascript' => true,
        'view_print' => false,
    );

    /** @var UTSMAccountConfigService */
    protected $configService;

    /** @var string */
    protected $record = '';

    /** @var array|null */
    protected $account = null;

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
            "<a href='index.php?module=ut_sm&action=settings'>"
                . (!empty($mod_strings['LBL_UT_SM_OAUTH_PAGE_TITLE'])
                    ? $mod_strings['LBL_UT_SM_OAUTH_PAGE_TITLE']
                    : 'Meta Leads') . '</a>',
            !empty($mod_strings['LBL_UT_SM_LEAD_SETTINGS_FOR'])
                ? $mod_strings['LBL_UT_SM_LEAD_SETTINGS_FOR']
                : 'Lead Settings',
        );
    }

    /**
     * Ensure admin access and load account before handling actions / display.
     */
    public function preDisplay()
    {
        require_once 'modules/ut_sm/services/LicenseService.php';
        UTSMLicenseService::redirectToLicenseIfInvalid();

        $this->loadAccountContext();
    }

    /**
     * Handle refresh-forms and save before rendering.
     */
    public function process()
    {
        require_once 'modules/ut_sm/services/LicenseService.php';
        UTSMLicenseService::redirectToLicenseIfInvalid();

        $this->loadAccountContext();
        $this->handleActions();
        parent::process();
    }

    /**
     * Load admin check, config service, and account row.
     */
    protected function loadAccountContext()
    {
        global $current_user, $mod_strings;

        if (empty($current_user) || !is_admin($current_user)) {
            sugar_die(translate('LBL_UT_SM_ADMIN_ACCESS_REQUIRED', 'ut_sm'));
        }

        if ($this->configService instanceof UTSMAccountConfigService && !empty($this->account)) {
            return;
        }

        $mod_strings = return_module_language($GLOBALS['current_language'], 'ut_sm');
        $this->configService = new UTSMAccountConfigService();
        $this->record = isset($_REQUEST['record']) ? trim((string) $_REQUEST['record']) : '';
        $this->account = $this->configService->getAccountById($this->record);

        if (empty($this->account)) {
            $this->redirectWithMessage('error', translate('LBL_UT_SM_ACCOUNT_NOT_FOUND', 'ut_sm'));
        }
    }

    /**
     * Process refresh / save posts; redirects on completion.
     */
    protected function handleActions()
    {
        $record = $this->record;
        $account = $this->account;
        $configService = $this->configService;

        if (empty($_SESSION['ut_sm_account_form_token'])) {
            $_SESSION['ut_sm_account_form_token'] = bin2hex(
                function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16)
            );
        }

        if (!empty($_REQUEST['refresh_forms'])) {
            $refresh = $configService->refreshForms($account);
            if (!$refresh['ok']) {
                $this->redirectWithMessage(
                    'error',
                    translate('LBL_UT_SM_FORMS_REFRESH_FAILED', 'ut_sm') . ' ' . $refresh['error'],
                    $record
                );
            }
            $count = isset($refresh['count']) ? (int) $refresh['count'] : 0;
            $this->redirectWithMessage(
                'success',
                translate('LBL_UT_SM_FORMS_REFRESH_SUCCESS', 'ut_sm') . ' ' . $count,
                $record
            );
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_account_config'])) {
            $postedToken = isset($_POST['ut_sm_account_form_token']) ? (string) $_POST['ut_sm_account_form_token'] : '';
            $sessionToken = isset($_SESSION['ut_sm_account_form_token']) ? (string) $_SESSION['ut_sm_account_form_token'] : '';
            if ($sessionToken === '' || $postedToken === '' || !hash_equals($sessionToken, $postedToken)) {
                $this->redirectWithMessage('error', translate('LBL_UT_SM_CSRF_INVALID', 'ut_sm'), $record);
            }

            $rrIds = isset($_POST['rr_user_ids']) && is_array($_POST['rr_user_ids']) ? $_POST['rr_user_ids'] : array();
            $enabledForms = isset($_POST['enabled_form_ids']) && is_array($_POST['enabled_form_ids'])
                ? $_POST['enabled_form_ids']
                : array();
            $fieldMap = isset($_POST['field_map']) && is_array($_POST['field_map'])
                ? $_POST['field_map']
                : array();

            $save = $configService->saveConfiguration($record, array(
                'assignment_type' => isset($_POST['assignment_type']) ? $_POST['assignment_type'] : 'keep_empty',
                'assignment_user_id' => isset($_POST['assignment_user_id']) ? $_POST['assignment_user_id'] : '',
                'assignment_group_id' => isset($_POST['assignment_group_id']) ? $_POST['assignment_group_id'] : '',
                'rr_user_ids' => $rrIds,
                'enabled_form_ids' => $enabledForms,
                'field_map' => $fieldMap,
            ));

            if (!$save['ok']) {
                $this->redirectWithMessage(
                    'error',
                    !empty($save['error']) ? $save['error'] : translate('LBL_UT_SM_CONFIG_SAVE_FAILED', 'ut_sm'),
                    $record
                );
            }

            $this->redirectWithMessage('success', translate('LBL_UT_SM_CONFIG_SAVED', 'ut_sm'), $record);
        }
    }

    /**
     * Render account settings template.
     */
    public function display()
    {
        global $mod_strings;

        $mod_strings = return_module_language($GLOBALS['current_language'], 'ut_sm');
        $configService = $this->configService;
        $record = $this->record;

        // Reload after possible prior saves handled via redirect
        $account = $configService->getAccountById($record);
        if (empty($account)) {
            $this->redirectWithMessage('error', translate('LBL_UT_SM_ACCOUNT_NOT_FOUND', 'ut_sm'));
        }

        $forms = $configService->getFormsForAccount($record);
        $formsWithMappings = $configService->getFormsWithMappings($account);
        $assignmentType = !empty($account['assignment_type']) ? $account['assignment_type'] : 'keep_empty';
        $rrUserIds = $configService->getAssignmentService()->decodeUserIds(
            !empty($account['assignment_rr_user_ids']) ? $account['assignment_rr_user_ids'] : ''
        );
        $rrSelected = array();
        foreach ($rrUserIds as $uid) {
            $rrSelected[(string) $uid] = true;
        }

        $crmFieldOptions = $configService->getFieldMappingService()->getCrmFieldOptions();
        foreach ($formsWithMappings as &$formRow) {
            if (empty($formRow['mappings']) || !is_array($formRow['mappings'])) {
                continue;
            }
            foreach ($formRow['mappings'] as &$mapRow) {
                $selected = isset($mapRow['crm_field']) ? $mapRow['crm_field'] : '';
                $opts = array();
                foreach ($crmFieldOptions as $opt) {
                    $opts[] = array(
                        'value' => $opt['value'],
                        'label' => $opt['label'],
                        'selected' => ((string) $opt['value'] === (string) $selected),
                    );
                }
                $mapRow['crm_options'] = $opts;
            }
            unset($mapRow);
        }
        unset($formRow);

        $users = get_user_array(false);
        $savedUserId = !empty($account['assignment_user_id']) ? (string) from_html($account['assignment_user_id']) : '';
        $userOptions = array();
        foreach ($users as $uid => $uname) {
            $uid = (string) $uid;
            $userOptions[] = array(
                'id' => $uid,
                'name' => $uname,
                'rr_checked' => !empty($rrSelected[$uid]),
                'selected' => ($savedUserId !== '' && $savedUserId === $uid),
            );
        }

        $securityGroups = array();
        $db = DBManagerFactory::getInstance();
        $savedGroupId = !empty($account['assignment_group_id']) ? (string) from_html($account['assignment_group_id']) : '';
        $sgRes = $db->query("SELECT id, name FROM securitygroups WHERE deleted = 0 ORDER BY name ASC");
        while ($sgRow = $db->fetchByAssoc($sgRes)) {
            $groupId = (string) from_html($sgRow['id']);
            $securityGroups[] = array(
                'id' => $groupId,
                'name' => from_html($sgRow['name']),
                'selected' => ($savedGroupId !== '' && $savedGroupId === $groupId),
            );
        }

        $accountTypeLabel = (!empty($account['account_type']) && $account['account_type'] === 'instagram')
            ? $mod_strings['LBL_UT_SM_TYPE_INSTAGRAM']
            : $mod_strings['LBL_UT_SM_TYPE_FACEBOOK'];
        $isInstagram = (!empty($account['account_type']) && $account['account_type'] === 'instagram');

        $errorMessage = !empty($_REQUEST['oauth_error']) ? (string) $_REQUEST['oauth_error'] : '';
        $successMessage = !empty($_REQUEST['oauth_success']) ? (string) $_REQUEST['oauth_success'] : '';
        if (empty($_SESSION['ut_sm_account_form_token'])) {
            $_SESSION['ut_sm_account_form_token'] = bin2hex(
                function_exists('random_bytes') ? random_bytes(16) : openssl_random_pseudo_bytes(16)
            );
        }
        $formToken = $_SESSION['ut_sm_account_form_token'];

        if (empty($this->ss)) {
            $this->ss = new Sugar_Smarty();
        }

        $this->ss->assign('MOD', $mod_strings);
        $this->ss->assign('FORM_TOKEN', $formToken);
        $this->ss->assign('RECORD', $record);
        $this->ss->assign('ACCOUNT_NAME', from_html($account['page_name']));
        $this->ss->assign('ACCOUNT_TYPE_LABEL', $accountTypeLabel);
        $this->ss->assign('IS_INSTAGRAM', $isInstagram);
        $this->ss->assign('ASSIGNMENT_TYPE', $assignmentType);
        $this->ss->assign('IS_KEEP_EMPTY', $assignmentType === 'keep_empty');
        $this->ss->assign('IS_ROUND_ROBIN', $assignmentType === 'round_robin');
        $this->ss->assign('IS_SPECIFIC_USER', $assignmentType === 'specific_user');
        $this->ss->assign('IS_SECURITY_GROUP', $assignmentType === 'security_group');
        $this->ss->assign('USER_OPTIONS', $userOptions);
        $this->ss->assign('HAS_USERS', !empty($userOptions));
        $this->ss->assign('SECURITY_GROUPS', $securityGroups);
        $this->ss->assign('HAS_SECURITY_GROUPS', !empty($securityGroups));
        $this->ss->assign('FORMS', $forms);
        $this->ss->assign('HAS_FORMS', !empty($forms));
        $this->ss->assign('FORMS_WITH_MAPPINGS', $formsWithMappings);
        $this->ss->assign('HAS_FORM_MAPPINGS', !empty($formsWithMappings));
        $this->ss->assign('HAS_ERROR', $errorMessage !== '');
        $this->ss->assign('ERROR_MESSAGE', $errorMessage);
        $this->ss->assign('HAS_SUCCESS', $successMessage !== '');
        $this->ss->assign('SUCCESS_MESSAGE', $successMessage);

        $this->ss->display('modules/ut_sm/tpls/account_settings.tpl');
    }

    /**
     * @param string $type error|success
     * @param string $message
     * @param string $record
     */
    protected function redirectWithMessage($type, $message, $record = '')
    {
        $param = $type === 'error' ? 'oauth_error' : 'oauth_success';
        $url = 'index.php?module=ut_sm&action=settings&' . $param . '=' . urlencode($message);
        if ($record !== '') {
            $url = 'index.php?module=ut_sm&action=account_settings&record=' . urlencode($record)
                . '&' . $param . '=' . urlencode($message);
        }
        SugarApplication::redirect($url);
    }
}
